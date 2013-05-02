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

class CRM_Core_Cividesk {

  /*
   * Registers the extension user with Cividesk
   */
  static function register($extension) {
    if ($domain_id = CRM_Core_Config::domainID()) {
      // Gather information from domain settings
      $params = array('id' => $domain_id);
      CRM_Core_BAO_Domain::retrieve($params, $domain);
      unset($params['id']);
      $locParams = $params + array('entity_id' => $domain_id, 'entity_table' => 'civicrm_domain');
      $defaults = CRM_Core_BAO_Location::getValues($locParams);
      foreach (array('address', 'phone', 'email') as $info) {
        $domain[$info] = reset(CRM_Utils_Array::value($info, $defaults));
      }

      // Create registration parameters
      $registration = array(
        'extension' => $extension,
        'organization_name' => $domain['name'],
        'description' => $domain['description'] );
      foreach (array('street_address', 'supplemental_address_1', 'supplemental_address_2', 'city', 'postal_code', 'state_province_id', 'country_id') as $field)
        $registration[$field] = CRM_Utils_Array::value($field, $domain['address']);
      $registration['phone'] = $domain['phone']['phone'];
      $registration['email'] = $domain['email']['email'];

      return self::_rest_helper('http://my.cividesk.com/register.php', $registration, 'POST');
    }
  return false;
  }

  static function subscribe($list, $email) {
    $params = array('list' => $list, 'email' => $email);
    return self::_rest_helper('http://my.cividesk.com/subscribe.php', $params);
  }

  /*
   * Calls a REST function - without external dependencies (i.e. does not use curl)
   * from: http://wezfurlong.org/blog/2006/nov/http-post-from-php-without-curl/
   */
  static function _rest_helper($url, $params = null, $verb = 'GET', $format = 'json') {
    $cparams = array(
      'http' => array(
        'method' => $verb,
        'ignore_errors' => true
      )
    );
    if ($params !== null) {
      $params = http_build_query($params);
      if ($verb == 'POST') {
        $cparams['http']['content'] = $params;
      } else {
        $url .= '?' . $params;
      }
    }

    $context = stream_context_create($cparams);
    $fp = fopen($url, 'rb', false, $context);
    if (!$fp) {
      return false;
    } else {
      // If you're trying to troubleshoot problems, try uncommenting the
      // next two lines; it will show you the HTTP response headers across
      // all the redirects:
      // $meta = stream_get_meta_data($fp);
      // var_dump($meta['wrapper_data']);
      $res = stream_get_contents($fp);
    }

    switch ($format) {
      case 'json':
        $r = json_decode($res);
        if ($r === null) return false;
        return $r;

      case 'xml':
        $r = simplexml_load_string($res);
        if ($r === null) return false;
        return $r;
      }
    return false;
  }
}