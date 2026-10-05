<?php
namespace Vtt;

/**
 * A parser for VTT (WebVTT) subtitle files
 * Handles file loading, parsing, and validation
 */
class TextParser
{
    // File properties
    public $file = '';
    public $path = '';
    private $full_path = '';
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
      var_dump($this->content);

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
    private function extractTimestamps($vttTimestampString) {
        $timePattern = '/(\d{2}:\d{2}:\d{2}.\d{3})/';

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

            return [
                "start" => $this->formatTime($matches[1][0]),
                "end" => $this->formatTime($matches[1][1])
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
    private function formatTime($timeString) {
        $timeComponents = explode(':', $timeString);
        return sprintf('%02d:%02d:%02d', $timeComponents[0], $timeComponents[1], $timeComponents[2]);
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
