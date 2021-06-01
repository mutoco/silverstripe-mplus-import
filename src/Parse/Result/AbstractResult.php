<?php


namespace Mutoco\Mplus\Parse\Result;


use Mutoco\Mplus\Serialize\SerializableTrait;

abstract class AbstractResult implements ResultInterface, \Serializable
{
    use SerializableTrait;
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

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->attributes = $obj->attributes;
        $this->tag = $obj->tag;
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->tag = $this->tag;
        $obj->attributes = $this->attributes;
        return $obj;
    }
}
