<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Api\SearchBuilder;
use SilverStripe\Dev\FunctionalTest;

class SearchBuilderTest extends FunctionalTest
{

    public function testSearchBuilder()
    {
        $builder = new SearchBuilder('Test');
        $builder->setPrettyPrint(true);
        $builder->setLimit(20);
        $builder->setStart(10);
        $xml = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'search' . DIRECTORY_SEPARATOR . 'default.xml');
        $this->assertEquals($xml, (string)$builder);

        $builder->setSelect(['__id', 'AdrSurNameTxt', 'AdrContactGrp.TypeVoc']);
        $xml = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'search' . DIRECTORY_SEPARATOR . 'withFields.xml');
        $this->assertEquals($xml, (string)$builder);

        $builder->setSelect([]);
        $builder->setStart(0);
        $builder->setLimit(10);
        $builder->setFulltext('*test');
        $builder->addSort('__id', false);
        $xml = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'search' . DIRECTORY_SEPARATOR . 'withFulltextAndSort.xml');
        $this->assertEquals($xml, (string)$builder);

        $builder = new SearchBuilder('Test');
        $builder->setPrettyPrint(true);
        $builder->setExpert([
            'and' => [
                [
                    'type' => 'isNotNull',
                    'fieldPath' => '__id'
                ],
                [
                    'type' => 'betweenIncl',
                    'fieldPath' => 'Date',
                    'operand1' => '1900-01-01',
                    'operand2' => '1920-01-01'
                ]
            ]
        ]);
        $xml = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'search' . DIRECTORY_SEPARATOR . 'withExpert.xml');
        $this->assertEquals($xml, (string)$builder);
    }

    public function testSingleExpert()
    {
        $builder = new SearchBuilder('Test');
        $builder->setPrettyPrint(true);
        $builder->setExpert([
            [
                'type' => 'equalsField',
                'fieldPath' => '__id',
                'operand' => '123'
            ]
        ]);
        $xml = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'search' . DIRECTORY_SEPARATOR . 'withSingleExpert.xml');
        $this->assertEquals($xml, (string)$builder);
    }

    public function testNestedExpert()
    {
        $builder = new SearchBuilder('Test');
        $builder->setPrettyPrint(true);
        $builder->setExpert([
            'and' => [
                [
                    'type' => 'greater',
                    'fieldPath' => '__lastModified',
                    'operand' => '2021-07-09'
                ],
                'or' => [
                    [
                        'type' => 'betweenIncl',
                        'fieldPath' => 'Date',
                        'operand1' => '1900-01-01',
                        'operand2' => '1920-01-01'
                    ],
                    [
                        'type' => 'equalsField',
                        'fieldPath' => '__id',
                        'operand' => '123'
                    ]
                ]

            ]
        ]);

        $xml = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'search' . DIRECTORY_SEPARATOR . 'withNestedExpert.xml');
        $this->assertEquals($xml, (string)$builder);
    }

    public function testValid()
    {
        $builder = new SearchBuilder('Test');
        $builder->setExpert([
            'and' => [
                [
                    'type' => 'isNotNull',
                    'fieldPath' => '__id'
                ]
            ]
        ]);
        $this->assertFalse($builder->isValid());
    }

    public function testSerialize()
    {
        $builder = new SearchBuilder('Test');
        $builder->setPrettyPrint(true);
        $builder->setLimit(20);
        $builder->setStart(10);
        $builder->setFulltext('*test');
        $builder->setSelect(['__id', 'AdrSurNameTxt', 'AdrContactGrp.TypeVoc']);
        $builder->addSort('__id', false);
        $builder->setExpert([
            'and' => [
                [
                    'type' => 'isNotNull',
                    'fieldPath' => '__id'
                ]
            ]
        ]);

        /** @var SearchBuilder $copy */
        $copy = unserialize(serialize($builder));

        $this->assertEquals('Test', $copy->getModule());
        $this->assertEquals(20, $copy->getLimit());
        $this->assertEquals(10, $copy->getStart());
        $this->assertEquals('*test', $copy->getFulltext());
        $this->assertEquals(['__id', 'AdrSurNameTxt', 'AdrContactGrp.TypeVoc'], $copy->getSelect());
        $this->assertTrue($copy->hasSort('__id'));
        $this->assertEquals([
            'and' => [
                [
                    'type' => 'isNotNull',
                    'fieldPath' => '__id'
                ]
            ]
        ], $copy->getExpert());

        $copy->addSort('__id');
        $copy->setExpert([
            'and' => [
                [
                    'type' => 'isNotNull',
                    'fieldPath' => '__id'
                ],
                [
                    'type' => 'isBlank',
                    'fieldPath' => 'Foo'
                ],
            ]
        ]);

        $xml = file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'search' . DIRECTORY_SEPARATOR . 'complex.xml');
        $this->assertEquals($xml, (string)$copy);
    }
}
