# vtt-parser

## Features

- Load and parse VTT files
- Extract timestamp information
- Validate subtitle structure
- Handle malformed files with error tracking
- Lightweight and dependency-free

## Installation

### Composer

```bash
composer require webrium/vtt-parser
```

## Usage
### Basic Parsing
```PHP
<?php

use Vtt\TextParser;

$parser = new TextParser();
$loaded = $parser->openFile('/path/to/files', 'subtitles.vtt');

if ($loaded) {
    $subtitles = $parser->parse();
    
    foreach ($subtitles as $subtitle) {
        echo "Time: {$subtitle['time']['start']} - {$subtitle['time']['end']}\n";
        echo "Text: " . implode("\n", $subtitle['text']) . "\n\n";
    }
}
```

### Validation

```PHP
$validation = $parser->textValidation();

if ($validation['ok']) {
    echo "VTT file is valid";
} else {
    echo "Found errors at lines: " . implode(', ', $validation['errors']);
}
```

## API Reference
openFile(string $file_path, string $file_name): bool

Loads a VTT file for parsing.

Parameters:

    $file_path: Directory path

    $file_name: Filename

Returns: true if file was successfully loaded
parse(): array

Parses the loaded VTT file.

Returns: Array of subtitle blocks with structure:

```
[
    [
        'time' => [
            'start' => 'HH:MM:SS',
            'end' => 'HH:MM:SS'
        ],
        'text' => [
            'Subtitle line 1',
            'Subtitle line 2'
        ]
    ],
    // ...
]
```
textValidation(): array

Validates the VTT structure.

Returns: Validation result array:
```
[
    'ok' => bool,     // Overall validation status
    'errors' => array // Line numbers with errors
]
```

### Error Handling
The parser throws exceptions for:

    Missing start/end timestamps (InvalidArgumentException)

    File not found (when using openFile)

Always wrap calls in try-catch blocks:
```PHP
try {
    $parser->openFile('path', 'file.vtt');
    $subtitles = $parser->parse();
} catch (\InvalidArgumentException $e) {
    echo "Error: " . $e->getMessage();
}
```

# Example VTT File
```
WEBVTT

00:00:01.000 --> 00:00:04.000
Hello world!

00:00:05.000 --> 00:00:08.000
This is a subtitle
Second line
```

## Output Structure
```
[
    [
        'time' => [
            'start' => '00:00:01',
            'end' => '00:00:04'
        ],
        'text' => ['Hello world!']
    ],
    [
        'time' => [
            'start' => '00:00:05',
            'end' => '00:00:08'
        ],
        'text' => ['This is a subtitle', 'Second line']
    ]
]
```
