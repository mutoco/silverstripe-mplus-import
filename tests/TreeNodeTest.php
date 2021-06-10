<?php


namespace Mutoco\Mplus\Tests;


use Mutoco\Mplus\Parse\Result\NamedChildFinder;
use Mutoco\Mplus\Parse\Result\ReferenceCollector;
use Mutoco\Mplus\Parse\Result\TreeNode;
use SilverStripe\Dev\FunctionalTest;

class TreeNodeTest extends FunctionalTest
{
    protected TreeNode $tree;

    protected function setUp()
    {
        $tree = new TreeNode('foo', ['name' => 'Foo']);
        $tree->addChild($child1 = new TreeNode('child', ['name' => 'Bar', 'targetModule' => 'Test']));
        $tree->addChild($child2 = new TreeNode('child', ['name' => 'Baz']));
        $child1->addChild(new TreeNode('moduleReferenceItem', ['moduleItemId' => 1]));
        $child1->addChild(new TreeNode('moduleReferenceItem', ['moduleItemId' => 2]));
        $child1->addChild(new TreeNode('moduleReferenceItem', ['moduleItemId' => 3]));
        $child2->addChild($textNode = new TreeNode('node', ['name' => 'Text']));
        $child2->addChild($authorNode = new TreeNode('node', ['name' => 'Author']));
        $textNode->setValue('Lorem ipsum dolor');
        $authorNode->setValue('Han Solo');
        $this->tree = $tree;
        parent::setUp();
    }

    public function testSerialization()
    {
        /** @var TreeNode $copy */
        $copy = unserialize(serialize($this->tree));
        $this->assertCount(2, $copy->getChildren());
        /** @var TreeNode $ref */
        $ref = $copy->getNestedNode('Foo.Bar');
        $this->assertTrue($ref->getChildren()[0]->isReferenceNode());
        $this->assertEquals($copy->getNestedNode('Foo'), $ref->getParent());
        $this->assertEquals('Lorem ipsum dolor', $copy->getNestedNode('Foo.Baz.Text')->getValue());

        $sub1 = new TreeNode('test', ['name' => 'Title']);
        $sub1->setValue('Sit amet');
        $refNode = $this->tree->getNestedNode('Foo.Bar');
        $refNode->getChildren()[0]->addChild($sub1);
        $copy = unserialize(serialize($this->tree));
        $this->assertEquals('Sit amet', $copy->getNestedNode('Foo.Bar.Title')->getValue());
    }

    public function testSubtree()
    {
        $sub1 = new TreeNode('test', ['name' => 'Title']);
        $sub1->setValue('Sit amet');

        $sub2 = new TreeNode('test');
        $sub2->addChild($sA = new TreeNode('groupA', ['name' => 'Group']));
        $sub2->addChild($sB = new TreeNode('groupB', ['name' => 'Group']));
        $sA->addChild($s1 = new TreeNode('testA', ['name' => 'Title']));
        $sA->addChild($s2 = new TreeNode('testA', ['name' => 'Author']));
        $sB->addChild($s3 = new TreeNode('testB', ['name' => 'Title']));
        $s1->setValue('May the force be with you');
        $s2->setValue('Obi-Wan Kenobi');
        $s3->setValue('The joker');

        $refNode = $this->tree->getNestedNode('Foo.Bar');
        $refNode->getChildren()[0]->addChild($sub1);
        $refNode->getChildren()[2]->addChild($sub2);

        $this->assertEquals('Sit amet', $this->tree->getNestedNode('Foo.Bar.Title')->getValue());
        $this->assertEquals('May the force be with you', $this->tree->getNestedNode('Foo.Bar.Group.Title')->getValue());
        $this->assertEquals('groupA', $this->tree->getNestedNode('Foo.Bar.Group')->getTag());

        $this->assertEquals(['foo'], array_map(function ($node) { return $node->getTag(); }, $this->tree->getNodesMatchingPath('Foo')));
        $this->assertEquals(['child'], array_map(function ($node) { return $node->getTag(); }, $this->tree->getNodesMatchingPath('Foo.Bar')));
        $this->assertEquals(['groupA', 'groupB'], array_map(function ($node) { return $node->getTag(); }, $this->tree->getNodesMatchingPath('Foo.Bar.Group')));
        $this->assertEquals(['May the force be with you', 'The joker'], array_map(function ($node) { return $node->getValue(); }, $this->tree->getNodesMatchingPath('Foo.Bar.Group.Title')));
    }

    public function testReferenceCollector()
    {
        $visitor = new ReferenceCollector();
        $nodes = $this->tree->accept($visitor);
        $this->assertCount(3, $nodes);
        $ids = array_map(function ($item) { return $item->moduleItemId; }, $nodes);
        $this->assertEquals([1,2,3], $ids);
        $this->assertEquals('Test', $nodes[0]->getModuleName());
    }

    public function testAccessors()
    {
        $this->assertEquals('Test' ,$this->tree->getNestedValue('Foo.Bar.targetModule'));
        $this->assertEquals('Han Solo', $this->tree->getNestedValue('Foo.Baz.Author'));
        $this->assertEquals('Test', $this->tree->getNestedNode('Foo.Bar')->targetModule);
        $node = $this->tree->getNestedNode('Foo.Bar');
        $this->assertEquals('Test', $node->getNestedValue('targetModule'));
        $this->assertEquals('Test', $node->targetModule);
    }

    public function testChildFinder()
    {
        $finder = new NamedChildFinder('Text');
        $result = $finder->visit($this->tree);
        $this->assertNull($result);

        $node = $this->tree->getNestedNode('Foo.Bar');
        $node->getChildren()[1]->addChild($anon1 = new TreeNode('test'));
        $anon1->addChild($dummy = new TreeNode('dummy'));
        $dummy->addChild(new TreeNode('dummy', ['name' => 'Nested']));
        $anon1->addChild($named = new TreeNode('dummy', ['name' => 'Nested']));
        $named->setValue('Hi!');

        $finder = new NamedChildFinder('Nested');
        $result = $finder->visit($this->tree);
        $this->assertNull($result);
        $result = $finder->visit($anon1);
        $this->assertEquals('Hi!', $result->getValue());
    }
}
