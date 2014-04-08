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
  'intlNumberFormat' =>  // cf. http://en.wikipedia.org/wiki/Telephone_numbers_in_Belgium
  array (
    array (
      'pattern' => '([2-49])(\\d{3})(\\d{2})(\\d{2})',
      'format' => '(0)$1 $2 $3 $4',
      'leadingDigitsPatterns' =>
      array (
      ),
    ),
    array (
      'pattern' => '(\\d{2})(\\d{2})(\\d{2})(\\d{2})',
      'format' => '(0)$1 $2 $3 $4',
      'leadingDigitsPatterns' =>
      array (
      ),
    ),
    array (
      'pattern' => '(4\\d{2})(\\d{2})(\\d{2})(\\d{2})',
      'format' => '(0)$1 $2 $3 $4',
      'leadingDigitsPatterns' =>
      array (
      ),
    ),
  ),
);
*/
