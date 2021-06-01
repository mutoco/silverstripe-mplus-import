<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Parse\Node\CollectionParser;
use Mutoco\Mplus\Parse\Node\ObjectParser;
use Mutoco\Mplus\Parse\Util;
use SilverStripe\Dev\FunctionalTest;

class ParserUtilTest extends FunctionalTest
{
    public function testBasicParserFromConfig()
    {
        /** @var ObjectParser $parser */
        $parser = Util::parserFromConfig([
            'Test' => [
                'fields' => [
                    'Title' => 'ExhTitleTxt',
                    'DateTo' => 'ExhDateToDat',
                    'DateFrom' => 'ExhDateFromDat',
                ]
            ]
        ], 'Test');

        $this->assertInstanceOf(ObjectParser::class, $parser);
        $this->assertEquals(['ExhTitleTxt', 'ExhDateToDat', 'ExhDateFromDat'], $parser->getFieldList());
        $this->assertEquals('Test', $parser->getType());
    }

    public function testRelationParserFromConfig()
    {
        /** @var ObjectParser $parser */
        $parser = Util::parserFromConfig([
            'Test' => [
                'relations' => [
                    'Texts' => 'ExhTextGrp',
                    'Persons' => [
                        'name' => 'ExhPersonRef',
                        'module' => 'Person'
                    ]
                ]
            ],
            'Person' => [
                'fields' => [
                    'Firstname' => 'PerFirstNameTxt'
                ]
            ],
            'ExhTextGrp' => [
                'fields' => [
                    'Text' => 'TextClb',
                ],
                'relations' => [
                    'Author' => [
                        'name' => 'AuthorRef',
                        'module' => 'Person'
                    ]
                ]
            ]
        ], 'Test');

        $this->assertInstanceOf(ObjectParser::class, $parser);
        $this->assertNull($parser->getFieldList());
        $this->assertEquals('Test', $parser->getType());

        $collectionParser = $parser->getRelationParser('ExhTextGrp');
        $this->assertInstanceOf(CollectionParser::class, $collectionParser);
        $this->assertEquals('repeatableGroup', $collectionParser->getTag());

        /** @var ObjectParser $childParser */
        $childParser = $collectionParser->getChildParser();
        $this->assertInstanceOf(ObjectParser::class, $childParser);
        $this->assertEquals('ExhTextGrp', $childParser->getType());
        $collectionParser = $childParser->getRelationParser('AuthorRef');
        $this->assertInstanceOf(CollectionParser::class, $collectionParser);


        $collectionParser = $parser->getRelationParser('ExhPersonRef');
        $this->assertInstanceOf(CollectionParser::class, $collectionParser);
        $this->assertEquals('moduleReference', $collectionParser->getTag());

        /** @var ObjectParser $childParser */
        $childParser = $collectionParser->getChildParser();
        $this->assertInstanceOf(ObjectParser::class, $childParser);
        $this->assertEquals('Person', $childParser->getType());
        $this->assertEquals(['PerFirstNameTxt'], $childParser->getFieldList());
    }

    public function testIncompleteRelationFromConfig()
    {
        $this->expectException(\InvalidArgumentException::class);

        /** @var ObjectParser $parser */
        $parser = Util::parserFromConfig([
            'Test' => [
                'relations' => [
                    'Texts' => 'ExhTextGrp'
                ]
            ]
        ], 'Test');
    }
}
