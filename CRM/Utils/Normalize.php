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

use com\google\i18n\phonenumbers\PhoneNumberUtil;
use com\google\i18n\phonenumbers\PhoneNumberFormat;
use com\google\i18n\phonenumbers\NumberParseException;
require_once 'libphonenumber/PhoneNumberUtil.php';

class CRM_Utils_Normalize {
  CONST NORMALIZE_PREFERENCES_NAME = 'Normalize Preferences';

  private static $_singleton = NULL;

  private $_settings;
  private $_country;

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
  }

  /**
   * Returns normalizer settings
   */
  static function getSettings() {
    return CRM_Core_BAO_Setting::getItem(CRM_Utils_Normalize::NORMALIZE_PREFERENCES_NAME);
  }

  function setSetting($value, $name) {
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
  function normalize_contact( &$contact ) {
    $handles = array(
      'de', 'des', // France
      'da', 'den', 'der', 'ten', 'ter', 'van', // Neederlands
      'von', // Germany
      'ben', // Marocco
      'et', 'and', 'und', // For company names
    );
    $orgstatus = array(
      'llc', 'ltd', 'inc', 'co', // USA
      'sc', 'sci', 'sarl', // France
      'fze', 'fz', 'fz-llc', 'fz-co', 'rak', // UAE
      'usa', 'uae',
    );

    if (CRM_Utils_Array::value('contact_FullFirst', $this->_settings)) {
      foreach (array('first_name','middle_name','last_name','organization_name') as $field) {
        $name = CRM_Utils_Array::value($field, $contact);
        if (empty($name)) continue;
        $words = explode(' ', str_replace('-', '- ', $name));
        foreach ($words as $i => $word) {
          // Preserve accronyms in organization names
          if (CRM_Utils_Array::value('contact_type', $contact) != 'Organization')
            $word = strtolower($word);
          // Preserve words that are always lowercase
          if (in_array($word, $handles))
            continue;
          // Capitalize organization statuses
          if ((CRM_Utils_Array::value('contact_type', $contact) == 'Organization')
            && in_array(str_replace(array('.'), '', $word), $orgstatus))
            $word = strtoupper($word);
          // Capitalize any character before a dot
          $pos = 0;
          while (($pos+1 < strlen($word)) && ($pos = strpos($word, '.', $pos+1)))
            $word{$pos-1} = strtoupper($word{$pos-1});
          // Finally, capitalize the first letter of word
          $words[$i] = ucfirst($word);
        }
        $contact[$field] = str_replace('- ', '-', implode(' ', $words));
      }
    }
    if (CRM_Utils_Array::value('contact_OrgCaps',$this->_settings)) {
      if ((CRM_Utils_Array::value('contact_type', $contact) == 'Organization')
        && CRM_Utils_Array::value('organization_name', $contact)) {
        $contact['organization_name'] = strtoupper($contact['organization_name']);
      }
    }
    return true;
  }

  /**
   * Normalizes a phone number
   * @param $phone
   *   Name that needs to be reformatted
   */

  function normalize_phone( &$phone ) {
    $input = $phone['phone'];
    if (empty($input)) return false; // Should not be empty

    $country = ($this->_country ? $this->_country : 'US');
    $phoneUtil = PhoneNumberUtil::getInstance();
    try {
      $phoneProto = $phoneUtil->parse($input, $country);
    } catch (NumberParseException $e) {
      return false;
    }
    if (!$phoneUtil->isValidNumber($phoneProto))
      return false;
    if (CRM_Utils_Array::value('phone_IntlPrefix', $this->_settings)) // always give the international format
      $phone['phone'] = $phoneUtil->format($phoneProto, PhoneNumberFormat::INTERNATIONAL);
    elseif (CRM_Utils_Array::value('phone_normalize', $this->_settings))
      // in-country for local, international for all others
      if ($phoneProto->getCountryCode() == $phoneUtil->getCountryCodeForRegion($country))
        $phone['phone'] = $phoneUtil->format($phoneProto, PhoneNumberFormat::NATIONAL);
      else
        $phone['phone'] = $phoneUtil->format($phoneProto, PhoneNumberFormat::INTERNATIONAL);
    return true;
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
   * @param $this->_settings
   *   Options for the conversion
   */

  function normalize_address( &$address ) {
    $zip_formats = array(
      'US' => '/^(\d{5})(-[0-9]{4})?$/i',
      'FR' => '/^(\d{5})$/i',
      'NL' => '/^(\d{4})\s*([a-z]{2})$/i',
    );

    if (CRM_Utils_Array::value('address_CityCaps',$this->_settings))
      $address['city'] = strtoupper($address['city']);

    if (CRM_Utils_Array::value('address_Zip',$this->_settings)) {
      if ($cid = CRM_Utils_Array::value('country_id', $address)) {
        $codes = CRM_Core_PseudoConstant::countryIsoCode();
        if ($regex = CRM_Utils_Array::value($codes[$cid], $zip_formats)) {
          if (!preg_match($regex, CRM_Utils_Array::value('postal_code', $address),$matches)) {
            $address['postal_code'] = '?? ' . $address['postal_code'];
          }
        }
      }
    }

    return true;
  }
}