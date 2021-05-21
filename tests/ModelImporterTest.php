<?php

namespace Mutoco\Mplus\Tests;

use Mutoco\Mplus\Import\ModelImporter;
use Mutoco\Mplus\Import\RelationImporter;
use Mutoco\Mplus\Tests\Model\Exhibition;
use Mutoco\Mplus\Tests\Model\TextBlock;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBDatetime;
use Symfony\Component\Yaml\Yaml;

class ModelImporterTest extends SapphireTest
{
    protected static $extra_dataobjects = [
        Exhibition::class,
        TextBlock::class
    ];

    protected static $fixture_file = 'ModelImporterTestFixture.yml';
    private $xml;

    protected function setUp()
    {
        parent::setUp();

        $config = Yaml::parseFile(__DIR__ . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'test.yml');
        if (isset($config[ModelImporter::class])) {
            Config::inst()->merge(ModelImporter::class, 'models', $config[ModelImporter::class]['models']);
        }
        $this->xml = new \DOMDocument();
        $this->xml->load(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'model.xml');
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

    public function testModifiedImport()
    {
        DBDatetime::set_mock_now('2021-05-10 10:00:00');

        $instance = new ModelImporter('Exhibition', '//m:module[@name="Exhibition"]/m:moduleItem');
        $instance->initialize($this->xml);
        $instance->importNext();

        $this->assertEquals([2], $instance->getImportedIds());
        $imported = Exhibition::get()->find('MplusID', 2);
        $this->assertEquals('2021-05-10 10:00:00', $imported->Imported);

        $instance = new ModelImporter('Exhibition', '//m:module[@name="Exhibition"]/m:moduleItem');
        $instance->initialize($this->xml);
        $instance->importNext();
        $this->assertEmpty($instance->getImportedIds());
        $this->assertEquals([2], $instance->getSkippedIds());

        DBDatetime::clear_mock_now();
    }
}
