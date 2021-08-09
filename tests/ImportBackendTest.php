<?php

namespace Mutoco\Mplus\Tests;

use Mutoco\Mplus\Import\MemoryImportBackend;
use Mutoco\Mplus\Import\BackendInterface;
use Mutoco\Mplus\Import\SqliteImportBackend;
use Mutoco\Mplus\Import\Step\LoadModuleStep;
use Mutoco\Mplus\Parse\Result\TreeNode;
use SilverStripe\Dev\FunctionalTest;

class ImportBackendTest extends FunctionalTest
{
    public function testTree()
    {
        $this->runTreeTests(new MemoryImportBackend());
        $this->runTreeTests(new SqliteImportBackend());
    }

    public function testRelation()
    {
        $this->runRelationTests(new MemoryImportBackend());
        $this->runRelationTests(new SqliteImportBackend());
    }

    public function testModules()
    {
        $this->runModuleTests(new MemoryImportBackend());
        $this->runModuleTests(new SqliteImportBackend());
    }

    public function testClear()
    {
        $this->runClearTests(new MemoryImportBackend());
        $this->runClearTests(new SqliteImportBackend());
    }

    public function testQueue()
    {
        $this->runQueueTests(new MemoryImportBackend());
        $this->runQueueTests(new SqliteImportBackend());
    }

    protected function runQueueTests(BackendInterface $registry)
    {
        $this->assertEquals(0, $registry->getRemainingSteps());
        $this->assertNull($registry->getNextStep($prio));
        $this->assertEquals(0, $prio);

        $registry->addStep(new LoadModuleStep('A', '3'), 50);
        $registry->addStep(new LoadModuleStep('B', '2'), 100);
        $registry->addStep(new LoadModuleStep('C', '1'), 10);

        $this->assertEquals(3, $registry->getRemainingSteps());

        $step = $registry->getNextStep($priority);
        $this->assertEquals(100, $priority);
        $this->assertInstanceOf(LoadModuleStep::class, $step);
        $this->assertEquals('B', $step->getModule());
        $this->assertEquals('2', $step->getId());

        $this->assertEquals(2, $registry->getRemainingSteps());

        $step = $registry->getNextStep($priority);
        $this->assertEquals(50, $priority);
        $this->assertInstanceOf(LoadModuleStep::class, $step);
        $this->assertEquals('A', $step->getModule());
        $this->assertEquals('3', $step->getId());

        $this->assertEquals(1, $registry->getRemainingSteps());
        $registry->addStep(new LoadModuleStep('D', '4'), 20);

        $step = $registry->getNextStep($priority);
        $this->assertEquals(20, $priority);
        $this->assertInstanceOf(LoadModuleStep::class, $step);
        $this->assertEquals('D', $step->getModule());
        $this->assertEquals('4', $step->getId());

        $this->assertEquals(1, $registry->getRemainingSteps());

        $copy = unserialize(serialize($registry));

        $step = $copy->getNextStep($priority);
        $this->assertEquals(10, $priority);
        $this->assertInstanceOf(LoadModuleStep::class, $step);
        $this->assertEquals('C', $step->getModule());
        $this->assertEquals('1', $step->getId());
    }

    protected function runTreeTests(BackendInterface $registry)
    {
        $tree = new TreeNode('tag', ['name' => 'Foo']);
        $this->assertNull($registry->getImportedTree('Foo', '1'));
        $this->assertFalse($registry->hasImportedTree('Foo', '1'));
        $this->assertFalse($registry->clearImportedTree('Foo', '1'));

        $registry->setImportedTree('Foo', '1', $tree);
        $registry->setImportedTree('Foo', '1', $tree);
        $this->assertTrue($registry->hasImportedTree('Foo', '1'));
        $this->assertNotNull($registry->getImportedTree('Foo', '1'));
        $this->assertEquals('tag', $registry->getImportedTree('Foo', '1')->getTag());
        $this->assertTrue($registry->clearImportedTree('Foo', '1'));
        $this->assertFalse($registry->hasImportedTree('Foo', '1'));

        $registry->setImportedTree('Bar', '2', $tree);
        $copy = unserialize(serialize($registry));
        $this->assertNotNull($copy->getImportedTree('Bar', '2'));
        $this->assertInstanceOf(TreeNode::class, $copy->getImportedTree('Bar', '2'));
    }

    protected function runRelationTests(BackendInterface $registry)
    {
        $this->assertFalse($registry->hasImportedRelation('ClassName', '2', 'Relation'));
        $registry->reportImportedRelation('ClassName', '2', 'Relation', [1, 2, 3]);
        $registry->reportImportedRelation('ClassName', '2', 'Relation', [1, 2, 3]);
        $this->assertTrue($registry->hasImportedRelation('ClassName', '2', 'Relation'));
        $this->assertEquals([1, 2, 3], $registry->getRelationIds('ClassName', '2', 'Relation'));
        $this->assertEmpty($registry->getRelationIds('ClassName', '1', 'Relation'));

        $copy = unserialize(serialize($registry));

        $this->assertTrue($copy->hasImportedRelation('ClassName', '2', 'Relation'));
        $this->assertTrue($registry->hasImportedRelation('ClassName', '2', 'Relation'));
    }

    protected function runModuleTests(BackendInterface $registry)
    {
        $this->assertFalse($registry->hasImportedModule('Foo'));
        $this->assertEmpty($registry->getImportedIds('Foo'));
        $registry->reportImportedModule('Foo', '2');
        $registry->reportImportedModule('Foo', '2');
        $registry->reportImportedModule('Foo', '3');
        $registry->reportImportedModule('Bar', '3');
        $this->assertTrue($registry->hasImportedModule('Foo'));
        $this->assertFalse($registry->hasImportedModule('Foo', '1'));
        $this->assertEquals(['2','3'], $registry->getImportedIds('Foo'));

        $copy = unserialize(serialize($registry));
        $this->assertTrue($copy->hasImportedModule('Foo'));
        $this->assertFalse($copy->hasImportedModule('Foo', '1'));
        $this->assertEquals(['2','3'], $copy->getImportedIds('Foo'));
    }

    public function runClearTests(BackendInterface $registry)
    {
        $tree = new TreeNode('tag', ['name' => 'Foo']);
        $registry->reportImportedModule('Foo', '2');
        $registry->reportImportedRelation('ClassName', '2', 'Relation', [1, 2, 3]);
        $registry->setImportedTree('Foo', '1', $tree);
        $registry->addStep(new LoadModuleStep('Test', '2'), 10);

        $registry->clear();
        $this->assertEmpty($registry->getImportedIds('Foo'));
        $this->assertEmpty($registry->getRelationIds('ClassName', '2', 'Relation'));
        $this->assertNull($registry->getImportedTree('Foo', '1'));
        $this->assertNull($registry->getNextStep($prio));
    }
}
