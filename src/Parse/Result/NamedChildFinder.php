<?php


namespace Mutoco\Mplus\Parse\Result;


use Tree\Node\NodeInterface;
use Tree\Visitor\Visitor;

/**
 * Finds a named item in a tree, doing a breadth first test.
 * All nodes that *are* named are automatically discarded. So it really only finds a descendant that has no other named
 * nodes inbetween!
 * @package Mutoco\Mplus\Parse\Result
 */
class NamedChildFinder implements Visitor
{
    protected string $name;

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * @inheritDoc
     */
    public function visit(NodeInterface $node): ?TreeNode
    {
        if ($node instanceof TreeNode && $node->getName() === $this->name) {
            return $node;
        }

        $unclear = [];
        foreach ($node->getChildren() as $child) {
            if ($child instanceof TreeNode && ($name = $child->getName())) {
                if ($name === $this->name) {
                    return $child;
                }
            } else {
                $unclear[] = $child;
            }
        }

        foreach ($unclear as $child) {
            if ($result = $child->accept($this)) {
                return $result;
            }
        }

        return null;
    }
}
