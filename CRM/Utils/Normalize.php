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

use libphonenumber\PhoneNumberUtil;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\NumberParseException;

require_once 'libphonenumber/PhoneNumberUtil.php';

class CRM_Utils_Normalize {
  CONST NORMALIZE_PREFERENCES_NAME = 'Normalize Preferences';

  private static $_singleton = NULL;

  private $_settings;
  private $_country;
  private $_nameFields;
  private $_phoneFields;
  private $_addressFields;


  static function singleton() {
    if (!self::$_singleton) {
      self::$_singleton = new CRM_Utils_Normalize();
    }
    return self::$_singleton;
  }

  /**
   * Construct a normalizer
   */
  public function __construct() {
    $this->_settings = $this->getSettings();
    // Get the default country information for phone/zip formatting
    $config = CRM_Core_Config::singleton();
    $this->_country = $config->defaultContactCountry();

    $this->_nameFields = array('first_name', 'middle_name', 'last_name', 'organization_name', 'household_name');
    $this->_phoneFields = array('phone');
    $this->_addressFields = array('city', 'postal_code');
  }

  /**
   * Returns normalizer settings
   */
  static function getSettings($name = NULL) {
    return CRM_Core_BAO_Setting::getItem(CRM_Utils_Normalize::NORMALIZE_PREFERENCES_NAME, $name);
  }

  static function setSetting($value, $name) {
    CRM_Core_BAO_Setting::setItem($value, CRM_Utils_Normalize::NORMALIZE_PREFERENCES_NAME, $name);
  }

  /**
   * Normalizes a name according to International rules - as much as we can
   *
   * For Individual names:
   * - put all in lowercase, then capitalize first letter of each word (with exceptions)
   * - examples:
   *   jean-pierre DE castignac => Jean-Pierre de Castignac
   *
   * For Organization names:
   * - capitalize all characters before dots & know organization statuses
   * - examples:
   *   IT bliss, l.l.c. => IT Bliss, L.L.C.
   *   IT bliss, LLC    => IT Bliss, LLC
   *   frank and sons moving co => Frank and Sons Moving CO
   *
   * @param $contact
   *   Name that needs to be normalized
   */
  function normalize_contact(&$contact) {
    $handles = array(
      'de', 'des', 'la', // France
      'da', 'den', 'der', 'ten', 'ter', 'van', // Neederlands
      'von', // Germany
      'et', 'and', 'und', // For company names
      'dos', 'das', 'do', 'du',
      "s" // skip apostrophe s
    );
    // These will be capitalized
    $orgstatus = array(
      'llc', 'llp', 'pllc', 'lp', 'pc', // USA
      'sa', 'sarl', 'sc', 'sci', // France
      'fze', 'fz', 'fz-llc', 'fz-co', 'rak', // UAE
      'usa', 'uae',
    );
    // These will be Firstcaped with a dot at the end
    $orgstatusSpecial = array( 'inc', 'co', 'corp', 'ltd' );
    
    $delimiters = array( "-", ".", "'","D'", "O'", "Mc", " ",);

    if (CRM_Utils_Array::value('contact_FullFirst', $this->_settings)) {
      foreach ($this->_nameFields as $field) {
        $name = CRM_Utils_Array::value($field, $contact);
        //Handle null value during Contact Merge
        if (empty($name) || ($name === "null")) {
          continue;
        }
        ///$name = mb_convert_case($name, MB_CASE_TITLE, "UTF-8");
        foreach ($delimiters as $delimiter) {
          $words = explode($delimiter, $name);
          $newWords = array();
          foreach ($words as $word) {
            if ( CRM_Utils_Array::value('contact_type', $contact) == 'Organization') {
              // Capitalize organization statuses
              // in_array is case sensitive, lower case the $word
              if ( in_array(str_replace(array('.'), '', strtolower($word)), $orgstatus) ) {
                $word = strtoupper($word);
              } else if ( in_array(str_replace(array('.'), '', strtolower($word)), $orgstatusSpecial) ) {
                // special status only need first letter to be capitalize
                $word = str_replace(array('.'), '', strtolower($word)) . '.';
              }
            } elseif ( CRM_Utils_Array::value('contact_type', $contact) == 'Individual') {
               // lower case few matching word for individual contact
               if (in_array(strtolower($word), $handles)) {
                 $word = strtolower($word);
               }
            }
            if (!in_array($word, $handles)) {
              $word = strtolower($word);
              $word = ucfirst($word);
            }
            array_push($newWords, $word);
          }
          $name = join($delimiter, $newWords);
          if (CRM_Utils_Array::value('contact_type', $contact) == 'Individual') {
            // if name is matching handles, then normalize it
            if (in_array(strtolower($name), $handles)) {
              $name = ucwords(strtolower($name));
            }
          }
        }
        $contact[$field] = $name;
        
      }
    }
    if (CRM_Utils_Array::value('contact_OrgCaps', $this->_settings)) {
      if ((CRM_Utils_Array::value('contact_type', $contact) == 'Organization')
        && CRM_Utils_Array::value('organization_name', $contact)
      ) {
        $contact['organization_name'] = strtoupper($contact['organization_name']);
      }
    }
    return TRUE;
  }

  /**
   * Normalizes a phone number
   * @param $phone
   *   Name that needs to be reformatted
   */

  function normalize_phone(&$phone) {
    if (empty($phone)) {
      return false;
    }
    $input = $phone['phone'];
    if (empty($input)) {
      return FALSE;
    } // Should not be empty

    $country = ($this->_country ? $this->_country : 'US');
    $phoneUtil = PhoneNumberUtil::getInstance();
    try {
      $phoneProto = $phoneUtil->parse($input, $country);
    } catch (NumberParseException $e) {
      return FALSE;
    }
    if (!$phoneUtil->isValidNumber($phoneProto)) {
      return FALSE;
    }
    if (CRM_Utils_Array::value('phone_IntlPrefix', $this->_settings)) // always give the international format
    {
      $phone['phone'] = $phoneUtil->format($phoneProto, PhoneNumberFormat::INTERNATIONAL);
    }
    elseif (CRM_Utils_Array::value('phone_normalize', $this->_settings)) // in-country for local, international for all others
    {
      if ($phoneProto->getCountryCode() == $phoneUtil->getCountryCodeForRegion($country)) {
        $phone['phone'] = $phoneUtil->format($phoneProto, PhoneNumberFormat::NATIONAL);
      }
      else {
        $phone['phone'] = $phoneUtil->format($phoneProto, PhoneNumberFormat::INTERNATIONAL);
      }
    }
    return TRUE;
  }

  /**
   * Normalizes a name according to International rules - as much as we can
   * Example of normalized Individual names:
   * jean-pierre DE castignac => Jean-Pierre de Castignac
   * Example of normalized Organization names:
   * it bliss, llc => It Bliss, Llc
   * IT bliss, LLC => IT Bliss, LLC
   * @param $name
   *   Name that needs to be normalized
   * @param $type
   *   Individual or Organization
   * @param $this ->_settings
   *   Options for the conversion
   */

  function normalize_address(&$address) {
    $zip_formats = array(
      'US' => '/^(\d{5})(-[0-9]{4})?$/i',
      'FR' => '/^(\d{5})$/i',
      'NL' => '/^(\d{4})\s*([a-z]{2})$/i',
    );
    // These will be all-capped
    $directionals = array(
      'ne', 'nw', 'se', 'sw',
    );
    if ($value = CRM_Utils_Array::value('address_CityCaps', $this->_settings)) {
      $city = CRM_Utils_Array::value('city', $address);
      if ($value == 1 && $city) {
        $address['city'] = strtoupper($city);
      } elseif($value == 2 && $city) {
        $address['city'] = ucwords(strtolower($city));
      }
    }
    if ($value = CRM_Utils_Array::value('address_StreetCaps', $this->_settings)) {
      foreach( array('street_address','supplemental_address_1', 'supplemental_address_2') as $name) { 
        $addressValue = CRM_Utils_Array::value($name, $address);
        if ($value == 1 && $addressValue) {
          $address[$name] = strtoupper($addressValue);
        } elseif($value == 2 && $name) {
          $address[$name] = ucwords(strtolower($addressValue));
          $patterns = array();
          foreach ($directionals as $d) {
            $patterns[] = "/\\b$d\\b/i";
          }
          $address[$name] = preg_replace_callback($patterns, function($matches){return strtoupper($matches[0]);}, $address[$name]);
        }
      }
    }
    if (CRM_Utils_Array::value('address_Zip', $this->_settings)) {
      if (($zip = CRM_Utils_Array::value('postal_code', $address))
          && ($cid = CRM_Utils_Array::value('country_id', $address))) {
        $codes = CRM_Core_PseudoConstant::countryIsoCode();
        if ($regex = CRM_Utils_Array::value($codes[$cid], $zip_formats)) {
          if (!preg_match($regex, $zip, $matches)) {
            CRM_Core_Session::setStatus(ts('Invalid Zip Code format %1', array(1 => $zip)));
          }
        }
      }
    }

    return TRUE;
  }

  function getNameFields() {
    return $this->_nameFields;
  }

  function getAddressFields() {
    return $this->_addressFields;
  }

  function getPhoneFields() {
    return $this->_phoneFields;
  }

  static function processNormalization($fromContactId, $toContactId) {
    $processInfo = array('name' => 0, 'phone' => 0, 'address' => 0);
    if (empty($fromContactId) || empty($toContactId)) {
      return $processInfo;
    }

    $contactIds = range($fromContactId, $toContactId);

    $normalization = CRM_Utils_Normalize::singleton();

    $formattedContactIds = $formattedPhoneIds = $formattedAddressIds = array();
    foreach ($contactIds as $contactId) {
      $contact = new CRM_Contact_DAO_Contact();
      $contact->id = $contactId;
      if ($contact->find()) {
        $params = array('id' => $contactId, 'contact_id' => $contactId);
        $orgContactValues = array();
        CRM_Contact_BAO_Contact::retrieve($params, $orgContactValues);
        //update contacts name fields.
        $formatNameValues = array();
        foreach ($normalization->getNameFields() as $field) {
          $nameValue = CRM_Utils_Array::value($field, $orgContactValues);
          if (empty($nameValue)) {
            continue;
          }
          $formatNameValues[$field] = $nameValue;
        }
        if (!empty($formatNameValues)) {
          $formatNameValues['contact_type'] = $orgContactValues['contact_type'];
          $formattedNameValues = $formatNameValues;

          //format name values
          $normalization->normalize_contact($formattedNameValues);

          //check formatted diff, only update if there is difference.
          $formatDiff = array_diff($formatNameValues, $formattedNameValues);
          if (!empty($formatDiff)) {
            $formattedNameValues['id'] = $formattedNameValues['contact_id'] = $orgContactValues['id'];
            $formattedNameValues['contact_type'] = $orgContactValues['contact_type'];
            $contactUpdated = CRM_Contact_BAO_Contact::add($formattedNameValues);
            if ($contactUpdated->id) {
              $formattedContactIds[$contactUpdated->id] = $contactUpdated->id;
            }
            $contactUpdated->free();
          }
        }

        //update phone fields.
        if (isset($orgContactValues['phone']) && is_array($orgContactValues['phone'])) {
          foreach ($orgContactValues['phone'] as $cnt => $orgPhoneValues) {
            if (!isset($orgPhoneValues['id']) || empty($orgPhoneValues['id']) || empty($orgPhoneValues['phone'])) {
              continue;
            }

            $formattedPhoneValues = $orgPhoneValues;

            //format phone fields
            $normalization->normalize_phone($formattedPhoneValues);

            //do check for formatted difference, than only update.
            $formattedDiff = array_diff_assoc($orgPhoneValues, $formattedPhoneValues);
            if (!empty($formattedDiff)) {
              $phoneUpdated = CRM_Core_BAO_Phone::add($formattedPhoneValues);
              if ($phoneUpdated->id) {
                $formattedPhoneIds[$phoneUpdated->id] = $phoneUpdated->id;
              }
              $phoneUpdated->free();
            }
          }
        }

        //update address.
        if (isset($orgContactValues['address']) && is_array($orgContactValues['address'])) {
          foreach ($orgContactValues['address'] as $cnt => $orgAddressValues) {
            if (!isset($orgAddressValues['id']) || empty($orgAddressValues['id'])) {
              continue;
            }

            $formattedAddressValues = $orgAddressValues;

            //format addrees fields
            $normalization->normalize_address($formattedAddressValues);

            //do check for formatted difference, than only update.
            $formattedDiff = array_diff($orgAddressValues, $formattedAddressValues);
            if (!empty($formattedDiff)) {
              $addressUpdated = CRM_Core_BAO_Address::add($formattedAddressValues, FALSE);
              if ($addressUpdated->id) {
                $formattedAddressIds[$addressUpdated->id] = $addressUpdated->id;
              }
              $addressUpdated->free();
            }
          }
        }
      }
      $contact->free();
    }

    $processInfo = array(
      'name' => $formattedContactIds,
      'phone' => $formattedPhoneIds,
      'address' => $formattedAddressIds
    );

    return $processInfo;
  }
}
