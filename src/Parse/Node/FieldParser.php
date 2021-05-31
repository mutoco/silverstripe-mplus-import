<?php


namespace Mutoco\Mplus\Parse\Node;


use Mutoco\Mplus\Parse\Parser;
use Mutoco\Mplus\Parse\Result\FieldResult;
use Mutoco\Mplus\Parse\Result\ResultInterface;

class FieldParser extends AbstractParser
{
    protected int $subState;
    protected string $value;
    protected string $valueTag;

    public function __construct(string $tagName, string $valueTag = 'VALUE')
    {
        parent::__construct($tagName);
        $this->valueTag = $valueTag;
    }

    public function reset()
    {
        parent::reset();
        $this->value = '';
        $this->subState = self::STATE_SEEKING;
    }

    public function handleElementStart(Parser $parser, string $name, array $attributes)
    {
        parent::handleElementStart($parser, $name, $attributes);

        if ($this->subState === self::STATE_SEEKING && $this->isInside()) {
            if ($name === $this->valueTag) {
                $this->subState = self::STATE_PARSING;
                $this->value = '';
            }
        }
    }

    public function handleCharacterData(Parser $parser, string $data)
    {
        if ($this->subState === self::STATE_PARSING) {
            $this->value .= $data;
        }
    }

    public function handleElementEnd(Parser $parser, string $name)
    {
        if ($this->subState === self::STATE_PARSING && $this->isInside()) {
            if ($name === $this->valueTag) {
                $this->subState = self::STATE_DONE;
            }
        }

        parent::handleElementEnd($parser, $name);
    }

    public function getValue(): ResultInterface
    {
        return new FieldResult($this->tag, $this->attributes, preg_replace('{\s+}', ' ', trim($this->value)));
    }
}
