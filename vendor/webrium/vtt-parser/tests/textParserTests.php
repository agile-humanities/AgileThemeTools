<?php
// require_once __DIR__ . '/config.php';

use PHPUnit\Framework\TestCase;

use Vtt\TextParser;

class textParserTests extends TestCase
{

    public function testTextValidation(){


        $vtt = new TextParser;
        $vtt->openFile(__DIR__, 'test.vtt');
        $check = $vtt->textValidation();
        $this->assertTrue($check['ok']);


        $vtt = new TextParser;
        $vtt->openFile(__DIR__, 'incorect.vtt');
        $check = $vtt->textValidation();
        $this->assertFalse($check['ok']);
    }

    public function testTextParse(){
        $vtt = new TextParser;
        $vtt->openFile(__DIR__, 'test.vtt');
        $lines = $vtt->parse();

        $this->assertEquals($lines[count($lines)-1]['text'][0], 'FRANK!!!');
    }


}




