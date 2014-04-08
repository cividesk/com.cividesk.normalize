<?php
return array(
  'intlNumberFormat' =>  // Abbreviated because only one format is used by the Google library
  array (
    array (
      'pattern' => '(\\d+)',
      'format' => '(0) $1',
      'leadingDigitsPatterns' =>
      array (
      ),
    ),
  ),
);

/*
return array(
  'intlNumberFormat' => // cf. http://en.wikipedia.org/wiki/Telephone_numbers_in_Germany
  array (
    0 =>
    array (
      'pattern' => '(\\d{2})(\\d{8})',
      'format' => '(0)$1 $2',
      'leadingDigitsPatterns' =>
      array (
      ),
    ),
    1 =>
    array (
      'pattern' => '(\\d{3})(\\d{8})',
      'format' => '(0)$1 $2',
      'leadingDigitsPatterns' =>
      array (
      ),
    ),
  ),
);
*/
