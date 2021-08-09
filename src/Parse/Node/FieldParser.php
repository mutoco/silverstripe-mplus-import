<?php

namespace Mutoco\Mplus\Parse\Node;

use Mutoco\Mplus\Parse\Parser;

class FieldParser implements ParserInterface
{
    protected string $tag;
    protected string $value;
    protected int $startDepth;

    public function __construct(string $tagName)
    {
        $this->tag = $tagName;
        $this->startDepth = -1;
        $this->value = '';
    }

    public function handleElementStart(Parser $parser, string $name, array $attributes): ?ParserInterface
    {
        if ($this->tag === $name) {
            $this->startDepth = $parser->getDepth();
            $this->value = '';
            $node = $parser->getCurrent();
            // Merge the attributes of this node into the ones of the "parent".
            $node->setAttributes(array_merge($node->getAttributes(), $attributes));
        }

        return null;
    }

    public function handleCharacterData(Parser $parser, string $data)
    {
        if ($parser->getDepth() >= $this->startDepth) {
            $this->value .= $data;
        }
    }

    public function handleElementEnd(Parser $parser, string $name): bool
    {
        if ($this->tag === $name && $this->startDepth === $parser->getDepth()) {
            $parser->getCurrent()->setValue(
                preg_replace('{\s+}', ' ', trim($this->value))
            );
            return true;
        }

        return false;
    }

    public function handleDefault(Parser $parser, string $data)
    {
    }
}
