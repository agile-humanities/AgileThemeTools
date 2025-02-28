<?php

namespace AgileThemeTools\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Omeka\Api\Representation\ValueRepresentation;

class DateHandler extends AbstractHelper
{
  /**
   * A stub function to process numeric dates. There must be a more elegant way to handle unknown date formats
   */
  public function __invoke() {

  }

  public function render($date=null,$output_format='F j, Y', $input_format='') {

    if (!$date) {
      return $date; // Return the original value or null if none specified
    }

    if ($input_format != '') {

      $date = str_replace('-', '', $date);
      $input_format = str_replace('-', '', $date);

      switch ($input_format) {
        case 'DDMMYYYY':
          $day = substr($date, 0, 2);
          $month = substr($date, 2, 2);
          $year = substr($date, 4, 4);
          break;
        case 'MMDDYYYY':
          $month = substr($date, 0, 2);
          $day = substr($date, 2, 2);
          $year = substr($date, 4, 4);
          break;
        case 'YYYYMM':
          $day = '01';
          $month = substr($date, 4, 2);
          $year = substr($date, 0, 4);
          break;
        case 'YYYYMMDD':
        default:
          $day = substr($date, 6, 2);
          $month = substr($date, 4, 2);
          $year = substr($date, 0, 4);
          break;

      }

      $date = implode('-', [$year, $month, $day]);
    }

    return date($output_format,strtotime($date));
  }

  /**
   * @param $valueRepresentation
   * @return bool
   *
   * Fuzzy logic function which takes a value representation and tries to discern if it's a date. It does not
   * check if the date is well formed.
   */
  public function isDate($valueRepresentation) {

    // Only handles value representations for now.
    if (!$valueRepresentation instanceof ValueRepresentation) {
      return false;
    }

    // Check to see if the data type is a timestamp (via Numeric Data Types module)

    if ($valueRepresentation->type() === 'timestamp') {
      return true;
    }



    // List of date terms

    $dateTerms = [
      'dcterms:temporal',
      'dcterms:dateSubmitted'
    ];

    // Check if term is in list above

    if (method_exists($valueRepresentation,'property')) {
      return in_array($valueRepresentation->property()->term(),$dateTerms);
    }

    // Return false by default

    return false;
  }

}
