<?php

namespace Mutoco\Mplus\Tests;

use Mutoco\Mplus\Util;
use SilverStripe\Dev\FunctionalTest;
use Tree\Visitor\PreOrderVisitor;

class UtilTest extends FunctionalTest
{
    public function testIsAssoc()
    {
        $this->assertTrue(Util::isAssoc(['test', 'foo' => 'bar', false => '2']));
        $this->assertFalse(Util::isAssoc(['test', 'foo', 2]));
    }

    public function testPathsToTree()
    {
        $tree = Util::pathsToTree([
            'Foo.Baz.Lorem',
            'Foo.Bar',
            'Foo.Baz.Ipsum.Dolor',
            'Foo.Baz.Sit.Amet',
            '42'
        ]);

        $this->assertCount(2, $tree->getChildren());
        $visitor = new PreOrderVisitor();
        $nodes = $tree->accept($visitor);
        $values = array_map(function ($node) {
            return $node->getValue();
        }, $nodes);

        $this->assertEquals([
            null,
            'Foo',
            'Baz',
            'Lorem',
            'Ipsum',
            'Dolor',
            'Sit',
            'Amet',
            'Bar',
            '42'
        ], $values);

        $childCounts = array_map(function ($node) {
            return count($node->getChildren());
        }, $nodes);

        $this->assertEquals([
            2, // Root has Foo and 42
            2, // Foo has Bar and Baz
            3, // Baz has Lorem, Ipsum and Sit
            0, // Lorem is leaf
            1, // Ipsum has Dolor
            0, // Dolor is leaf
            1, // Sit has Amet
            0, // Amet is leaf
            0, // Bar is leaf
            0 // 42 is leaf
        ], $childCounts);

        $this->assertCount(0, Util::pathsToTree([])->getChildren());
        $this->assertCount(0, Util::pathsToTree([], 'Dude')->getChildren());
    }

    public function testPathsToTreeWithPrefix()
    {
        $tree = Util::pathsToTree([
            'Foo.Baz.Lorem',
            'Foo.Bar',
            'Foo.Baz.Ipsum.Dolor',
            '42'
        ], 'Hello.World');

        $this->assertEquals('Hello', $tree->getChildren()[0]->getValue());
        $this->assertEquals('World', $tree->getChildren()[0]->getChildren()[0]->getValue());

        $this->assertEquals([
            'Hello.World.Foo.Baz.Lorem',
            'Hello.World.Foo.Baz.Ipsum.Dolor',
            'Hello.World.Foo.Bar',
            'Hello.World.42'
        ], Util::treeToPaths($tree));
    }

    public function testIsValid()
    {
        $tree = Util::pathsToTree([
            'Foo.Baz.Lorem',
            'Foo.Bar',
            'Foo.Baz.Ipsum.Dolor',
            'Foo.Baz.Sit.Amet',
            '42'
        ]);

        $this->assertTrue(Util::isValidPath('Foo', $tree));
        $this->assertTrue(Util::isValidPath('Foo.Baz', $tree));
        $this->assertTrue(Util::isValidPath('Foo.Baz.Ipsum.Dolor', $tree));
        $this->assertTrue(Util::isValidPath('42', $tree));

        $this->assertFalse(Util::isValidPath('', $tree));
        $this->assertFalse(Util::isValidPath('Baz.Ipsum', $tree));
        $this->assertFalse(Util::isValidPath('41', $tree));
        $this->assertFalse(Util::isValidPath('Foo.Test', $tree));

        $subNode = $tree->getChildren()[0];
        $this->assertEquals('Foo', $subNode->getValue());
        $this->assertTrue(Util::isValidPath('Baz', $subNode));
    }

    public function testTreeToPaths()
    {
        $tree = Util::pathsToTree([
            'Foo.Baz.Lorem',
            'Foo.Bar',
            'Foo.Baz.Ipsum.Dolor'
        ]);

        $this->assertEquals([
            'Foo.Baz.Lorem',
            'Foo.Baz.Ipsum.Dolor',
            'Foo.Bar',
        ], Util::treeToPaths($tree));
    }

    public function testClone()
    {
        $tree = Util::pathsToTree([
            'Foo.Baz.Lorem',
            'Foo.Bar',
            'Foo.Baz.Ipsum.Dolor'
        ]);

        $copy = Util::cloneTree($tree);

        $this->assertEquals([
            'Foo.Baz.Lorem',
            'Foo.Baz.Ipsum.Dolor',
            'Foo.Bar',
        ], Util::treeToPaths($copy));

        $copy = Util::cloneTree($tree->getChildren()[0]->getChildren()[0]);
        $this->assertEquals([
            'Baz.Lorem',
            'Baz.Ipsum.Dolor',
        ], Util::treeToPaths($copy));
    }

    public function testSearchPaths()
    {
        $tree = Util::pathsToTree([
            'ExhPerOrganiserRef',
            'ExhTextGrp.TextClb',
            'ExhRoomTxt'
        ]);

        $fields = Util::getSearchPaths($tree);

        $this->assertEquals([
            'ExhPerOrganiserRef.moduleReferenceItem',
            'ExhTextGrp.TextClb',
            'ExhTextGrp.repeatableGroupItem',
            'ExhRoomTxt'
        ], $fields);
    }
}
