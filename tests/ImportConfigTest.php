<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Import\ImportConfig;
use Mutoco\Mplus\Parse\Node\CollectionParser;
use Mutoco\Mplus\Parse\Node\ObjectParser;
use Mutoco\Mplus\Parse\Util;
use SilverStripe\Config\Collections\MutableConfigCollectionInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\FunctionalTest;

class ImportConfigTest extends FunctionalTest
{
    private static array $config = [
        'Test' => [
            'modelClass' => 'Test',
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
        $config = new ImportConfig([
            'Test' => [
                'fields' => [
                    'Title' => 'ExhTitleTxt',
                    'DateTo' => 'ExhDateToDat',
                    'DateFrom' => 'ExhDateFromDat',
                ]
            ]
        ]);

        $parser = $config->parserForModule('Test');

        $this->assertInstanceOf(ObjectParser::class, $parser);
        $this->assertEquals(['ExhTitleTxt', 'ExhDateToDat', 'ExhDateFromDat'], $parser->getFieldList());
        $this->assertEquals('Test', $parser->getType());
    }

    public function testRelationParserFromConfig()
    {
        /** @var ObjectParser $parser */
        $config = new ImportConfig(self::$config);
        $parser = $config->parserForModule('Test');

        $this->assertInstanceOf(ObjectParser::class, $parser);
        $this->assertNull($parser->getFieldList());
        $this->assertEquals('Test', $parser->getType());

        $collectionParser = $parser->getCollectionParser('ExhTextGrp');
        $this->assertInstanceOf(CollectionParser::class, $collectionParser);
        $this->assertEquals('repeatableGroup', $collectionParser->getTag());

        /** @var ObjectParser $childParser */
        $childParser = $collectionParser->getChildParser();
        $this->assertInstanceOf(ObjectParser::class, $childParser);
        $this->assertEquals('ExhTextGrp', $childParser->getType());
        $collectionParser = $childParser->getCollectionParser('AuthorRef');
        $this->assertInstanceOf(CollectionParser::class, $collectionParser);


        $collectionParser = $parser->getCollectionParser('ExhPersonRef');
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
        $config = new ImportConfig(self::$config);
        $this->assertEquals('Person', $config->getRelationModule('Test', 'ExhPersonRef'));
        $this->assertEquals('ExhTextGrp', $config->getRelationModule('Test', 'ExhTextGrp'));
    }

    public function testRelationNormalization()
    {
        $config = new ImportConfig(self::$config);
        $this->assertEquals([
            'Texts' => [
                'name' => 'ExhTextGrp',
                'module' => 'ExhTextGrp'
            ],
            'Persons' => [
                'name' => 'ExhPersonRef',
                'module' => 'Person'
            ]
        ], $config->getRelationsForModule('Test'));
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

            $config = new ImportConfig($moduleConfig);
            $this->assertEquals([
                'Title' => 'ExhTitleTxt',
                'MplusID' => '__id'
            ], $config->getFieldsForModule('Test'));
        });
    }

    public function testModuleNormalization()
    {
        Config::withConfig(function(MutableConfigCollectionInterface $config) {
            // update your config
            $config->set('Test', 'mplus_import_fields', ['MplusID' => '__id']);
            $cfg = new ImportConfig(self::$config);

            $this->assertEquals([
                'modelClass' => 'Test',
                'fields' => ['MplusID' => '__id'],
                'relations' => [
                    'Texts' => [
                        'name' => 'ExhTextGrp',
                        'module' => 'ExhTextGrp'
                    ],
                    'Persons' => [
                        'name' => 'ExhPersonRef',
                        'module' => 'Person'
                    ]
                ]
            ], $cfg->getModuleConfig('Test'));
        });
    }

    public function testIncompleteRelationFromConfig()
    {
        $this->expectException(\InvalidArgumentException::class);
        $config = new ImportConfig([
            'Test' => [
                'relations' => [
                    'Texts' => 'ExhTextGrp'
                ]
            ]
        ]);
        /** @var ObjectParser $parser */
        $parser = $config->parserForModule('Test');
    }
}
