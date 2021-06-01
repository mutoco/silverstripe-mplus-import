<?php


namespace Mutoco\Mplus\Parse\Node;


use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\ResultInterface;
use Sabre\Event\Emitter;

abstract class AbstractParser extends Emitter implements ParserInterface
{
    const STATE_SEEKING = 1;
    const STATE_PARSING = 2;
    const STATE_DONE = 4;

    protected ?Parser $parser;
    protected string $tag;
    protected string $tagUc;
    protected array $attributes;
    protected int $startDepth;
    protected int $state;
    protected int $iteration = -1;

    public function __construct(string $tagName)
    {
        $this->tag = $tagName;
        $this->tagUc = strtoupper($tagName);
        $this->reset();
    }

    public function getTag($uppercase = false): string
    {
        if ($uppercase) {
            return $this->tagUc;
        }

        return $this->tag;
    }

    abstract public function getValue(): ResultInterface;

    public function reset()
    {
        $this->iteration++;
        $this->state = self::STATE_SEEKING;
        $this->startDepth = -1;
        $this->attributes = [];
    }

    public function isInside(): bool
    {
        return $this->state === self::STATE_PARSING;
    }

    public function handleElementStart(Parser $parser, string $name, array $attributes)
    {
        if ($this->state === self::STATE_SEEKING && $name === $this->tagUc) {
            $this->state = self::STATE_PARSING;
            $this->startDepth = $parser->getDepth();
            $this->attributes = $attributes;
            $this->onEnter($parser);
        }
    }

    public function handleElementEnd(Parser $parser, string $name)
    {
        if ($this->state === self::STATE_PARSING && $this->startDepth === $parser->getDepth() && $name === $this->tagUc) {
            $this->emit('parse:result', [$this->getValue()]);
            $this->state = self::STATE_DONE;
            $this->onLeave($parser);
        }
    }

    public function handleCharacterData(Parser $parser, string $data) {
        // Does nothing, implement in subclass if needed
    }

    public function handleDefault(Parser $parser, string $data)
    {
        // Does nothing, implement in subclass if needed
    }

    protected function onEnter(Parser $parser)
    {
        $this->parser = $parser;
        $this->emit('parse:enter', [$this]);
    }

    protected function onLeave(Parser $parser)
    {
        $this->emit('parse:complete', [$this]);
        $this->parser = null;
        $this->reset();
    }
}
