<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Import\ImportConfig;
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
                    'type' => 'Person',
                    'fields' => [
                        'Sort' => 'ExhPersonRef.seqNo'
                    ]
                ]
            ]
        ],
        'Person' => [
            'fields' => [
                'Firstname' => 'PerFirstNameTxt'
            ],
            'attachment' => 'ImageID'
        ],
        'ExhTextGrp' => [
            'fields' => [
                'Text' => 'TextClb',
            ],
            'relations' => [
                'Author' => [
                    'name' => 'AuthorRef',
                    'type' => 'Person'
                ]
            ]
        ]
    ];

    private static array $config2 = [
        'Exhibition' => [
            'fields' => [
            ],
            'relations' => [
                'Works' => [
                    'name' => 'ExhRegistrarRef.RegObjectRef',
                    'fields' => [
                        'Sort' => 'ExhRegistrarRef.RegSortLnu'
                    ],
                    'type' => 'Object'
                ]
            ]
        ],
        'Object' => [
            'fields' => [
                'Title' => 'ObjObjectTitleGrp.TitleTxt',
                'Subtitle' => 'ObjObjectTitleGrp.AdditionTxt',
                'Artist' => 'ObjContentDescriptionGrp.PersonRef.PerPersonVrt'
            ]
        ]
    ];

    public function testImportPaths()
    {
        $config = new ImportConfig([
            'Test' => [
                'fields' => [
                    'Title' => 'ExhTitleTxt',
                    'DateTo' => 'ExhDateToDat',
                    'DateFrom' => 'ExhDateFromDat',
                ]
            ]
        ]);

        $paths = $config->getImportPaths('Test');
        $this->assertEquals(['ExhTitleTxt', 'ExhDateToDat', 'ExhDateFromDat'], $paths);

        $config = new ImportConfig(self::$config);
        $paths = $config->getImportPaths('Test');
        $this->assertEquals([
            'ExhTextGrp',
            'ExhTextGrp.TextClb',
            'ExhTextGrp.AuthorRef',
            'ExhPersonRef',
            'ExhPersonRef.seqNo',
        ], $paths);

        $config = new ImportConfig(self::$config2);
        $paths = $config->getImportPaths('Exhibition');
        $this->assertEquals([
            'ExhRegistrarRef.RegObjectRef',
            'ExhRegistrarRef.RegSortLnu',
        ], $paths);

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
            $paths = $config->getImportPaths('Test');
            $this->assertEquals([
                'ExhTitleTxt',
                '__id'
            ], $paths);
        });
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
                'type' => 'ExhTextGrp',
            ],
            'Persons' => [
                'name' => 'ExhPersonRef',
                'type' => 'Person',
                'fields' => ['Sort' => 'ExhPersonRef.seqNo']
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
            $cfg->applyConfig([
                'Test' => [
                    'fields' => ['Date' => 'DateFrom']
                ],
                'Object' => [
                    'fields' => ['Interesting' => 'Stuff']
                ]
            ], true);

            $this->assertEquals([
                'modelClass' => 'Test',
                'fields' => ['MplusID' => '__id', 'Date' => 'DateFrom'],
                'relations' => [
                    'Texts' => [
                        'name' => 'ExhTextGrp',
                        'type' => 'ExhTextGrp',
                    ],
                    'Persons' => [
                        'name' => 'ExhPersonRef',
                        'type' => 'Person',
                        'fields' => ['Sort' => 'ExhPersonRef.seqNo']
                    ]
                ]
            ], $cfg->getModuleConfig('Test'));

            $this->assertEquals([
                'attachment' => 'ImageID',
                'fields' => [
                    'Firstname' => 'PerFirstNameTxt'
                ],
                'relations' => [],
            ], $cfg->getModuleConfig('Person'));

            $this->assertEquals([
                'fields' => ['Interesting' => 'Stuff'],
                'relations' => []
            ], $cfg->getModuleConfig('Object'));
        });
    }

    public function testConfigMerge()
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
                        'type' => 'ExhTextGrp',
                    ],
                    'Persons' => [
                        'name' => 'ExhPersonRef',
                        'type' => 'Person',
                        'fields' => ['Sort' => 'ExhPersonRef.seqNo']
                    ]
                ]
            ], $cfg->getModuleConfig('Test'));
        });
    }
}
