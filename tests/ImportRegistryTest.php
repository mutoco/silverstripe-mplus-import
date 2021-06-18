<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Import\MemoryImportRegistry;
use Mutoco\Mplus\Import\RegistryInterface;
use Mutoco\Mplus\Import\SqliteImportRegistry;
use Mutoco\Mplus\Parse\Result\TreeNode;
use SilverStripe\Dev\FunctionalTest;

class ImportRegistryTest extends FunctionalTest
{
    public function testTree()
    {
        $this->runTreeTests(new MemoryImportRegistry());
        $this->runTreeTests(new SqliteImportRegistry());
    }

    public function testRelation()
    {
        $this->runRelationTests(new MemoryImportRegistry());
        $this->runRelationTests(new SqliteImportRegistry());
    }

    public function testModules()
    {
        $this->runModuleTests(new MemoryImportRegistry());
        $this->runModuleTests(new SqliteImportRegistry());
    }

    public function testClear()
    {
        $this->runClearTests(new MemoryImportRegistry());
        $this->runClearTests(new SqliteImportRegistry());
    }

    protected function runTreeTests(RegistryInterface $registry)
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

    protected function runRelationTests(RegistryInterface $registry)
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

    protected function runModuleTests(RegistryInterface $registry)
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

    public function runClearTests(RegistryInterface $registry)
    {
        $tree = new TreeNode('tag', ['name' => 'Foo']);
        $registry->reportImportedModule('Foo', '2');
        $registry->reportImportedRelation('ClassName', '2', 'Relation', [1, 2, 3]);
        $registry->setImportedTree('Foo', '1', $tree);

        $registry->clear();
        $this->assertEmpty($registry->getImportedIds('Foo'));
        $this->assertEmpty($registry->getRelationIds('ClassName', '2', 'Relation'));
        $this->assertNull($registry->getImportedTree('Foo', '1'));
    }
}
