<?php

namespace Mutoco\Mplus\Tests;

use Mutoco\Mplus\Import\ModelImporter;
use Mutoco\Mplus\Import\RelationImporter;
use Mutoco\Mplus\Tests\Api\Client;
use Mutoco\Mplus\Tests\Model\Exhibition;
use Mutoco\Mplus\Tests\Model\Person;
use Mutoco\Mplus\Tests\Model\TextBlock;
use SilverStripe\Config\Collections\MutableConfigCollectionInterface;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symfony\Component\Yaml\Yaml;

class ModelImporterTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        Exhibition::class,
        TextBlock::class,
        Person::class
    ];

    protected static $fixture_file = 'ModelImporterTestFixture.yml';
    private $xml;
    private $loadedConfig = null;

    protected function setUp()
    {
        parent::setUp();

        Config::nest();

        Config::inst()->merge(Injector::class, 'Mutoco\Mplus\Api\Client', ['class' => Client::class]);

        $this->loadedConfig = Yaml::parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'test.yml');
        if (isset($this->loadedConfig['ModelImporterDefault'])) {
            Config::inst()->merge(ModelImporter::class, 'models', $this->loadedConfig['ModelImporterDefault']['models']);
        }

        $this->xml = new \DOMDocument();
        $this->xml->load(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'model.xml');
    }

    protected function tearDown()
    {
        Config::unnest();
        parent::tearDown();
    }

    public function testSerialization()
    {
        $instance = new ModelImporter('Exhibition', '//m:module[@name="Exhibition"]/m:moduleItem');
        $instance->initialize($this->xml);
        $context = $instance->performQuery('//m:repeatableGroupItem[@id="356559"]')->item(0);
        $instance->setContext($context);
        $serialized = serialize($instance);

        $copy = unserialize($serialized);
        $this->assertInstanceOf(ModelImporter::class, $copy, 'Deserialized object should be of type ModelImporter');
        $this->assertEquals($copy->getXml()->saveXML(), $instance->getXml()->saveXML(), 'Deserialized object should have the same XML content');
        $this->assertEquals($copy->getXpath(), $instance->getXpath(), 'Deserialized object should have the same xpath');
        $this->assertEquals($copy->getCurrentIndex(), $instance->getCurrentIndex(), 'Deserialized object should have the same index');
        $this->assertEquals($copy->getUUID(), $instance->getUUID(), 'Deserialized object should have the same uuid');
        $this->assertEquals($copy->getContext(), $instance->getContext(), 'Deserialized object should have the same context');

    }

    public function testSubtaskSerialization()
    {
        $instance = new ModelImporter('Exhibition', '//m:module[@name="Exhibition"]/m:moduleItem');
        $instance->initialize($this->xml);

        $exhibition = $this->objFromFixture(Exhibition::class, 'exhibition2');
        $subtask = new RelationImporter('ExhTextGrp', '//m:repeatableGroup[@name="ExhTextGrp"]/m:repeatableGroupItem', $instance, $exhibition, 'Texts');
        $serialized = serialize($subtask);
        $copy = unserialize($serialized);
        $copy->setParent($instance);
        $this->assertEquals($copy->getXml()->saveXML(), $instance->getXml()->saveXML(), 'Deserialized object should have the same XML content');
        $this->assertEquals($copy->getTarget()->ID, $subtask->getTarget()->ID, 'Deserialized object should have the same Target');
        $this->assertEquals($copy->getRelationName(), $subtask->getRelationName(), 'Deserialized object should have the same relation name');
    }

    public function testSerializationAndResume()
    {
        $cfg = $this->loadedConfig;
        Config::withConfig(function(MutableConfigCollectionInterface $config) use ($cfg) {
            // update your config
            $config->set(ModelImporter::class, 'models', $cfg['ModelImporterDefault']['models']);
            $instance = new ModelImporter('Exhibition', '//m:module[@name="Exhibition"]/m:moduleItem');
            $instance->initialize($this->xml);
            $instance->importNext();
            $this->assertEquals(1, $instance->getProcessedSteps(), 'Processed steps should be 1');
            $instance->importNext();

            /** @var ModelImporter $copy */
            $copy = unserialize(serialize($instance));
            $this->assertEquals(2, $copy->getProcessedSteps(), 'Processed steps should be 2');

            while ($copy->importNext()) { /* NOOP */ }

            $imported = Exhibition::get()->find('MplusID', 2);
            $this->assertEquals([356559, 356558, 367558, 367559, 367560], $imported->Texts()->column('MplusID'));
        });
    }

    public function testDeleteUnused()
    {
        $cfg = $this->loadedConfig;
        Config::withConfig(function(MutableConfigCollectionInterface $config) use ($cfg) {
            $config->set(ModelImporter::class, 'models', $cfg['ModelImporterDefault']['models']);
            $exhibition = Exhibition::create()->update([
                'MplusID' => 3,
                'Title' => 'Testrecord'
            ]);
            $exhibition->write();

            $this->assertEquals([2,3], Exhibition::get()->column('MplusID'));
            $instance = new ModelImporter('Exhibition', '//m:module[@name="Exhibition"]/m:moduleItem');
            $instance->initialize($this->xml);
            while ($instance->importNext()) { /* NOOP */ }

            $this->assertEquals([2], Exhibition::get()->column('MplusID'));
        });
    }

    public function testModifiedImport()
    {
        $cfg = $this->loadedConfig;
        Config::withConfig(function(MutableConfigCollectionInterface $config) use ($cfg) {
            $config->set(ModelImporter::class, 'models', $cfg['ModelImporterDefault']['models']);
            DBDatetime::set_mock_now('2021-05-10 10:00:00');

            $instance = new ModelImporter('Exhibition', '//m:module[@name="Exhibition"]/m:moduleItem');
            $instance->initialize($this->xml);
            $instance->importNext();

            $this->assertEquals([2], $instance->getImportedIds(), 'Exhibition should be imported');
            Exhibition::flush_and_destroy_cache();
            $imported = Exhibition::get()->find('MplusID', 2);
            $this->assertEquals('2021-05-10 10:00:00', $imported->Imported, 'Imported date must be updated to import time');

            $instance = new ModelImporter('Exhibition', '//m:module[@name="Exhibition"]/m:moduleItem');
            $instance->initialize($this->xml);
            $instance->importNext();
            $this->assertEmpty($instance->getImportedIds(), 'There should be no import, as nothing has changed');
            $this->assertEquals([2], $instance->getSkippedIds(), 'Exhibition must be in skipped');

            DBDatetime::clear_mock_now();
        });
    }

    public function testModuleRelationImport()
    {
        $cfg = $this->loadedConfig;
        Config::withConfig(function(MutableConfigCollectionInterface $config) use ($cfg) {
            // update your config
            $config->set(ModelImporter::class, 'models', $cfg['ModelImporterRelation']['models']);
            $instance = new ModelImporter('Exhibition', '//m:module[@name="Exhibition"]/m:moduleItem');
            $client = new Client();
            $instance->setApi($client);
            $instance->initialize($this->xml);
            while ($instance->importNext()) {  }
            $imported = Exhibition::get()->find('MplusID', 2);
            $this->assertEquals([1982], $imported->Persons()->column('MplusID'));
            $person = $imported->Persons()->find('MplusID', 1982);
            $this->assertEquals('Edvard', $person->Firstname);
            $this->assertEquals('Munch', $person->Lastname);
        });
    }
}
