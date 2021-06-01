<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Parse\Node\CollectionParser;
use Mutoco\Mplus\Parse\Node\ObjectParser;
use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\CollectionResult;
use Mutoco\Mplus\Parse\Result\ObjectResult;
use SilverStripe\Dev\FunctionalTest;

class CollectionParserTest extends FunctionalTest
{
    public function testCollectionParser()
    {
        $collectionParser = new CollectionParser('module', new ObjectParser());

        $parser = new Parser();
        /** @var CollectionResult $collectionResult */
        $collectionResult = $parser->parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'parserTest.xml', $collectionParser);

        $this->assertNotNull($collectionResult, 'Collection result should be set');
        $this->assertEquals(1, $collectionResult->count());
        $this->assertInstanceOf(ObjectResult::class, $collectionResult->getItems()[0]);
    }
}
