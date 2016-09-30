<?php
/*
 +--------------------------------------------------------------------------+
 | Copyright IT Bliss LLC (c) 2012-2013                                     |
 +--------------------------------------------------------------------------+
 | This program is free software: you can redistribute it and/or modify     |
 | it under the terms of the GNU Affero General Public License as published |
 | by the Free Software Foundation, either version 3 of the License, or     |
 | (at your option) any later version.                                      |
 |                                                                          |
 | This program is distributed in the hope that it will be useful,          |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the            |
 | GNU Affero General Public License for more details.                      |
 |                                                                          |
 | You should have received a copy of the GNU Affero General Public License |
 | along with this program.  If not, see <http://www.gnu.org/licenses/>.    |
 +--------------------------------------------------------------------------+
*/

class CRM_Admin_Form_Setting_Normalize extends CRM_Admin_Form_Setting {
  protected $_settings;
  protected $_country;

  const QUEUE_NAME = 'normalize-contact';
  const END_URL    = 'civicrm/admin/setting/normalize';
  const END_PARAMS = 'state=done';

  function preProcess() {
    // Needs to be here as from is build before default values are set
    $this->_settings = CRM_Utils_Normalize::getSettings();
    if (!$this->_settings) $this->_settings = array();

    // Get the default country information for phone/zip formatting
    $config = CRM_Core_Config::singleton();
    $this->_country = $config->defaultContactCountry();

    $state = CRM_Utils_Request::retrieve('state', 'String', CRM_Core_DAO::$_nullObject, FALSE, 'tmp', 'GET');
    if ($state == 'done') {
      $stats = $this->_settings['normalization_stats'];
      $this->assign('stats', $stats);
    }
  }

  /**
   * Function to build the form
   *
   * @return None
   * @access public
   */
  public function buildQuickForm() {
    $this->applyFilter('__ALL__', 'trim');
    $this->assign('default_country', ($this->_country != NULL));

    $this->add('checkbox',
      'contact_FullFirst',
      ts('Capitalize first letter of each word in all names')
    );
    $this->add('checkbox',
      'contact_OrgCaps',
      ts('Capitalize organization names')
    );

    $this->add('checkbox',
      'phone_normalize',
      ts('Normalize phone numbers ('. $this->_country .', prefix intl with +)')
    );
    $this->add('checkbox',
      'phone_IntlPrefix',
      ts('Normalize local numbers as International')
    );

    $options = array(
      'O' => ts('City no format'),
      '1' => ts('Capitalize city names'),
      '2' => ts('Capitalize first letter of each word in city names')
    );
    $this->addRadio( 'address_CityCaps', ts(''), $options );

    $optionsStreet = array(
      'O' => ts('Street Address no format'),
      '1' => ts('Capitalize Street Address'),
      '2' => ts('Capitalize first letter of each word in Street Address, and directionals such as NE, NW, etc.')
    );
    $this->addRadio( 'address_StreetCaps', ts(''), $optionsStreet );
    
    $this->add('checkbox',
      'address_Zip',
      ts('Normalize zip codes and flag incorrect entries')
    );

    //added these element to process normalization.
    $this->addElement('text', "to_contact_id", ts("To Contact ID"));
    $this->addElement('text', "from_contact_id", ts("From Contact ID"));
    $this->addElement('text', "batch_size", ts("Batch Size..."));
    $this->addFormRule(array('CRM_Admin_Form_Setting_Normalize', 'formRule'));    
    
    
    $this->addButtons(array(
      array(
        'type' => 'submit',
        'name' => ts('Save'),
        'isDefault' => TRUE,
      ),
      array(
        'type' => 'cancel',
        'name' => ts('Cancel'),
      ),
    ));
  }

  function setDefaultValues() {
    $defaults = $this->_settings;
    return $defaults;
  }

  static function formRule($fields) {
    $errors = array();
    //validate data when user click on perform normalization.
    if (isset($fields['_qf_Normalize_submit']) && $fields['_qf_Normalize_submit'] == 'Perform Normalization') {
      //validate from contact id.
      if (empty($fields['to_contact_id'])) {
        $errors['to_contact_id'] = ts("Please enter last contact id.");
      }
      //validate from contact id.
      if (empty($fields['from_contact_id'])) {
        $errors['from_contact_id'] = ts("Please enter start contact id.");
      }
      if (empty($fields['batch_size'])) {
        $errors['batch_size'] = ts("Please enter Batch Size.");
      }
    }

    return empty($errors) ? TRUE : $errors;
  }

  /**
   * Function to process the form
   *
   * @access public
   * @return None
   */
  public function postProcess(){
    // store the submitted values in an array
    $params = $this->exportValues();

    if (isset($params['_qf_Normalize_submit']) && $params['_qf_Normalize_submit'] == 'Perform Normalization') {
      $fromContactId = $params['from_contact_id'];
      $toContactId = $params['to_contact_id'];
      $batchSize = $params['batch_size'];
      if (empty($fromContactId) || empty($toContactId)) {
        CRM_Core_Session::setStatus(ts('No contact has been updated'));
        return true;
      }
      $runner = self::getRunner( false, $fromContactId, $toContactId, $batchSize);
      if ($runner) {
        // Run Everything in the Queue via the Web.
        $runner->runAllViaWeb();
      }
      return true;
    }

    // Save all settings
    foreach ($this->_elementIndex as $key => $dontcare) {
      $prefix = explode('_', $key);
      $prefix = reset($prefix);
      if (in_array($prefix, array('contact', 'phone', 'address'))) {
        CRM_Utils_Normalize::setSetting(CRM_Utils_Array::value($key, $params, 0), $key);
      }
    }
  } //end of function

  static function getRunner($skipEndUrl = FALSE, $fromContactId, $toContactId, $batchSize) {
    // Setup the Queue
    $queue = CRM_Queue_Service::singleton()->create(array(
      'name'  => self::QUEUE_NAME,
      'type'  => 'Sql',
      'reset' => TRUE,
    ));

    CRM_Utils_Normalize::setSetting(array('contact' => 0, 'phone' => 0, 'address' => 0), 'normalization_stats');

    for ($startId = $fromContactId; $startId <= $toContactId; $startId += $batchSize) {
      $endId = $startId + $batchSize - 1;
      $title = ts('Normalizing contacts (%1 => %2)', array(1 => $startId, 2 => $endId));

      $task  = new CRM_Queue_Task(
        array ('CRM_Admin_Form_Setting_Normalize', 'normalizeContacts'),
        array($startId, $endId, $title),
        "Preparing queue for $title"
      );

      // Add the Task to the Queue
      $queue->createItem($task);
    }

    // Setup the Runner
    $runnerParams = array(
      'title' => ts('Contact Normalization'),
      'queue' => $queue,
      'errorMode'=> CRM_Queue_Runner::ERROR_ABORT,
      'onEndUrl' => CRM_Utils_System::url(self::END_URL, self::END_PARAMS, TRUE, NULL, FALSE),
    );
    // Skip End URL to prevent redirect
    // if calling from cron job
    if ($skipEndUrl == TRUE) {
      unset($runnerParams['onEndUrl']);
    }
    $runner = new CRM_Queue_Runner($runnerParams);
    return $runner;
  }

  static function normalizeContacts(CRM_Queue_TaskContext $ctx, $fromId, $toId) {
    $normalization  = CRM_Utils_Normalize::singleton();
    $processingInfo = $normalization->processNormalization($fromId, $toId);
    $updateInfo = array('contact' => count($processingInfo['name']), 'phone' => count($processingInfo['phone']), 'address'=> count($processingInfo['address']));
    self::updatePushStats($updateInfo);
    return CRM_Queue_Task::TASK_SUCCESS;
  }

  /**
  * Update the push stats setting.
  */
  static function updatePushStats($updates) {
    $stats = CRM_Utils_Normalize::getSettings('normalization_stats');
    foreach ($updates as $name => $value) {
      $stats[$name] += $value;
    }
    CRM_Utils_Normalize::setSetting($stats, 'normalization_stats');
  }

} // end class
