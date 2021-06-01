<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Parse\Node\ModuleParser;
use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\ModuleResult;
use SilverStripe\Dev\FunctionalTest;

class ParserTest extends FunctionalTest
{
    public function testModuleParser()
    {
        $parser = new Parser();
        $fields = new ModuleParser('Object');
        /** @var ModuleResult $moduleResult */
        $fields->on('parse:result', function (ModuleResult $result) use (&$moduleResult) {
            $moduleResult = $result;
        });
        $parser->pushStack($fields);
        $parser->parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'parserTest.xml');

        $this->assertNotNull($moduleResult, 'Module result should be set');
        $this->assertEquals('123', $moduleResult->getId());
        $this->assertEquals('Object', $moduleResult->getType());
        $this->assertCount(4, $moduleResult->getFields());
        $this->assertEquals(
            ['ID' => '123', 'HASATTACHMENTS' => 'true', 'UUID' => '2624c885-af51-4da6-a91c-b0233a8ffef9'],
            $moduleResult->getAttributes()
        );

        $fieldValues = [];
        foreach ($moduleResult->getFields() as $field) {
            $fieldValues[$field->getName()] = $field->getValue();
        }
        $this->assertEquals([
            '__id' => '123',
            'ObjAcquisitionYearTxt' => '1916',
            'ObjCategoryVoc' => 'Druckgrafik',
            'ObjTitleVrt' => 'Testdatensatz Portrait . Hummer'
        ], $fieldValues);
    }

    public function testModuleParserFiltered()
    {
        $parser = new Parser();
        $fields = new ModuleParser('Object');
        $fields->setFieldList(['ObjCategoryVoc']);
        /** @var ModuleResult $moduleResult */
        $fields->on('parse:result', function (ModuleResult $result) use (&$moduleResult) {
            $moduleResult = $result;
        });
        $parser->pushStack($fields);
        $parser->parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'parserTest.xml');

        $this->assertNotNull($moduleResult, 'Module result should be set');
        $this->assertEquals('123', $moduleResult->getId());
        $this->assertCount(1, $moduleResult->getFields());
        $this->assertEquals('ObjCategoryVoc', $moduleResult->getFields()[0]->getName());
    }
}
