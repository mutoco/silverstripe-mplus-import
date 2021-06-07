<?php


namespace Mutoco\Mplus\Parse\Node;


use Mutoco\Mplus\Parse\Parser;

class TreeParser implements ParserInterface
{
    protected string $tag;
    protected array $attributes;
    protected \SplStack $depths;

    protected array $fields = [
        'systemField' => 'value',
        'dataField' => 'value',
        'virtualField' => 'value'
    ];

    protected array $collections = [
        'repeatableGroupItem' => true,
        'moduleReferenceItem' => true
    ];

    public function __construct()
    {
        $this->depths = new \SplStack();
    }

    public function handleElementStart(Parser $parser, string $name, array $attributes): ?ParserInterface
    {
        if (isset($attributes['name']) || isset($this->collections[$name])) {
            $this->depths->push($parser->getDepth());
            $node = $parser->pushStack();
            $node->setTag($name);
            $node->setAttributes($attributes);
        }

        if (!$this->depths->isEmpty() && $this->depths->top() >= $parser->getDepth() && isset($this->fields[$name])) {
            return new FieldParser($this->fields[$name]);
        }

        return null;
    }

    public function handleElementEnd(Parser $parser, string $name): bool
    {
        if ($name === $parser->getCurrent()->getTag() && $this->depths->top() === $parser->getDepth()) {
            $this->depths->pop();
            $parser->popStack();
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
