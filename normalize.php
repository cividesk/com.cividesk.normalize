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
require_once 'normalize.civix.php';

/**
 * Implementation of hook_civicrm_config
 */
function normalize_civicrm_config(&$config) {
  $extRoot = dirname( __FILE__ ) . DIRECTORY_SEPARATOR;
  if (is_dir($extRoot . 'packages')) {
    set_include_path($extRoot . 'packages' . PATH_SEPARATOR . get_include_path());
  }
  _normalize_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function normalize_civicrm_xmlMenu(&$files) {
  _normalize_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function normalize_civicrm_install() {
  return _normalize_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function normalize_civicrm_uninstall() {
  return _normalize_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function normalize_civicrm_enable() {
  // jump to the setup screen after enabling extension
  $session = CRM_Core_Session::singleton();
  $session->replaceUserContext(CRM_Utils_System::url('civicrm/admin/setting/reformat'));
  return _normalize_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function normalize_civicrm_disable() {
  return _normalize_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function normalize_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _normalize_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function normalize_civicrm_managed(&$entities) {
  return _normalize_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_navigationMenu
 */
function normalize_civicrm_navigationMenu( &$params ) {
  // Add menu entry for extension administration page
  _normalize_civix_insert_navigationMenu($params, 'Administer/Customize Data and Screens', array(
    'name'       => 'Cividesk Normalize',
    'url'        => 'civicrm/admin/setting/normalize',
    'permission' => 'administer CiviCRM',
  ));
}

/**
 * Implementation of hook_civicrm_pre
 */
function normalize_civicrm_pre( $op, $objectName, $objectId, &$objectRef ) {
  $normalize = CRM_Utils_Normalize::singleton();

  if (in_array($objectName, array('Individual','Organization','Household'))) {
    $normalize->normalize_contact($objectRef);
    // for CiviCRM 4.2.2 & lower only
    if (array_key_exists('phone', $objectRef) && is_array($objectRef['phone']))
      foreach($objectRef['phone'] as &$phone)
        $normalize->normalize_phone($phone);
    if (array_key_exists('address', $objectRef) && is_array($objectRef['address']))
      foreach($objectRef['address'] as &$address)
        $normalize->normalize_address($address);
  } elseif ($objectName == 'Phone') {
    $normalize->normalize_phone($objectRef);
  } elseif ($objectName == 'Address') {
    $normalize->normalize_address($objectRef);
  }
}