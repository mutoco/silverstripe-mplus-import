<?php

namespace Mutoco\Mplus\Tests;

use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Import\Step\ImportAttachmentStep;
use Mutoco\Mplus\Import\Step\LoadModuleStep;
use Mutoco\Mplus\Tests\Api\Client;
use Mutoco\Mplus\Tests\Extension\TestSkipAttachmentExtension;
use Mutoco\Mplus\Tests\Model\Exhibition;
use Mutoco\Mplus\Tests\Model\ExhibitionWork;
use Mutoco\Mplus\Tests\Model\Person;
use Mutoco\Mplus\Tests\Model\TextBlock;
use Mutoco\Mplus\Tests\Model\Work;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Config\Collections\MutableConfigCollectionInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symfony\Component\Yaml\Yaml;

class ImportAttachmentStepTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        Exhibition::class,
        TextBlock::class,
        Person::class,
        Work::class,
        ExhibitionWork::class
    ];

    protected array $loadedConfig;
    protected static $fixture_file = 'ImportAttachmentStepTest.yml';

    protected function setUp(): void
    {
        parent::setUp();


        TestAssetStore::activate('data');

        Config::nest();

        Config::inst()->merge(Injector::class, 'Mutoco\Mplus\Api\Client', ['class' => Client::class]);

        $this->loadedConfig = Yaml::parseFile(
            __DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'test.yml'
        );
    }

    protected function tearDown(): void
    {
        Config::unnest();
        TestAssetStore::reset();
        parent::tearDown();
    }

    public function testAttachmentImport()
    {
        if (! extension_loaded('imagick')) {
            $this->markTestSkipped('The imagick extension is not installed. Skipping.');
        }

        Config::withConfig(function (MutableConfigCollectionInterface $config) {
            DBDatetime::set_mock_now('2021-05-10 10:00:00');
            $object = $this->objFromFixture(Work::class, 'work1');
            $object->write();


            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['ImportNested']['modules']);
            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $engine->addStep(new ImportAttachmentStep('Object', 1));
            do {
                $hasSteps = $engine->next();
            } while ($hasSteps);

            Work::flush_and_destroy_cache();
            $work = Work::get()->find('MplusID', 1);
            // Importing TIFF will only work with imagick
            if (extension_loaded('imagick')) {
                $this->assertTrue($work->Image()->exists());
                $this->assertEquals(766, $work->Image()->getWidth());
                $this->assertEquals(1000, $work->Image()->getHeight());
                $this->assertEquals('file-Object-1.jpg', $work->Image()->Name);
                $this->assertEquals('2021-05-10 10:00:00', $work->Image()->LastEdited);

                DBDatetime::set_mock_now('2021-05-10 12:08:00');
                $engine = new ImportEngine();
                $engine->setApi(new Client());
                $engine->addStep(new ImportAttachmentStep('Object', 1));
                do {
                    $hasSteps = $engine->next();
                } while ($hasSteps);

                Work::flush_and_destroy_cache();
                $work = Work::get()->find('MplusID', 1);
                $this->assertEquals('2021-05-10 10:00:00', $work->Image()->LastEdited, 'Image should not get updated');

                DBDatetime::clear_mock_now();
            }
        });
    }

    public function testSkipAttachment()
    {
        Config::withConfig(function (MutableConfigCollectionInterface $config) {
            $config->set(ImportEngine::class, 'modules', $this->loadedConfig['ImportNested']['modules']);
            $config->merge(Work::class, 'extensions', [
                TestSkipAttachmentExtension::class
            ]);

            $engine = new ImportEngine();
            $engine->setApi(new Client());
            $engine->addStep(new LoadModuleStep('Exhibition', 2));
            do {
                $hasSteps = $engine->next();
            } while ($hasSteps);

            Work::flush_and_destroy_cache();
            $work = Work::get()->find('MplusID', 47894);
            $this->assertFalse($work->Image()->exists());
        });
    }
}
