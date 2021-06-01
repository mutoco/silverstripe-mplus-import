<?php


namespace Mutoco\Mplus\Parse\Result;


class FieldResult extends AbstractResult
{
    protected string $value;
    protected string $name;
    protected string $type;

    public function __construct(string $tag, array $attributes, string $value)
    {
        parent::__construct($tag, $attributes);
        $this->value = $value;
        $this->name = $attributes['name'] ?? $tag;
        $this->type = $attributes['dataType'] ?? 'unknown';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString()
    {
        return sprintf('%s (%s): %s', $this->getName(), $this->getType(), $this->getValue());
    }
}
