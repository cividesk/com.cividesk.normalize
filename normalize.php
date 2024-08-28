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
  _normalize_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_install
 */
function normalize_civicrm_install() {
  return _normalize_civix_civicrm_install();
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
 * Implementation of hook_civicrm_navigationMenu
 */
function normalize_civicrm_navigationMenu( &$params ) {
  // Add menu entry for extension administration page
  _normalize_civix_insert_navigation_menu($params, 'Administer/Customize Data and Screens', array(
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
