<?php

namespace Mutoco\Mplus\Tests;

use Mutoco\Mplus\Import\ModelImporter;
use SilverStripe\Dev\SapphireTest;

class ModelImporterTest extends SapphireTest
{
    private $xml;

    protected function setUp()
    {
        parent::setUp();

        $this->xml = new \DOMDocument();
        $this->xml->load(__DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'model.xml');
    }

    public function testSerialization()
    {
        $instance = new ModelImporter('Exhibition', '//m:module[@name="Exhibition"]/m:moduleItem', $this->xml);
        $serialized = serialize($instance);

        $copy = unserialize($serialized);
        $this->assertInstanceOf(ModelImporter::class, $copy, 'Deserialized object should be of type ModelImporter');
        $this->assertEquals($copy->getXml()->saveXML(), $instance->getXml()->saveXML(), 'Deserialized object should have the same XML content');
        $this->assertEquals($copy->getXpath(), $instance->getXpath(), 'Deserialized object should have the same xpath');
        $this->assertEquals($copy->getCurrentIndex(), $instance->getCurrentIndex(), 'Deserialized object should have the same index');
        $this->assertEquals($copy->getContext(), $instance->getContext(), 'Deserialized object should have the same context');
    }
}
