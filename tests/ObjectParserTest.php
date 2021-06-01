<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Parse\Node\CollectionParser;
use Mutoco\Mplus\Parse\Node\ObjectParser;
use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\ObjectResult;
use SilverStripe\Dev\FunctionalTest;

class ObjectParserTest extends FunctionalTest
{
    public function testObjectParser()
    {
        $fields = new ObjectParser();

        $parser = new Parser();
        /** @var ObjectResult $objectResult */
        $objectResult = $parser->parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'parserTest.xml', $fields);

        $this->assertNotNull($objectResult, 'Object result should be set');
        $this->assertEquals('123', $objectResult->getId());
        $this->assertCount(4, $objectResult->getFields());
        $this->assertCount(0, $objectResult->getRelations());
        $this->assertEquals(
            ['ID' => '123', 'HASATTACHMENTS' => 'true', 'UUID' => '2624c885-af51-4da6-a91c-b0233a8ffef9'],
            $objectResult->getAttributes()
        );

        $fieldValues = [];
        foreach ($objectResult->getFields() as $field) {
            $fieldValues[$field->getName()] = $field->getValue();
        }
        $this->assertEquals([
            '__id' => '123',
            'ObjAcquisitionYearTxt' => '1916',
            'ObjCategoryVoc' => 'Druckgrafik',
            'ObjTitleVrt' => 'Testdatensatz Portrait . Hummer'
        ], $fieldValues);
    }

    public function testObjectParserFiltered()
    {
        $fields = new ObjectParser();
        $fields->setFieldList(['ObjCategoryVoc']);
        $fields->setRelationParser('ObjBriefDescriptionGrp', new CollectionParser('repeatableGroup', new ObjectParser('repeatableGroupItem')));
        $fields->setRelationParser('ObjMultimediaRef', new CollectionParser('moduleReference', new ObjectParser('moduleReferenceItem')));

        $parser = new Parser();
        /** @var ObjectResult $objectResult */
        $objectResult = $parser->parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'parserTest.xml', $fields);

        $this->assertNotNull($objectResult, 'Object result should be set');
        $this->assertEquals('123', $objectResult->getId());
        $this->assertCount(1, $objectResult->getFields());
        $this->assertCount(2, $objectResult->getRelations());
        $this->assertEquals('ObjCategoryVoc', $objectResult->getFields()[0]->getName());
        $this->assertEquals('repeatableGroup', $objectResult->getRelations()[0]->getTag());
        $this->assertCount(2, $objectResult->getRelations()[0]->getItems());
        $this->assertCount(7, $objectResult->getRelations()[1]->getItems());

        $ids = [];
        foreach ($objectResult->getRelations()[1]->getItems() as $item) {
            $ids[] = $item->getId();
        }
        $this->assertEquals(['115234', '307193', '904212', '904213', '904214', '904215', '907206'], $ids);
    }
}
