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
        $engine->enqueue($step);
        do {
            $continue = $engine->next();
        } while ($continue);
        $this->assertEquals([
            'A:activate',
            'A:run',
            'A:run',
            'A:deactivate'
        ], TestStep::$stack);
    }

    public function testIllegalEnqueue()
    {
        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('A queue cannot change size during one run step');
        $engine = new ImportEngine();
        $step = new TestStep(1, true);
        $engine->enqueue($step, ImportEngine::QUEUE_LOAD);
        do {
            $continue = $engine->next();
        } while ($continue);
    }

    public function testValidEnqueue()
    {
        TestStep::$stack = [];
        $engine = new ImportEngine();
        $step = new TestStep(2, true);
        $engine->enqueue($step, ImportEngine::QUEUE_IMPORT);
        do {
            $continue = $engine->next();
        } while ($continue);
        $this->assertTrue($engine->isComplete());
        $this->assertEquals([
            'A:activate',
            'A:run',
            'B:activate',
            'B:run',
            'B:deactivate',
            'A:run',
            'A:deactivate'
        ], TestStep::$stack);
    }
}
