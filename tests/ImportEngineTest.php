<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Import\ImportEngine;
use Mutoco\Mplus\Tests\Step\TestStep;
use SilverStripe\Dev\FunctionalTest;

class ImportEngineTest extends FunctionalTest
{
    public function testEmptyEngine()
    {
        $engine = new ImportEngine();
        $this->assertFalse($engine->next(), 'Empty engine is already finished');
        $this->assertTrue($engine->isComplete());
        $this->assertNull($engine->getCurrentQueue());
    }

    public function testCallOrder()
    {
        TestStep::$stack = [];
        $engine = new ImportEngine();
        $step = new TestStep(2);
        $engine->addStep($step);
        do {
            $continue = $engine->next();
        } while ($continue);
        $this->assertEquals([
            'A:activate',
            'A:run',
            'A:run',
            'A:deactivate'
        ], TestStep::$stack);

        TestStep::$stack = [];
        $engine = new ImportEngine();
        $engine->addStep(new TestStep(1, false, 'A'));
        $engine->addStep(new TestStep(1, false, 'B'));
        do {
            $continue = $engine->next();
        } while ($continue);
        $this->assertEquals([
            'A:activate',
            'B:activate',
            'B:run',
            'B:deactivate',
            'A:run',
            'A:deactivate',
        ], TestStep::$stack);
    }

    public function testIllegalEnqueue()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A queue cannot change the current item during one run step');
        $engine = new ImportEngine();
        $step = new TestStep(1, true);
        $engine->addStep($step, ImportEngine::QUEUE_LOAD);
        do {
            $continue = $engine->next();
        } while ($continue);
    }

    public function testValidEnqueue()
    {
        TestStep::$stack = [];
        $engine = new ImportEngine();
        $step = new TestStep(2, true);
        $engine->addStep($step);
        $engine->addStep(new TestStep(1, false, 'C'));
        do {
            $continue = $engine->next();
        } while ($continue);
        $this->assertTrue($engine->isComplete());
        $this->assertEquals([
            'A:activate',
            'C:activate',
            'C:run',
            'C:deactivate',
            'A:run',
            'B:activate',
            'B:run',
            'B:deactivate',
            'A:run',
            'A:deactivate'
        ], TestStep::$stack);
        $this->assertEquals(4, $engine->getSteps());
    }

    public function testSerialize()
    {
        $engine = new ImportEngine();
        $engine->addStep(new TestStep());

        /** @var ImportEngine $copy */
        $copy = unserialize(serialize($engine));
        $this->assertFalse($copy->isComplete());
        $this->assertInstanceOf(TestStep::class, $copy->getCurrentQueue()->top());
    }

    public function testRegistry()
    {
        $engine = new ImportEngine();
        $this->assertFalse($engine->getRegistry()->hasImportedModule('Test'));
        $this->assertFalse($engine->getRegistry()->hasImportedModule('Test', '0'));
        $engine->getRegistry()->reportImportedModule('Test', 2);
        $engine->getRegistry()->reportImportedModule('Test', 3);
        $engine->getRegistry()->reportImportedModule('Person', 0);
        $engine->getRegistry()->reportImportedModule('Person', 2);
        $this->assertTrue($engine->getRegistry()->hasImportedModule('Test'));
        $this->assertTrue($engine->getRegistry()->hasImportedModule('Test', 2));
        $this->assertTrue($engine->getRegistry()->hasImportedModule('Test', 3));
        $this->assertFalse($engine->getRegistry()->hasImportedModule('Test', 0));
        $this->assertTrue($engine->getRegistry()->hasImportedModule('Person', 0));
        $this->assertEquals([2,3], $engine->getRegistry()->getImportedIds('Test'));
        $this->assertEquals([0,2], $engine->getRegistry()->getImportedIds('Person'));
    }
}
