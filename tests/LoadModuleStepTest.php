<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Import\Step\ImportModuleStep;
use Mutoco\Mplus\Import\Step\LoadModuleStep;
use Mutoco\Mplus\Tests\Api\Client;
use Mutoco\Mplus\Tests\Model\Exhibition;
use SilverStripe\Config\Collections\MutableConfigCollectionInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\FunctionalTest;
use Symfony\Component\Yaml\Yaml;

class LoadModuleStepTest extends FunctionalTest
{
    protected static $extra_dataobjects = [
        Exhibition::class
    ];

    protected array $loadedConfig;

    protected function setUp()
    {
        parent::setUp();

        Config::nest();

        Config::inst()->merge(Injector::class, 'Mutoco\Mplus\Api\Client', ['class' => Client::class]);

        $this->loadedConfig = Yaml::parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'test.yml');
        if (isset($this->loadedConfig['ImportEngine'])) {
            Config::inst()->merge(ImportEngine::class, 'modules', $this->loadedConfig['ImportEngine']['modules']);
        }
    }

    protected function tearDown()
    {
        Config::unnest();
        parent::tearDown();
    }

    public function testLoadStep()
    {
        $engine = new ImportEngine();
        $engine->setApi(new Client());
        $engine->addStep(new LoadModuleStep('Exhibition', 2));
        $this->assertEquals(1, $engine->getTotalSteps());
        $engine->next();
        $engine->next(); // Must run multiple times to resolve tree
        $this->assertEquals(3, $engine->getTotalSteps());
        $current = $engine->getBackend()->getNextStep($prio);
        $engine->addStep($current, $prio);
        $engine->next();
        $this->assertInstanceOf(ImportModuleStep::class, $current, 'Immediately import a resolved module');
        $this->assertEquals(2, $current->getId());
        $this->assertEquals('Exhibition', $current->getModule());
        $current = $engine->getBackend()->getNextStep($prio);
        $this->assertInstanceOf(ImportModuleStep::class, $current, 'Import direct relations');
        $this->assertEquals(356559, $current->getId());
        $this->assertEquals('ExhTextGrp', $current->getModule());
    }

    public function testTreeResolve()
    {
        Config::withConfig(function(MutableConfigCollectionInterface $config) {
            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['ImportEngine']['modules']);
            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $engine->addStep($step = new LoadModuleStep('Exhibition', 2));

            $step->activate($engine);
            $this->assertTrue($step->run($engine), 'First step is to load the module from API');
            $this->assertFalse($step->run($engine), 'Second step marks completion as tree is resolved');
            $this->assertNull($engine->getBackend()->getImportedTree('Person', 1892), 'No person has been imported');
        });

        Config::withConfig(function(MutableConfigCollectionInterface $config) {
            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['TestResolveTree']['modules']);
            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $engine->addStep($step = new LoadModuleStep('Exhibition', 2));
            $step->activate($engine);
            do {
                $hasRemaining = $step->run($engine);
            } while ($hasRemaining);
            $tree = $step->getResultTree();
            $step->deactivate($engine);
            // Deque current
            $engine->getBackend()->getNextStep($prio);
            // Deque next
            $step = $engine->getBackend()->getNextStep($prio);

            $this->assertEquals('Künstler/in', $tree->getNestedValue('ExhPersonRef.TypeVoc.artist'), 'Has resolved internal field');
            $this->assertEquals('Edvard', $tree->getNestedValue('ExhPersonRef.PerFirstNameTxt'), 'Has resolved external field');
            $this->assertInstanceOf(LoadModuleStep::class, $step);
            $this->assertEquals(47894, $step->getId());
            $this->assertEquals('Object', $step->getModule(), 'Should load related Object next');
        });
    }
}
