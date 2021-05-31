<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Parse\Node\FieldParser;
use Mutoco\Mplus\Parse\Node\ModuleParser;
use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\ModuleResult;
use Mutoco\Mplus\Parse\Result\ResultInterface;
use SilverStripe\Dev\FunctionalTest;

class ParserTest extends FunctionalTest
{
    public function testParse()
    {
        $parser = new Parser();
        $fields = new ModuleParser('Object');
        $fields->on('parse:result', function (ModuleResult $result) {
            echo $result->getId();
            foreach ($result->fields as $field){
                echo PHP_EOL;
                echo (string)$field;
            }
            echo PHP_EOL;
        });
        $parser->pushStack($fields);
        $parser->parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'Object-47894.xml');
    }
}
