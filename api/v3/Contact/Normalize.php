<?php

/**
 * Contact.Normalize API: Normalizes one or more contacts
 *
 * @param  array $params  an associative array of contacts.
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_contact_normalize($params) {
  $normalize = CRM_Utils_Normalize::singleton();
  $contacts = CRM_Utils_Array::value('values', $params);
  if (sizeof($contacts)) {
    foreach ($contacts as &$contact) {
      $normalize->normalize_contact($contact);
    }
  }
  return civicrm_api3_create_success($contacts);
}