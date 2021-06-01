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

    public function getAttribute($name): ?string
    {
        return $this->attributes[$name] ?? null;
    }

    public function __get(string $name)
    {
        return $this->getAttribute($name);
    }

    abstract public function getValue();
}
