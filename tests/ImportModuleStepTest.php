<?php

namespace Mutoco\Mplus\Tests;

use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Import\SqliteImportBackend;
use Mutoco\Mplus\Import\Step\LoadModuleStep;
use Mutoco\Mplus\Model\VocabularyGroup;
use Mutoco\Mplus\Model\VocabularyItem;
use Mutoco\Mplus\Tests\Api\Client;
use Mutoco\Mplus\Tests\Extension\TestTaxonomyExtension;
use Mutoco\Mplus\Tests\Model\Exhibition;
use Mutoco\Mplus\Tests\Model\ExhibitionWork;
use Mutoco\Mplus\Tests\Model\Person;
use Mutoco\Mplus\Tests\Model\Taxonomy;
use Mutoco\Mplus\Tests\Model\TaxonomyType;
use Mutoco\Mplus\Tests\Model\TextBlock;
use Mutoco\Mplus\Tests\Model\Work;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Config\Collections\MutableConfigCollectionInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symfony\Component\Yaml\Yaml;

class ImportModuleStepTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        Exhibition::class,
        TextBlock::class,
        Person::class,
        Work::class,
        ExhibitionWork::class,
        Taxonomy::class,
        TaxonomyType::class
    ];

    protected array $loadedConfig;
    protected static $fixture_file = 'ImportModuleStepTest.yml';

    protected function setUp()
    {
        parent::setUp();

        TestAssetStore::activate('data');

        Config::nest();

        Config::inst()->merge(Injector::class, 'Mutoco\Mplus\Api\Client', ['class' => Client::class]);

        $this->loadedConfig = Yaml::parseFile(
            __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'test.yml'
        );
    }

    protected function tearDown()
    {
        Config::unnest();
        TestAssetStore::reset();
        parent::tearDown();
    }


    public function testModelImport()
    {
        Config::withConfig(function (MutableConfigCollectionInterface $config) {
            $existing = $this->objFromFixture(Exhibition::class, 'exhibition2');
            $existing->write();
            $this->assertEquals(['10'], $existing->Persons()->column('MplusID'));

            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['ImportEngine']['modules']);
            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $engine->addStep(new LoadModuleStep('Exhibition', 2));
            do {
                $hasSteps = $engine->next();
            } while ($hasSteps);

            Exhibition::flush_and_destroy_cache();
            $exhibition = Exhibition::get()->find('MplusID', 2);
            $this->assertEquals('complete', $exhibition->Status);
            $this->assertEquals('Dies ist nur ein Test', $exhibition->Title);
            $this->assertEquals('2018-06-14', $exhibition->DateTo);
            $this->assertEquals(['1982'], $exhibition->Persons()->column('MplusID'));
            $person = $exhibition->Persons()->first();
            $this->assertEquals('Løiten (Hedmark)', $person->PlaceOfBirth);
            $this->assertEquals('Künstler/in', $person->Role);
            $this->assertEquals([
                'Dies ist ein Pressetext.',
                'This would be a press release.',
                'Das war eine Ausstellung...',
                '...die 5 Wochen gedauert hat...',
                '... und auf wenig Interesse gestossen ist.'
            ], $exhibition->Texts()->column('Text'));

            $this->assertEquals([2], $engine->getBackend()->getImportedIds('Exhibition'));
            $this->assertEquals([1982], $engine->getBackend()->getImportedIds('Person'));
            Person::flush_and_destroy_cache();
            $person = Person::get()->find('MplusID', 1982);
            $this->assertEquals('1863-12-12', $person->DateOfBirth);
            $this->assertEquals('Edvard', $person->Firstname);
            $this->assertEquals(
                [356559, 356558, 367558, 367559, 367560],
                $engine->getBackend()->getImportedIds('ExhTextGrp')
            );
        });
    }

    public function testModifiedImport()
    {
        Config::withConfig(function (MutableConfigCollectionInterface $config) {
            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['ImportEngine']['modules']);
            DBDatetime::set_mock_now('2021-05-10 10:00:00');

            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $engine->addStep(new LoadModuleStep('Exhibition', 2));
            do {
                $hasSteps = $engine->next();
            } while ($hasSteps);

            Exhibition::flush_and_destroy_cache();
            Person::flush_and_destroy_cache();
            $exhibition = Exhibition::get()->find('MplusID', 2);
            $person = Person::get()->find('MplusID', 1982);
            $this->assertEquals(
                '2021-05-10 10:00:00',
                $exhibition->Imported,
                'Imported date must be updated to import time'
            );
            $this->assertEquals(
                '2021-05-10 10:00:00',
                $person->Imported,
                'Imported date must be updated to import time'
            );

            DBDatetime::set_mock_now('2021-05-11 11:00:00');
            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $engine->addStep(new LoadModuleStep('Exhibition', 2));
            do {
                $hasSteps = $engine->next();
            } while ($hasSteps);

            Exhibition::flush_and_destroy_cache();
            $exhibition = Exhibition::get()->find('MplusID', 2);
            $this->assertEquals('2021-05-10 10:00:00', $exhibition->Imported, 'Imported date must still be as before');

            DBDatetime::clear_mock_now();
        });
    }

    public function testNestedFields()
    {
        Config::withConfig(function (MutableConfigCollectionInterface $config) {
            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['ImportNested']['modules']);
            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $engine->addStep(new LoadModuleStep('Exhibition', 2));
            do {
                $hasSteps = $engine->next();
            } while ($hasSteps);

            Exhibition::flush_and_destroy_cache();
            $exhibition = Exhibition::get()->find('MplusID', 2);
            $this->assertEquals([435960, 47894], $exhibition->Works()->column('MplusID'));
            $this->assertEquals(
                ['Stillleben mit Hummer', 'Testdatensatz Portrait'],
                $exhibition->Works()->column('Title')
            );
            $this->assertEquals(['TEST', 'Hummer'], $exhibition->Works()->column('Subtitle'));
            $this->assertEquals(['Edvard Munch', 'Edvard Munch'], $exhibition->Works()->column('Artist'));
            $this->assertTrue($exhibition->Works()->last()->Image()->exists());

            $sort = [];
            $type = [];
            foreach ($exhibition->Works() as $work) {
                $sort[] = $work->getJoin()->Sort;
                $type[] = $work->getJoin()->Type();
            }
            $this->assertEquals([3, 4], $sort);
            $this->assertCount(2, $type);
            $this->assertEquals('Outgoing', $type[0]->Value);
            $this->assertEquals('100087407', $type[0]->MplusID);
            $this->assertEquals(null, $type[1]->MplusID);
            $this->assertEquals('RegRecordTypeVgr', $type[0]->VocabularyGroup()->Name);
        });
    }

    public function testTaxonomyItems()
    {
        Config::withConfig(function (MutableConfigCollectionInterface $config) {
            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['TestVocabRelations']['modules']);
            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $engine->addStep(new LoadModuleStep('Exhibition', 2));
            do {
                $hasSteps = $engine->next();
            } while ($hasSteps);

            Exhibition::flush_and_destroy_cache();
            $exhibition = Exhibition::get()->find('MplusID', 2);
            $text = $exhibition->Texts()->first();
            $taxonomy = $text->Type();
            $this->assertNotNull($taxonomy);
            $this->assertInstanceOf(Taxonomy::class, $taxonomy);
            $this->assertEquals('100842588', $taxonomy->MplusID);
            $this->assertNull($taxonomy->Title);
            $this->assertFalse($taxonomy->Type()->exists());

            // Apply an extension that applies title and parent
            $config->merge(Taxonomy::class, 'extensions', [TestTaxonomyExtension::class]);

            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $engine->addStep(new LoadModuleStep('Exhibition', 2));
            do {
                $hasSteps = $engine->next();
            } while ($hasSteps);

            Exhibition::flush_and_destroy_cache();
            $exhibition = Exhibition::get()->find('MplusID', 2);
            $text = $exhibition->Texts()->first();
            $taxonomy = $text->Type();
            $this->assertNotNull($taxonomy);
            $this->assertInstanceOf(Taxonomy::class, $taxonomy);
            $this->assertEquals('100842588', $taxonomy->MplusID);
            $this->assertEquals('Press_DE', $taxonomy->Title);
            $this->assertTrue($taxonomy->Type()->exists());
            $this->assertEquals('ExhTextTypeVgr', $taxonomy->Type()->Title);
            $this->assertEquals('100000205', $taxonomy->Type()->MplusID);
        });
    }

    public function testVocabularyItems()
    {
        Config::withConfig(function (MutableConfigCollectionInterface $config) {
            DBDatetime::set_mock_now('2021-05-10 10:00:00');

            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['TestVocabularyModels']['modules']);
            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $engine->setBackend(new SqliteImportBackend());
            $engine->addStep(new LoadModuleStep('Exhibition', 2));
            do {
                $hasSteps = $engine->next();
            } while ($hasSteps);

            Exhibition::flush_and_destroy_cache();
            $exhibition = Exhibition::get()->find('MplusID', 2);

            $vocabulary = $exhibition->InternalType();
            $this->assertNotNull($vocabulary);
            $this->assertInstanceOf(VocabularyItem::class, $vocabulary);
            $this->assertTrue($vocabulary->exists());
            $this->assertEquals('100035803', $vocabulary->MplusID);
            $this->assertEquals('internal', $vocabulary->Title);
            $this->assertEquals('Intern', $vocabulary->Value);
            $this->assertEquals('de', $vocabulary->Language);
            $this->assertEquals('2021-05-10 10:00:00', $vocabulary->Imported);
            $this->assertEquals('VocabularyItem', $vocabulary->Module);

            $group = $vocabulary->VocabularyGroup();
            $this->assertNotNull($group);
            $this->assertInstanceOf(VocabularyGroup::class, $group);
            $this->assertTrue($group->exists());
            $this->assertEquals('100014392', $group->MplusID);
            $this->assertEquals('ExhExternalInternalVgr', $group->Name);
        });
    }

    public function testWithSerialization()
    {
        Config::withConfig(function (MutableConfigCollectionInterface $config) {
            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['ImportNested']['modules']);
            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $engine->addStep(new LoadModuleStep('Exhibition', 2));

            $copy = $engine;
            do {
                /** @var ImportEngine $copy */
                $copy = unserialize(serialize($copy));
                $hasSteps = $copy->next();
            } while ($hasSteps);

            Exhibition::flush_and_destroy_cache();
            $exhibition = Exhibition::get()->find('MplusID', 2);
            $this->assertEquals([435960, 47894], $exhibition->Works()->column('MplusID'));
            $this->assertEquals(
                ['Stillleben mit Hummer', 'Testdatensatz Portrait'],
                $exhibition->Works()->column('Title')
            );
            $this->assertEquals(['TEST', 'Hummer'], $exhibition->Works()->column('Subtitle'));
            $this->assertEquals(['Edvard Munch', 'Edvard Munch'], $exhibition->Works()->column('Artist'));
        });
    }
}
