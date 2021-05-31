<?php


namespace Mutoco\Mplus\Parse\Result;


abstract class AbstractResult implements ResultInterface
{
    protected string $tag;
    protected array $attributes;

    public function __construct(string $tag, array $attributes)
    {
        $this->tag = $tag;
        $this->attributes = $attributes;
    }

    public function getTag(): string
    {
        return $this->tag;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function __toString()
    {
        return json_encode([
            'tag' => $this->getTag(),
            'attributes' => $this->getAttributes(),
            'value' => (string)$this->getValue()
        ]);
    }

    abstract public function getValue();
}
