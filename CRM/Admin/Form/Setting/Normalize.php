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

  function preProcess() {
    // Needs to be here as from is build before default values are set
    $this->_settings = CRM_Utils_Normalize::getSettings();
    if (!$this->_settings) $this->_settings = array();

    // Get the default country information for phone/zip formatting
    $config = CRM_Core_Config::singleton();
    $this->_country = $config->defaultContactCountry();
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

    $element =& $this->add('checkbox',
      'contact_FullFirst',
      ts('Capitalize first letter of each word in all names')
    );
    $element =& $this->add('checkbox',
      'contact_OrgCaps',
      ts('Capitalize organization names')
    );

    $element =& $this->add('checkbox',
      'phone_normalize',
      ts('Normalize phone numbers ('. $this->_country .', prefix intl with +)')
    );
    $element =& $this->add('checkbox',
      'phone_IntlPrefix',
      ts('Normalize local numbers as International')
    );

    $element =& $this->add('checkbox',
      'address_CityCaps',
      ts('Capitalize city names')
    );
    $element =& $this->add('checkbox',
      'address_Zip',
      ts('Normalize zip codes and flag incorrect entries')
    );

    if ( ! CRM_Utils_Array::value('cividesk_registered', $this->_values) ) {
      $element =& $this->add('checkbox',
        'cividesk_register',
        ts('Register with Cividesk'));
    }
    $element =& $this->add('text',
      'cividesk_subscribed',
      ts('Send updates to'));
    $this->addRule('cividesk_subscribed', ts('Please enter a valid email address.'), 'email');

    //added these element to process normalization.
    $this->addElement('text', "to_contact_id", ts("To Contact ID"));
    $this->addElement('text', "from_contact_id", ts("From Contact ID"));
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
    $defaults['register'] = true;
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
        if (empty($fromContactId) || empty($toContactId)) {
            CRM_Core_Session::setStatus(ts('No contact has been updated'));
            return true;
        }
        $normalization = CRM_Utils_Normalize::singleton();
        $processingInfo = $normalization->processNormalization($fromContactId, $toContactId);
        
        $updateMessage = ts('Normalization done. <br />Total Contact(s) updated: %1 <br />Total Phone(s) updated: %2<br />Total Address(es) updated: %3', array(1 => count($processingInfo['name']), count($processingInfo['phone']), count($processingInfo['address'])));
        CRM_Core_Session::setStatus($updateMessage);
        
        return true;
    }
    
    // Check registration
    if ( CRM_Utils_Array::value('cividesk_register', $params) ) {
      if (CRM_Core_Cividesk::register("Normalize")) {
        CRM_Utils_Normalize::setSetting(true, 'cividesk_registered');
        CRM_Core_Session::setStatus(ts('Thank you for registering with Cividesk.'));
      }
    }
    // Check subscription
    if ( CRM_Utils_Array::value('cividesk_subscribed', $params) != CRM_Utils_Array::value('cividesk_subscribed', $this->_values) ) {
      if ($params['cividesk_subscribed']) {
        if (CRM_Core_Cividesk::register("Normalize", $params['cividesk_subscribed'])) {
          CRM_Core_Session::setStatus(ts('Thanks, we will send you email updates related to this extension.'));
        } else {
          $params['subscribed'] = '';
          CRM_Core_Session::setStatus(ts('Sorry, there was an error when subscribing. Please retry later.'));
        }
      }
      CRM_Utils_Normalize::setSetting($params['cividesk_subscribed'], 'cividesk_subscribed');
    }

    // Save all settings
    foreach ($this->_elementIndex as $key => $dontcare) {
      $prefix = reset(explode('_', $key));
      if (in_array($prefix, array('contact', 'phone', 'address'))) {
        CRM_Utils_Normalize::setSetting(CRM_Utils_Array::value($key, $params, 0), $key);
      }
    }
  } //end of function
} // end class
