<?php

namespace Mutoco\Mplus\Tests;

use Mutoco\Mplus\Api\SearchBuilder;
use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Import\Step\ImportModuleStep;
use Mutoco\Mplus\Import\Step\LoadModuleStep;
use Mutoco\Mplus\Import\Step\LoadSearchStep;
use Mutoco\Mplus\Model\VocabularyGroup;
use Mutoco\Mplus\Model\VocabularyItem;
use Mutoco\Mplus\Tests\Api\Client;
use Mutoco\Mplus\Tests\Model\Exhibition;
use Mutoco\Mplus\Tests\Model\ExhibitionWork;
use Mutoco\Mplus\Tests\Model\Person;
use Mutoco\Mplus\Tests\Model\Work;
use SilverStripe\Config\Collections\MutableConfigCollectionInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use Symfony\Component\Yaml\Yaml;

class LoadSearchStepTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        Exhibition::class,
        Work::class,
        ExhibitionWork::class,
        VocabularyItem::class
    ];

    protected array $loadedConfig;

    protected function setUp()
    {
        parent::setUp();

        Config::nest();
        Config::inst()->merge(Injector::class, 'Mutoco\Mplus\Api\Client', ['class' => Client::class]);
        $this->loadedConfig = Yaml::parseFile(
            __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'test.yml'
        );
    }

    protected function tearDown()
    {
        Config::unnest();
        parent::tearDown();
    }

    public function testSteppedLoading()
    {
        Config::withConfig(function (MutableConfigCollectionInterface $config) {
            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['SearchLoaderSimple']['modules']);
            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $search = new SearchBuilder('Exhibition', 0, 5);
            $engine->addStep(new LoadSearchStep($search));
            $steps = [];
            $starts = [];
            do {
                $hasSteps = $engine->next();
                $steps[] = get_class($engine->getLastStep());
                if ($engine->getLastStep() instanceof LoadSearchStep) {
                    $starts[] = $engine->getLastStep()->getSearch()->getStart();
                }
            } while ($hasSteps);

            $this->assertEquals([0, 5, 10], $starts, 'Load offsets must be in steps of 5');

            $this->assertEquals([
                LoadSearchStep::class,
                LoadModuleStep::class,
                ImportModuleStep::class,
                LoadModuleStep::class,
                ImportModuleStep::class,
                LoadModuleStep::class,
                ImportModuleStep::class,
                LoadModuleStep::class,
                ImportModuleStep::class,
                LoadModuleStep::class,
                ImportModuleStep::class,
                LoadSearchStep::class,
                LoadModuleStep::class,
            ], array_slice($steps, 0, 13), 'LoadSearchStep must repeat after 5 imports');
        });
    }


    public function testModelImport()
    {
        Config::withConfig(function (MutableConfigCollectionInterface $config) {
            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['SearchLoader']['modules']);
            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $search = new SearchBuilder('Exhibition', 0, 5);
            $engine->addStep(new LoadSearchStep($search));

            do {
                $hasSteps = $engine->next();
            } while ($hasSteps);

            $titles = [
                'Lorem',
                'Ipsum',
                'Dolor',
                'Sit',
                'Amet',
                'Consectetur',
                'Adipiscing',
                'Elit',
                'Mauris',
                'Venenatis',
                'Ullamcorper',
                'Risus'
            ];
            sort($titles);

            $this->assertEquals($titles, Exhibition::get()->sort('Title')->column('Title'));

            $this->assertEquals([
                '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'
            ], Exhibition::get()->column('MplusID'));

            $ex1 = Exhibition::get()->find('MplusID', 1);
            $this->assertCount(3, $ex1->ArtMovements());
            $this->assertEmpty($ex1->ArtMovements()->filter(['VocabularyGroupID' => 0]));
            $ex4 = Exhibition::get()->find('MplusID', 4);
            $this->assertCount(2, $ex4->ArtMovements());
            $this->assertEmpty($ex4->ArtMovements()->filter(['VocabularyGroupID' => 0]));
            $this->assertCount(1, VocabularyGroup::get());
            $ex6 = Exhibition::get()->find('MplusID', 6);
            $ex7 = Exhibition::get()->find('MplusID', 7);
            $this->assertCount(1, $ex6->Works());
            $this->assertCount(1, $ex7->Works());
            $this->assertEquals('Testdatensatz Portrait', $ex6->Works()->First()->Title);
            $this->assertEquals('Testdatensatz Portrait', $ex7->Works()->First()->Title);
        });
    }

    public function testSerialize()
    {
        $search = new SearchBuilder('Exhibition', 0, 5);
        $step = new LoadSearchStep($search);
        /** @var LoadSearchStep $copy */
        $copy = unserialize(serialize($step));
        $this->assertEquals($copy->getSearch()->__toString(), $search->__toString());
    }

    public function testEmptyResult()
    {
        Config::withConfig(function (MutableConfigCollectionInterface $config) {
            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['SearchLoader']['modules']);
            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $search = new SearchBuilder('Object', 0, 5);
            $engine->addStep(new LoadSearchStep($search));
            $loops = 0;
            while ($engine->next()) {
                $loops++;
            }

            $this->assertEquals(1, $loops);
        });
    }
}
