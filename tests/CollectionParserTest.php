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
        $this->assertEquals('Object', $collectionResult->getName());
        $this->assertInstanceOf(ObjectResult::class, $collectionResult->getItems()[0]);
    }

    public function testSubCollection()
    {
        $moduleParser = new ObjectParser();
        $moduleParser->setRelationParser('ObjMultimediaRef', new CollectionParser('moduleReference', new ObjectParser('moduleReferenceItem')));
        $collectionParser = new CollectionParser('module', $moduleParser);
        $parser = new Parser();
        /** @var CollectionResult $collectionResult */
        $collectionResult = $parser->parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'parserTest.xml', $collectionParser);
        $this->assertNotNull($collectionResult, 'Collection result should be set');
        $this->assertEquals(1, $collectionResult->count());

        $moduleResult = $collectionResult->getItems()[0];
        $this->assertInstanceOf(CollectionResult::class, $moduleResult->getRelationResult('ObjMultimediaRef'));
        $this->assertCount(7, $moduleResult->ObjMultimediaRef);
        $this->assertEquals('Multimedia', $moduleResult->getRelationResult('ObjMultimediaRef')->targetModule);
    }

    public function testCollectionResult()
    {
        $collectionParser = new CollectionParser('module', new ObjectParser());

        $parser = new Parser();
        /** @var CollectionResult $collectionResult */
        $collectionResult = $parser->parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'parserTest.xml', $collectionParser);

        $this->assertEquals($collectionResult->getItems(), $collectionResult->getValue());
        $this->assertEquals('module', $collectionResult->getTag());
    }

    public function testCollectionResultSerialize()
    {
        $result = new CollectionResult('tag', ['name' => 'Module']);
        $result->addItem(new ObjectResult('objTag', ['id' => '123']));
        $result->addItem(new ObjectResult('objTag', ['id' => '321']));

        /** @var CollectionResult $copy */
        $copy = unserialize(serialize($result));
        $this->assertCount(2, $copy->getItems());
        $this->assertInstanceOf(ObjectResult::class, $copy->getItems()[0]);
        $this->assertEquals('123', $copy->getItems()[0]->getId());
        $this->assertEquals('321', $copy->getItems()[1]->getId());
    }
}
