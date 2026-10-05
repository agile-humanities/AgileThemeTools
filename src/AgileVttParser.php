<?php
namespace AgileThemeTools;


/**
 * A parser for VTT (WebVTT) subtitle files
 * Handles file loading, parsing, and validation
 *
 * Adapted from the webrium/vtt-parser library, with thanks.
 */
class AgileVttParser
{
  // File properties
  public $file = '';
  public $path = '';
  private string $content = '';

  // State flags
  public bool $ready = false;
  public bool $init = false;
  public bool $validation_text = true;

  // Error tracking
  private array $errors = [];

  /**
   * Opens and loads a VTT file
   *
   * @param string $path Full path contents, suitable to be passed to file_get_contents()
   * @return bool Returns true if file was successfully loaded
   */
  public function getFileContents($path)
  {
    $file_headers = @get_headers($path );

    if (file_exists($path) || stripos($file_headers[0],'404') === false) {
      $this->content = file_get_contents($path);
      $this->ready = true;
      return true;
    }

    $this->ready = false;
    return false;
  }

  /**
   * Opens and loads a VTT file
   *
   * @param string $file_path Directory path of the file
   * @param string $file_name Name of the file
   * @return bool Returns true if file was successfully loaded
   */
  public function openFile($file_path, $file_name)
  {
    $this->path = $file_path;
    $this->file = $file_name;
    $this->full_path = "$file_path/$file_name";

    if (file_exists($this->full_path)) {
      $this->content = file_get_contents($this->full_path);
      $this->ready = true;
      return true;
    }

    $this->ready = false;
    return false;
  }

  /**
   * Parses the loaded VTT file content
   *
   * @return array Structured array of subtitle entries with timestamps and text
   */
  public function parse()
  {
    if (!$this->ready) {
      return [];
    }
    $lines = explode("\n", trim($this->content));
    $line_start_content = false;
    $start_first_line = false;
    $array = [];
    $ob = ['text' => [], 'time' => ''];

    foreach ($lines as $key => $line) {
      $text = trim($line);

      // Check for timestamp line
      if (strpos($text, '-->') !== false) {
        if ($start_first_line == false) {
          $start_first_line = true;
        }

        if ($line_start_content == false) {
          $line_start_content = true;
          $time = $this->extractTimestamps($text);
          $ob['time'] = $time;
        }
      }
      // Handle subtitle text content
      else if ($line_start_content == true) {
        if (empty($text)) {
          $line_start_content = false;
          $array[] = $ob;
          $ob = ['text' => [], 'time' => ''];
        } else {
          $ob['text'][] = $text;
        }
      }
      // Track validation errors
      else if ($line_start_content == false && empty($text) && $start_first_line == true) {
        $this->errors[] = $key;
        $this->validation_text = false;
      }
    }

    if ($start_first_line) {
      $array[] = $ob;
    }

    $this->init = true;
    return $array;
  }

  /**
   * Extracts start and end timestamps from a VTT timestamp string
   *
   * @param string $vttTimestampString The timestamp line from VTT file
   * @return array|null Array with start/end times or null if no match
   * @throws \InvalidArgumentException If timestamps are invalid
   */
  protected function extractTimestamps($vttTimestampString) {
    $timePattern = '/([\d{2}:]?\d{2}:\d{2}.\d{3})/';

    if (preg_match_all($timePattern, $vttTimestampString, $matches)) {
      if (!isset($matches[1][0]) || empty($matches[1][0])) {
        throw new \InvalidArgumentException(
          "Start time not found in timestamp string($vttTimestampString)\nFile: ".$this->full_path
        );
      }

      if (!isset($matches[1][1]) || empty($matches[1][1])) {
        throw new \InvalidArgumentException(
          "End time not found in timestamp string($vttTimestampString)\nFile: ".$this->full_path
        );
      }

      $start = $this->formatTime($matches[1][0]);
      $end = $this->formatTime($matches[1][1]);

      return [
        "start" => $start,
        "end" => $end,
        "timestamp" => $start .' --> '. $end,
        "start_cue" => $this->convertToMicroseconds($matches[1][0]),
        "end_cue" => $this->convertToMicroseconds($matches[1][1])
      ];
    }

    return null;
  }

  /**
   * Formats a time string to HH:MM:SS format
   *
   * @param string $timeString Time string in VTT format
   * @return string Formatted time string
   */
  protected function formatTime($timeString) {
    $timeComponents = $this->getTimeComponents($timeString);
    return sprintf('%02d:%02d:%02d', $timeComponents[0], $timeComponents[1], $timeComponents[2]);
  }

  public function convertToMicroseconds($timeString) {
    $timeComponents = $this->getTimeComponents($timeString);
    return (($timeComponents[0] * 60 * 60 * 1000) + ($timeComponents[1] * 60 * 1000) + ($timeComponents[2] * 1000));
  }

  /*
   * Converts a VTT timestring into an array of components (hours, minutes, seconds).
   * A valid VTT time marker (start and end) looks like this: 00:00:00.260 --> 00:00:01.560.
   * @param $timeString – A VTT time marker (either start or end)
   * @returns  ['hh','mm','ss']
   *
   * Hours are optional in the VTT specification.
   */

  private function getTimeComponents($timeString): array {
    $timeComponents = explode(':', $timeString);

    // Add hours if missing
    if(count($timeComponents) === 2) {
      array_unshift($timeComponents,'00');
    }

    // Some VTT transcripts have the format :mm:ss.sss (missing the hour but with preceding colon)
    // In these instances the hour component returns an empty string and needs to be standardized

    if ($timeComponents[0] === '') {
      $timeComponents[0] = '00';
    }

    return $timeComponents;

  }

  /**
   * Validates the parsed VTT content
   *
   * @return array Validation result with status and error information
   */
  public function textValidation()
  {
    if ($this->init == false) {
      $this->parse();
    }

    return [
      'ok' => $this->validation_text,
      'errors' => $this->errors
    ];
  }
}
