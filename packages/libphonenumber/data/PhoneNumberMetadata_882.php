<?php
/**
 * This file is automatically @generated by {@link BuildMetadataPHPFromXml}.
 * Please don't modify it directly.
 */


return array (
  'generalDesc' => 
  array (
    'NationalNumberPattern' => '[13]\\d{6,11}',
    'PossibleNumberPattern' => '\\d{7,12}',
    'ExampleNumber' => '3451234567',
  ),
  'fixedLine' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
    'ExampleNumber' => '3451234567',
  ),
  'mobile' => 
  array (
    'NationalNumberPattern' => '
          3(?:
            2\\d{3}|
            37\\d{2}|
            4(?:
              2|
              7\\d{3}
            )
          )\\d{4}
        ',
    'PossibleNumberPattern' => '\\d{7,10}',
    'ExampleNumber' => '3451234567',
  ),
  'tollFree' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'premiumRate' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'sharedCost' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'personalNumber' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'voip' => 
  array (
    'NationalNumberPattern' => '
          1(?:
            3(?:
              0[0347]|
              [13][0139]|
              2[035]|
              4[013568]|
              6[0459]|
              7[06]|
              8[15678]|
              9[0689]
            )\\d{4}|
            6\\d{5,10}
          )|
          345\\d{7}
        ',
    'PossibleNumberPattern' => '\\d{7,12}',
    'ExampleNumber' => '3451234567',
  ),
  'pager' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'uan' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'emergency' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'voicemail' => 
  array (
    'NationalNumberPattern' => '348[57]\\d{7}',
    'PossibleNumberPattern' => '\\d{11}',
    'ExampleNumber' => '3451234567',
  ),
  'shortCode' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'standardRate' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'carrierSpecific' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'noInternationalDialling' => 
  array (
    'NationalNumberPattern' => 'NA',
    'PossibleNumberPattern' => 'NA',
  ),
  'id' => '001',
  'countryCode' => 882,
  'internationalPrefix' => '',
  'sameMobileAndFixedLinePattern' => false,
  'numberFormat' => 
  array (
    0 => 
    array (
      'pattern' => '(\\d{2})(\\d{4})(\\d{3})',
      'format' => '$1 $2 $3',
      'leadingDigitsPatterns' => 
      array (
        0 => '3[23]',
      ),
      'nationalPrefixFormattingRule' => '',
      'domesticCarrierCodeFormattingRule' => '',
    ),
    1 => 
    array (
      'pattern' => '(\\d{2})(\\d{5})',
      'format' => '$1 $2',
      'leadingDigitsPatterns' => 
      array (
        0 => '
            16|
            342
          ',
      ),
      'nationalPrefixFormattingRule' => '',
      'domesticCarrierCodeFormattingRule' => '',
    ),
    2 => 
    array (
      'pattern' => '(\\d{2})(\\d{4})(\\d{4})',
      'format' => '$1 $2 $3',
      'leadingDigitsPatterns' => 
      array (
        0 => '34[57]',
      ),
      'nationalPrefixFormattingRule' => '',
      'domesticCarrierCodeFormattingRule' => '',
    ),
    3 => 
    array (
      'pattern' => '(\\d{3})(\\d{4})(\\d{4})',
      'format' => '$1 $2 $3',
      'leadingDigitsPatterns' => 
      array (
        0 => '348',
      ),
      'nationalPrefixFormattingRule' => '',
      'domesticCarrierCodeFormattingRule' => '',
    ),
    4 => 
    array (
      'pattern' => '(\\d{2})(\\d{2})(\\d{4})',
      'format' => '$1 $2 $3',
      'leadingDigitsPatterns' => 
      array (
        0 => '1',
      ),
      'nationalPrefixFormattingRule' => '',
      'domesticCarrierCodeFormattingRule' => '',
    ),
    5 => 
    array (
      'pattern' => '(\\d{2})(\\d{3,4})(\\d{4})',
      'format' => '$1 $2 $3',
      'leadingDigitsPatterns' => 
      array (
        0 => '16',
      ),
      'nationalPrefixFormattingRule' => '',
      'domesticCarrierCodeFormattingRule' => '',
    ),
    6 => 
    array (
      'pattern' => '(\\d{2})(\\d{4,5})(\\d{5})',
      'format' => '$1 $2 $3',
      'leadingDigitsPatterns' => 
      array (
        0 => '16',
      ),
      'nationalPrefixFormattingRule' => '',
      'domesticCarrierCodeFormattingRule' => '',
    ),
  ),
  'intlNumberFormat' => 
  array (
  ),
  'mainCountryForCode' => false,
  'leadingZeroPossible' => false,
  'mobileNumberPortableRegion' => false,
);
/* EOF */