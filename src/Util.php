<?php


namespace Mutoco\Mplus;


use Tree\Node\Node;
use Tree\Visitor\YieldVisitor;

class Util
{
    public static function isAssoc(array $arr): bool
    {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    public static function pathsToTree(array $paths): Node
    {
        $tree = new Node();

        foreach ($paths as $path) {
            $parts = explode('.', $path);
            $node = $tree;
            foreach ($parts as $part) {
                $found = false;
                foreach ($node->getChildren() as $child) {
                    if ($child->getValue() === $part) {
                        $found = true;
                        $node = $child;
                        break;
                    }
                }
                if (!$found) {
                    $node->addChild($node = new Node($part));
                }
            }
        }

        return $tree;
    }

    public static function findNodeForPath($value, Node $tree): ?Node
    {
        if ($tree === null) {
            throw new \InvalidArgumentException('Tree must be a Node instance');
        }

        if (is_string($value)) {
            $value = explode('.', $value);
        }

        if (empty($value)) {
            return null;
        }

        $node = $tree;
        for ($i = 0; $i < count($value); $i++) {
            $segment = $value[$i];
            $found = false;
            foreach ($node->getChildren() as $child) {
                if ($child->getValue() === $segment) {
                    $node = $child;
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                return null;
            }
        }

        return $node;
    }

    public static function isValidPath($value, Node $tree): bool
    {
        return self::findNodeForPath($value, $tree) !== null;
    }

    public static function treeToPaths(Node $tree): array
    {
        $result = [];
        $visitor = new YieldVisitor();
        $leaves = $tree->accept($visitor);
        /** @var Node $leaf */
        foreach ($leaves as $leaf) {
            $segments = array_map(function ($node) {
                return $node->getValue();
            }, $leaf->getAncestorsAndSelf());
            $result[] = implode('.', array_filter($segments));
        }
        return $result;
    }
}
