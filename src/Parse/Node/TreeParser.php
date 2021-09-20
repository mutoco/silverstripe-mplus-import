<?php

namespace Mutoco\Mplus\Parse\Node;

use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\TreeNode;

class TreeParser implements ParserInterface
{
    protected string $tag;
    protected array $attributes;
    protected \SplStack $depths;
    protected int $ignoredDepth = -1;

    protected array $fieldTags = [
        'systemField' => 'value',
        'dataField' => 'value',
        'virtualField' => 'value',
        'vocabularyReferenceItem' => 'formattedValue'
    ];

    protected array $collectionTags = [
        'repeatableGroupItem' => true,
        'moduleReferenceItem' => true,
        'moduleItem' => true,
        'vocabularyReferenceItem' => true
    ];

    public function __construct()
    {
        $this->ignoredDepth = -1;
        $this->depths = new \SplStack();
    }

    /**
     * @return string[]
     */
    public function getFieldTags(): array
    {
        return $this->fieldTags;
    }

    /**
     * @param string[] $fields
     * @return TreeParser
     */
    public function setFieldTags(array $fields): self
    {
        $this->fieldTags = $fields;
        return $this;
    }

    /**
     * @return string[]
     */
    public function getCollectionTags(): array
    {
        return array_keys($this->collectionTags);
    }

    /**
     * @param string[] $collections
     * @return TreeParser
     */
    public function setCollectionTags(array $collections): self
    {
        $this->collectionTags = array_flip($collections);
        return $this;
    }

    public function handleElementStart(Parser $parser, string $name, array $attributes): ?ParserInterface
    {
        // Skip if we're ignoring a certain tree branch
        if ($this->ignoredDepth >= 0) {
            return null;
        }

        $allowedNext = false;
        if (isset($attributes['name'])) {
            // Check if the named node is allowed as next node
            $allowedNext = $parser->isAllowedNext($attributes['name']);

            if (!$allowedNext) {
                // If we encountered a named node that is not matching the allowed paths, we can discard
                // the entire sub-tree. Setting the ignored depth to the current depth
                $this->ignoredDepth = $parser->getDepth();
            }
        }

        if (
            $allowedNext || (
            // Also valid are all collection tags that are nested 1 level into the current node
            isset($this->collectionTags[$name]) &&
            !$this->depths->isEmpty() &&
            $parser->getDepth() === $this->depths->top() + 1 &&
            $parser->isAllowedPath($parser->getCurrent()->getPathSegments())
            )
        ) {
            $this->depths->push($parser->getDepth());
            $node = new TreeNode();
            $node->setTag($name);
            $node->setAttributes($attributes);
            $parser->addNode($node);
            // Reset the ignored depth, since there was an exception to the rule
            $this->ignoredDepth = -1;
        }

        if (
            !$this->depths->isEmpty() &&
            $this->depths->top() >= $parser->getDepth() &&
            isset($this->fieldTags[$name])
        ) {
            // Reset the ignored depth, since there was an exception to the rule
            $this->ignoredDepth = -1;
            return new FieldParser($this->fieldTags[$name]);
        }

        return null;
    }

    public function handleElementEnd(Parser $parser, string $name): bool
    {
        if ($parser->getDepth() <= $this->ignoredDepth) {
            $this->ignoredDepth = -1;
            return false;
        }

        if ($name === $parser->getCurrent()->getTag() && $this->depths->top() === $parser->getDepth()) {
            $this->depths->pop();
            $parser->popNode();
        }

        return false;
    }

    public function handleCharacterData(Parser $parser, string $data)
    {
    }

    public function handleDefault(Parser $parser, string $data)
    {
    }
}
