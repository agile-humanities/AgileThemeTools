<?php

namespace AgileThemeTools\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use NumericDataTypes\DataType\AbstractDateTimeDataType;
use Omeka\Api\Representation\ValueRepresentation;

class DateHandler extends AbstractHelper
{
  /**
   * Properties whose literal values may hold dates. dcterms:temporal is deliberately absent: in this data it holds
   * period names ("Eighteenth century"), not dates.
   */
  const DATE_TERMS = [
    'dcterms:date',
    'dcterms:created',
    'dcterms:issued',
    'dcterms:available',
    'dcterms:dateAccepted',
    'dcterms:dateCopyrighted',
    'dcterms:dateSubmitted',
    'dcterms:modified',
    'dcterms:valid',
  ];

  /**
   * Data types that are always dates. NumericDataTypes formats them for the locale.
   */
  const DATE_TYPES = ['numeric:timestamp', 'numeric:interval'];

  /**
   * An ISO 8601 date at year, month or day precision, e.g. "1946", "1946-12", "1946-12-29".
   */
  const ISO_DATE_PATTERN = '/^-?\d{4}(-\d{2}){0,2}$/';

  /**
   * A year range entered as text, e.g. "1504-1514" or "1504/1514".
   */
  const YEAR_RANGE_PATTERN = '~^(\d{4})\s*[-/–]\s*(\d{4})$~u';

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
   * Returns display text for a date value.
   *
   * Timestamps and intervals use NumericDataTypes' locale formatting ("1741", "December 1946", "1825 – 1875").
   * ISO dates entered as text get the same formatting, and text year ranges get the interval's dash. Anything
   * else is returned unchanged.
   */
  public function renderValue(ValueRepresentation $value): string {
    if (in_array($value->type(), self::DATE_TYPES, true)) {
      return strip_tags($value->asHtml());
    }

    $date = trim((string) $value->value());

    if (preg_match(self::YEAR_RANGE_PATTERN, $date, $matches)) {
      return sprintf('%s – %s', $matches[1], $matches[2]);
    }

    if (preg_match(self::ISO_DATE_PATTERN, $date) && class_exists(AbstractDateTimeDataType::class)) {
      try {
        return AbstractDateTimeDataType::getFormattedDateTimeFromValue($date, true, ['lang' => $this->getView()->lang()]);
      } catch (\Exception $e) {
        return $date;
      }
    }

    return $date;
  }

  /**
   * @param $valueRepresentation
   * @return bool
   *
   * Whether a value is a date: a NumericDataTypes timestamp or interval, or a literal date property (see
   * DATE_TERMS) whose text is an ISO date or a year range. Free text in a date property is not a date.
   */
  public function isDate($valueRepresentation) {

    if (!$valueRepresentation instanceof ValueRepresentation) {
      return false;
    }

    if (in_array($valueRepresentation->type(), self::DATE_TYPES, true)) {
      return true;
    }

    if (!in_array($valueRepresentation->property()->term(), self::DATE_TERMS, true)) {
      return false;
    }

    $date = trim((string) $valueRepresentation->value());

    return preg_match(self::ISO_DATE_PATTERN, $date) === 1 || preg_match(self::YEAR_RANGE_PATTERN, $date) === 1;
  }

}
