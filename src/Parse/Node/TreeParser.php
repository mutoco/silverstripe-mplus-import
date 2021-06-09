<?php


namespace Mutoco\Mplus\Parse\Node;


use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\TreeNode;

class TreeParser implements ParserInterface
{
    protected string $tag;
    protected array $attributes;
    protected \SplStack $depths;

    protected array $fieldTags = [
        'systemField' => 'value',
        'dataField' => 'value',
        'virtualField' => 'value'
    ];

    protected array $collectionTags = [
        'repeatableGroupItem' => true,
        'moduleReferenceItem' => true,
        'moduleItem' => true
    ];

    public function __construct()
    {
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
        if (
            (isset($attributes['name']) && $parser->isAllowedNext($attributes['name'])) ||
            (isset($this->collectionTags[$name]) && $parser->isAllowedPath($parser->getCurrent()->getPathSegments()))
        ) {
            $this->depths->push($parser->getDepth());
            $node = new TreeNode();
            $node->setTag($name);
            $node->setAttributes($attributes);
            $parser->addNode($node);
        }

        if (!$this->depths->isEmpty() && $this->depths->top() >= $parser->getDepth() && isset($this->fieldTags[$name])) {
            return new FieldParser($this->fieldTags[$name]);
        }

        return null;
    }

    public function handleElementEnd(Parser $parser, string $name): bool
    {
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
