<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Parse\Node\FieldParser;
use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\ResultInterface;
use SilverStripe\Dev\FunctionalTest;

class ParserTest extends FunctionalTest
{
    public function testParse()
    {
        $parser = new Parser();
        $fields = new FieldParser('dataField');
        $fields->on('parse:complete', function (ResultInterface $result) {
            echo $result->getValue() . PHP_EOL;
        });
        $parser->pushStack($fields);
        $parser->parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'Object-47894.xml');
    }
}
