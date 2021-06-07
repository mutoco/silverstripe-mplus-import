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
    }

}
