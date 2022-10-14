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

require_once 'packages/libphonenumber/PhoneNumberUtil.php';

class CRM_Utils_Normalize {

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
    $this->_country = CRM_Core_BAO_Country::defaultContactCountry();

    $this->_nameFields = ['first_name', 'middle_name', 'last_name', 'organization_name', 'household_name', 'legal_name', 'nick_name'];
    $this->_phoneFields = ['phone'];
    $this->_addressFields = ['city', 'postal_code'];
  }

  /**
   * Returns normalizer settings
   */
  static function getSettings($name = NULL) {
    if (!empty($name)) {
      return Civi::settings()->get($name);
    }
    // group name not used anymore, so fetch only normalization related setting (also suppress warning)
    $settingsField = ['contact_FullFirst', 'contact_LastnameToUpper', 'contact_OrgCaps', 'contact_Gender', 'phone_normalize',
      'phone_IntlPrefix', 'address_CityCaps', 'address_StreetCaps', 'address_Zip', 'normalization_stats', 'address_postal_validation'];
    $settings = [];
    foreach ($settingsField as $fieldName) {
      $settings[$fieldName] = Civi::settings()->get($fieldName);
    }

    return $settings;
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
    $handles = [
      'de', 'des', 'la', // France
      'da', 'den', 'der', 'ten', 'ter', 'van', // Neederlands
      'von', // Germany
      'et', 'and', 'und', // For company names
      'dos', 'das', 'do', 'du',
      "s" // skip apostrophe s
    ];

    // These will be small
    $orgHandles = [
      'of'
    ];

    // These will be capitalized
    $orgstatus = [
      'llc', 'llp', 'pllc', 'lp', 'pc', // USA
      'sa', 'sarl', 'sc', 'sci', // France
      'fze', 'fz', 'fz-llc', 'fz-co', 'rak', // UAE
      'usa', 'uae',
    ];
    // These will be Firstcaped with a dot at the end
    $orgstatusSpecial = ['inc', 'co', 'corp', 'ltd'];

    $delimiters = ["-", ".", "D'", "O'", "Mc", " ",];

    // Set Gender Using Contact Prefix Value
    if ($contact['contact_type'] == 'Individual' && CRM_Utils_Array::value('contact_Gender', $this->_settings)) {
      $prefixValue = CRM_Utils_Array::value('prefix_id', $contact);
      if ($prefixValue) {
        // get key and name of prefix
        $prefix = CRM_Core_PseudoConstant::get('CRM_Contact_DAO_Contact', 'prefix_id', [], 'validate');
        // get key and name of gender with flip
        $gender = CRM_Core_PseudoConstant::get('CRM_Contact_DAO_Contact', 'gender_id', ['flip' => 1], 'validate');

        // Get Name from ID
        $prefixName = $prefix[$prefixValue];
        if ($prefixName && in_array($prefixName, ['Mr.']) && !empty($gender['Male'])) {
          $contact['gender_id'] = $gender['Male'];
        }
        elseif ($prefixName && in_array($prefixName, ['Ms.', 'Mrs.']) && !empty($gender['Female'])) {
          $contact['gender_id'] = $gender['Female'];
        }
      }
    }

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
          $newWords = [];
          foreach ($words as $word) {
            if (CRM_Utils_Array::value('contact_type', $contact) == 'Organization') {
              // Capitalize organization statuses
              // in_array is case sensitive, lower case the $word
              if (in_array(str_replace(['.'], '', strtolower($word)), $orgstatus)) {
                $word = strtoupper($word);
              }
              elseif (in_array(str_replace(['.'], '', strtolower($word)), $orgstatusSpecial)) {
                // special status only need first letter to be capitalize
                $word = str_replace(['.'], '', strtolower($word)) . '.';
              }
              elseif (in_array(strtolower($word), $orgHandles)) {
                // lower case few matching word for Organization contact
                $word = strtolower($word);
              }
            }
            elseif (CRM_Utils_Array::value('contact_type', $contact) == 'Individual') {
              // lower case few matching word for individual contact
              if (in_array(strtolower($word), $handles)) {
                $word = strtolower($word);
              }
            }
            if (!in_array($word, $handles) && !in_array($word, $orgHandles)) {
              // in case name does not contain special handler char, normalize with lower all char and then use ucfirst
              if (CRM_Utils_Array::value('contact_type', $contact) == 'Individual') {
                $word = strtolower($word);
              }
              //use these delimiters to capitalize
              $word = ucwords($word, '-');
              $word = ucwords($word, "'");
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
    // upper case individual last name if setting is ON.
    if (!empty($contact['last_name']) && $contact['contact_type'] == 'Individual' && CRM_Utils_Array::value('contact_LastnameToUpper', $this->_settings)) {
      $contact['last_name'] = strtoupper($contact['last_name']);
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
      return FALSE;
    }
    $input = $phone['phone'];
    if (empty($input)) {
      return FALSE;
    } // Should not be empty

    $country = ($this->_country ? $this->_country : 'US');
    $phoneUtil = PhoneNumberUtil::getInstance();
    try {
      $phoneProto = $phoneUtil->parse($input, $country);
    }
    catch (NumberParseException $e) {
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
    $zip_formats = [
      'US' => '/^(\d{5})(-[0-9]{4})?$/i',
      'FR' => '/^(\d{5})$/i',
      'NL' => '/^(\d{4})\s*([A-Z]{2})$/i',
      'CA' => '/^([ABCEGHJKLMNPRSTVXY]\d[ABCEGHJKLMNPRSTVWXYZ])\ {0,1}(\d[ABCEGHJKLMNPRSTVWXYZ]\d)$/i'
    ];

    // First let's get the country ISO code
    $country = CRM_Utils_Array::value('country_id', $address) ? CRM_Core_PseudoConstant::countryIsoCode($address['country_id']) : NULL;

    // Reformat address lines
    $directionals = [
      'US' => ['ne', 'nw', 'se', 'sw'],
      'CA' => ['ne', 'nw', 'se', 'sw', 'po', 'rr'],
    ];

    $suffixes = ['st', 'th', 'nd', 'rd'];
    if ($value = CRM_Utils_Array::value('address_StreetCaps', $this->_settings)) {
      foreach (['street_address', 'supplemental_address_1', 'supplemental_address_2'] as $name) {
        $addressValue = CRM_Utils_Array::value($name, $address);
        if ($value == 1 && $addressValue) {
          $address[$name] = strtoupper($addressValue);
        }
        elseif ($value == 2 && $addressValue) {
          $address[$name] = $this->ucFirst($addressValue);
          // Capitalize directionals and other misc items
          if ($country && array_key_exists($country, $directionals)) {
            $patterns = [];
            foreach ($directionals[$country] as $d) {
              $patterns[] = "/\\b$d\\b/i";
            }
            $address[$name] = preg_replace_callback($patterns, function ($matches) {
              return strtoupper($matches[0]);
            }, $address[$name]);
          }

          $patterns = [];
          foreach ($suffixes as $suffix) {
            $patterns[] = "/[0-9\.]+$suffix/i";
          }

          //numbers suffixes st, th, nd, rd should remain
          //in small caps when directly against a number
          $address[$name] = preg_replace_callback($patterns, function ($matches) {
            return strtolower($matches[0]);
          }, $address[$name]);
        }

        //PO Box and P.O. Box should not be changed to Po or P.o. Box
        $pattern = "/((?:P(?:OST)?.?\s*(?:O(?:FF(?:ICE)?)?)?.?\s*(?:B(?:IN|OX)?)+)+|(?:B(?:IN|OX)+\s+)+)\s*\d+/i";
        $address[$name] =  preg_replace_callback($pattern, function ($matches) {
          return str_replace("BOX", "Box", strtoupper($matches[0]));
        }, $address[$name]);
      }
    }

    // Reformat city
    if ($value = CRM_Utils_Array::value('address_CityCaps', $this->_settings)) {
      $city = CRM_Utils_Array::value('city', $address);
      if ($value == 1 && $city) {
        $address['city'] = strtoupper($city);
      }
      elseif ($value == 2 && $city) {
        $address['city'] = $this->ucFirst($city);
      }
    }

    // Reformat postal code ONLY FOR CA
    if (CRM_Utils_Array::value('address_Zip', $this->_settings)) {
      // http://www.pidm.net/postal%20code.html: there are currently no examples of postal codes written with lower-case letters
      if ($address['postal_code']) {
        $address['postal_code'] = strtoupper($address['postal_code']);
      }

      if ($country == 'CA' && ($zip = CRM_Utils_Array::value('postal_code', $address))) {
        $zip = trim($zip);
        if ($regex = CRM_Utils_Array::value($country, $zip_formats)) {
          if (!preg_match($regex, $zip, $matches)) {

            // Zip code is invalid for country
            // 1. send an email if configured
            if (CRM_Utils_Array::value('address_postal_validation', $this->_settings)) {
              // Get email Id from Normalize Admin page
              $emailTo = CRM_Utils_Array::value('address_postal_validation', $this->_settings);
              if (!empty($emailTo) && !empty($address['contact_id'])) {
                // Send Email
                $this->sendEmail($emailTo, $address['contact_id']);
              }
            }
            // 2. display an error message on screen
            CRM_Core_Session::setStatus(ts('Invalid Zip Code format %1', [1 => $zip]));
          }
          else {
            // Zip code is valid
            //Check for Single Space and add Space If user not added
            $space_regex = '/^([a-zA-Z]\d[a-zA-Z][ -])?(\d[a-zA-Z]\d)$/';
            if (!preg_match($space_regex, $zip)) {
              $address['postal_code'] = substr($zip, 0, 3) . ' ' . substr($zip, 3);
            }
          }
        }

        // Set the State/Province as per Zip code ONLY FOR CANADA
        // Task : https://projects.cividesk.com/projects/28/tasks/858
        if ($country == 'CA') {
          $first_char = strtoupper(substr($zip, 0, 1));
          $three_char = strtoupper(substr($zip, 0, 3));
          if (!empty($first_char)) {
            switch ($first_char) {
              case 'A':
                //Newfoundland and Labrador - 1104
                $address['state_province_id'] = 1104;
                break;
              case 'B':
                //Nova Scotia - 1106
                $address['state_province_id'] = 1106;
                break;
              case 'C':
                //Prince Edwards Island - 1109
                $address['state_province_id'] = 1109;
                break;
              case 'E':
                //New Brunswick - 1103
                $address['state_province_id'] = 1103;
                break;
              case 'G':
              case 'H':
              case 'J':
                //Quebec - 1110
                $address['state_province_id'] = 1110;
                break;
              case 'K':
              case 'L':
              case 'M':
              case 'N':
              case 'P':
                //Ontario -  1108
                $address['state_province_id'] = 1108;
                break;
              case 'R':
                //Manitoba - 1102
                $address['state_province_id'] = 1102;
                break;
              case 'S':
                //Saskatchewan - 1111
                $address['state_province_id'] = 1111;
                break;
              case 'T':
                //Alberta - 1100
                $address['state_province_id'] = 1100;
                break;
              case 'V':
                //British Columbia - 1101
                $address['state_province_id'] = 1101;
                break;
              case 'Y':
                //Yukon Territories - 1112
                $address['state_province_id'] = 1112;
                break;
            }
          }
          if (!empty($three_char)) {
            switch ($three_char) {
              case 'X0A':
              case 'X0B':
              case 'X0G':
                //Nuvanut - 1107
                $address['state_province_id'] = 1107;
                break;
              case 'X1A':
              case 'X0E':
              case 'X0G':
                //Northwest Territories - 1105
                $address['state_province_id'] = 1105;
                break;
            }
          }
        }

      }
    }

    return TRUE;
  }

  function sendEmail($emailTo, $contactId) {
    [$domainEmailName, $domainEmailAddress] = CRM_Core_BAO_Domain::getNameAndEmail();
    [$contact_name, $contact_email] = CRM_Contact_BAO_Contact::getContactDetails($contactId);
    $mailBody = "<html><head></head><body>";
    $mailBody .= "User <a href='{$_SERVER['HTTP_HOST']}/civicrm/contact/view?reset=1&cid={$contactId}'>{$contact_name} </a> has added invalid postal code <br/>";
    $mailBody .= "</body></html>";

    $mailParams = [
      'groupName' => 'empower notification',
      'from' => '"' . $domainEmailName . '" <' . $domainEmailAddress . '>',
      'subject' => 'Invalid Postal Code Added for contact :',
      'text' => $mailBody,
      'html' => $mailBody,
    ];
    $mailParams['toName'] = $emailTo;
    $mailParams['toEmail'] = $emailTo;
    CRM_Utils_Mail::send($mailParams);
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

  /**
   * Provides a ucfirst implementation with fixes for utf-8
   * and a few special use-cases.
   */
  function ucFirst($string) {
    // https://github.com/cividesk/com.cividesk.normalize/issues/5
    $string = str_replace("’", "'", $string);

    // Use mb_convert_case otherwise strings such as MONTRÉAL end up as MontrÉal
    $string = mb_convert_case(mb_strtolower($string), MB_CASE_TITLE, "UTF-8");

    // Fix strings such as O'Connor or L'Ancienne-Lorette (city)
    // or names such as McEachran (street name in Montreal).
    // We start by a quick check, to avoid doing the split/implode otherwise.
    if (strpos($string, "'") !== FALSE || strpos($string, 'Mc')) {
      $parts = explode(' ', $string);

      foreach ($parts as &$part) {
        if (!empty($part[1]) && $part[1] == "'") {
          // O'connor -> O'Connor
          $t = mb_strtoupper($part[2]);
          $part[2] = $t;
        }
        elseif (!empty($part[2]) && substr($part, 0, 2) == 'Mc') {
          // Mceachran -> McEachran
          $t = mb_strtoupper($part[2]);
          $part[2] = $t;
        }
      }

      $string = implode(' ', $parts);
    }

    return $string;
  }

  static function processNormalization($fromContactId, $toContactId) {
    $processInfo = ['name' => 0, 'phone' => 0, 'address' => 0];
    if (empty($fromContactId) || empty($toContactId)) {
      return $processInfo;
    }

    $contactIds = range($fromContactId, $toContactId);

    $normalization = CRM_Utils_Normalize::singleton();

    $formattedContactIds = $formattedPhoneIds = $formattedAddressIds = [];
    foreach ($contactIds as $contactId) {
      $contact = new CRM_Contact_DAO_Contact();
      $contact->id = $contactId;
      if ($contact->find()) {
        $params = ['id' => $contactId, 'contact_id' => $contactId];
        $orgContactValues = [];
        CRM_Contact_BAO_Contact::retrieve($params, $orgContactValues);
        //update contacts name fields.
        $formatNameValues = [];
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

    $processInfo = [
      'name' => $formattedContactIds,
      'phone' => $formattedPhoneIds,
      'address' => $formattedAddressIds
    ];

    return $processInfo;
  }
}
