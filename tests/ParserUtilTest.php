<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Parse\Node\CollectionParser;
use Mutoco\Mplus\Parse\Node\ObjectParser;
use Mutoco\Mplus\Parse\Util;
use SilverStripe\Config\Collections\MutableConfigCollectionInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;

class ParserUtilTest extends FunctionalTest
{
    private static array $config = [
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
    ];

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
        $parser = Util::parserFromConfig(self::$config, 'Test');

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

    public function testRelationModule()
    {
        $module = Util::getRelationModule(self::$config, 'Test', 'ExhPersonRef');
        $this->assertEquals('Person', $module);

        $module = Util::getRelationModule(self::$config, 'Test', 'ExhTextGrp');
        $this->assertEquals('ExhTextGrp', $module);
    }

    public function testRelationNormalization()
    {
        $relations = Util::getNormalizedRelationConfig(self::$config, 'Test');
        $this->assertEquals([
            'Texts' => [
                'name' => 'ExhTextGrp',
                'module' => 'ExhTextGrp'
            ],
            'Persons' => [
                'name' => 'ExhPersonRef',
                'module' => 'Person'
            ]
        ], $relations);
    }

    public function testFieldNormalization()
    {
        Config::withConfig(function(MutableConfigCollectionInterface $config) {
            // update your config
            $config->set('Test', 'mplus_import_fields', ['MplusID' => '__id']);
            $moduleConfig = [
                'Test' => [
                    'modelClass' => 'Test',
                    'fields' => [
                        'Title' => 'ExhTitleTxt'
                    ]
                ]
            ];

            $fields = Util::getNormalizedFieldConfig($moduleConfig, 'Test');
            $this->assertEquals([
                'Title' => 'ExhTitleTxt',
                'MplusID' => '__id'
            ], $fields);
        });
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
