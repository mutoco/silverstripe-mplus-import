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
            'Object.ObjBriefDescriptionGrp.DescriptionClb',
            'Object.ObjMultimediaRef',
            'Object.ObjMultimediaRef.TypeVoc'
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
        $this->assertTrue($parser->isAllowedPath('Object.ObjMultimediaRef'));
        $this->assertFalse($parser->isAllowedPath('Object.ObjMultimediaRef.ThumbnailBoo'));
        $this->assertTrue($parser->isAllowedPath('Object.ObjMultimediaRef.TypeVoc'));

        /** @var TreeNode $objectResult */
        $result = $parser->parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'parserTest.xml');
        $this->assertEquals('123', $result->getNestedNode('Object.__id')->getValue());
        $this->assertEquals('repeatableGroup', $result->getNestedNode('Object.ObjBriefDescriptionGrp')->getTag());
        $this->assertNull($result->getNestedNode('Object.ObjBriefDescriptionGrp.TypeVoc'));
        $this->assertNull($result->getNestedNode('Object.ObjAcquisitionYearTxt'));
        $this->assertNull($result->getNestedNode('Object.ObjMultimediaRef.ThumbnailBoo'));
        $this->assertNotNull($result->getNestedNode('Object.ObjMultimediaRef.TypeVoc'));
        $this->assertFalse($result->getNestedNode('Object.ObjMultimediaRef.TypeVoc')->isLeaf());
        $this->assertEquals(
            'Testing',
            $result->getNestedNode('Object.ObjMultimediaRef.TypeVoc')->getChildren()[0]->getValue()
        );
    }

    public function testSearchResult()
    {
        $parser = new Parser();
        $parser->setAllowedPaths([
            'Exhibition.__id',
            'Exhibition.ExhTitleTxt',
            'Exhibition.ExhTextGrp.TextClb',
            'Exhibition.ExhPersonRef.TypeVoc'
        ]);
        $result = $parser->parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'SearchResult.xml');
        $this->assertCount(1, $result->getChildren());
        $this->assertCount(3, $result->getChildren()[0]->getChildren());
        $this->assertCount(4, $result->getChildren()[0]->getChildren()[0]->getChildren());
    }
}
