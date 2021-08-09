<?php

namespace Mutoco\Mplus\Parse\Result;

use Tree\Node\NodeInterface;
use Tree\Visitor\Visitor;

/**
 * Collect all nodes that reference external modules from a result-tree
 * @package Mutoco\Mplus\Parse\Result
 */
class ReferenceCollector implements Visitor
{
    public function visit(NodeInterface $node)
    {
        $nodes = [];

        if ($node instanceof TreeNode && $node->isReferenceNode()) {
            $nodes[] = $node;
        }

        foreach ($node->getChildren() as $child) {
            $nodes = array_merge(
                $nodes,
                $child->accept($this)
            );
        }

        return $nodes;
    }
}
