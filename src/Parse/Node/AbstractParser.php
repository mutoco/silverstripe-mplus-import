<?php


namespace Mutoco\Mplus\Parse\Node;


use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\ResultInterface;
use Sabre\Event\Emitter;

abstract class AbstractParser extends Emitter implements ParserInterface
{
    protected string $tag;
    protected string $tagUc;
    protected string $characters;
    protected int $depth;
    protected bool $isComplete;
    protected array $attributes;
    protected int $startDepth;
    protected int $iteration = -1;

    public function __construct(string $tagName)
    {
        $this->tag = $tagName;
        $this->tagUc = strtoupper($tagName);
        $this->reset();
    }

    public function reset()
    {
        $this->iteration++;
        $this->characters = '';
        $this->depth = 0;
        $this->startDepth = -1;
        $this->isComplete = false;
        $this->attributes = [];
    }

    public function handleCharacterData(Parser $parser, string $data)
    {
        $this->characters .= $data;
    }

    public function handleElementStart(Parser $parser, string $name, array $attributes)
    {
        if ($this->startDepth === -1 && $name === $this->tagUc) {
            $this->startDepth = $this->depth;
            $this->characters = '';
            $this->attributes = $attributes;
        }

        $this->depth++;
    }

    public function handleElementEnd(Parser $parser, string $name)
    {
        $this->depth--;

        if ($this->startDepth === $this->depth && $name === $this->tagUc) {
            $this->isComplete = true;
            $this->emit('parse:complete', [$this->getValue()]);
            $this->reset();
        }
    }

    public function handleDefault(Parser $parser, string $data)
    {
        // Does nothing, implement in subclass if needed
    }

    public function isComplete(): bool
    {
        return $this->isComplete;
    }

    abstract public function getValue(): ResultInterface;
}
