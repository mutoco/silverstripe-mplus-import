<?php


namespace Mutoco\Mplus\Tests;


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

        $root1 = new TreeNode();
        $root1->addChild($sub1 = new TreeNode('test', ['name' => 'Title']));
        $sub1->setValue('Sit amet');
        $refNode = $this->tree->getNestedNode('Foo.Bar');
        $refNode->getChildren()[0]->setSubTree($root1);
        $copy = unserialize(serialize($this->tree));
        $this->assertEquals('Sit amet', $copy->getNestedNode('Foo.Bar.Title')->getValue());
    }

    public function testSubtree()
    {
        $root1 = new TreeNode();
        $root1->addChild($sub1 = new TreeNode('test', ['name' => 'Title']));
        $sub1->setValue('Sit amet');
        $root2 = new TreeNode();
        $root2->addChild($sub2 = new TreeNode('test', ['name' => 'Group']));
        $sub2->addChild($s1 = new TreeNode('test', ['name' => 'Title']));
        $sub2->addChild($s2 = new TreeNode('test', ['name' => 'Author']));
        $s1->setValue('May the force be with you');
        $s2->setValue('Obi-Wan Kenobi');

        $refNode = $this->tree->getNestedNode('Foo.Bar');
        $refNode->getChildren()[0]->setSubTree($root1);
        $refNode->getChildren()[2]->setSubTree($root2);

        $this->assertEquals('Sit amet', $this->tree->getNestedNode('Foo.Bar.Title')->getValue());
        $this->assertEquals('May the force be with you', $this->tree->getNestedNode('Foo.Bar.Group.Title')->getValue());
    }
}
