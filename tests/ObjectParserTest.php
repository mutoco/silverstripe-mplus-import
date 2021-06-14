<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\TreeNode;
use SilverStripe\Dev\FunctionalTest;

class ObjectParserTest extends FunctionalTest
{
    public function testObjectParser()
    {
        $parser = new Parser();
        /** @var TreeNode $objectResult */
        $result = $parser->parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'parserTest.xml');

        $this->assertNotNull($result, 'Parser should return a tree');
        $this->assertEquals('123', $result->getNestedNode('Object.__id')->getValue());
        $this->assertEquals('Clob', $result->getNestedNode('Object.ObjBriefDescriptionGrp.DescriptionClb')->dataType);
        $this->assertEquals('Varchar', $result->getNestedNode('Object.ObjAcquisitionYearTxt')->dataType);
        $this->assertEquals('repeatableGroup', $result->getNestedNode('Object.ObjBriefDescriptionGrp')->getTag());
        $this->assertCount(2, $result->getNestedNode('Object.ObjBriefDescriptionGrp')->getChildren());
        $this->assertCount(1, $result->getNodesMatchingPath('Object.ObjBriefDescriptionGrp'));
    }

    public function testAllowedPaths()
    {
        $parser = new Parser();
        $parser->setAllowedPaths([
            'Object.__id',
            'Object.ObjBriefDescriptionGrp.DescriptionClb'
        ]);

        $this->assertTrue($parser->isAllowedPath('Object'));
        $this->assertTrue($parser->isAllowedPath('Object.__id'));
        $this->assertTrue($parser->isAllowedPath(['Object','__id']));
        $this->assertTrue($parser->isAllowedPath('Object.ObjBriefDescriptionGrp'));
        $this->assertTrue($parser->isAllowedPath('Object.ObjBriefDescriptionGrp.DescriptionClb'));
        $this->assertFalse($parser->isAllowedPath('Object.ObjBriefDescriptionGrp.DescriptionClb.Foo'));
        $this->assertFalse($parser->isAllowedPath('Object.DescriptionClb'));
        $this->assertTrue($parser->isAllowedNext('Object'));
        $this->assertFalse($parser->isAllowedNext('__id'));

        /** @var TreeNode $objectResult */
        $result = $parser->parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'parserTest.xml');
        $this->assertEquals('123', $result->getNestedNode('Object.__id')->getValue());
        $this->assertEquals('repeatableGroup', $result->getNestedNode('Object.ObjBriefDescriptionGrp')->getTag());
        $this->assertNull($result->getNestedNode('Object.ObjAcquisitionYearTxt'));
    }

}