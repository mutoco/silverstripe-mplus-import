<?php


namespace Mutoco\Mplus\Parse\Result;


class FieldResult extends AbstractResult
{
    protected string $value;

    public function __construct(string $tag, array $attributes, string $value)
    {
        parent::__construct($tag, $attributes);
        $this->value = $value;
    }

    public function getName(): string
    {
        return $this->attributes['name'] ?? $this->getTag();
    }

    public function getType(): string
    {
        return $this->attributes['dataType'] ?? 'unknown';
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString()
    {
        return sprintf('%s (%s): %s', $this->getName(), $this->getType(), $this->getValue());
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = parent::getSerializableObject();
        $obj->value = $this->value;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->value = $obj->value;
        parent::unserializeFromObject($obj);
    }
}
