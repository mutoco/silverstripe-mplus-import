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

    public function getValue(): string
    {
        return $this->value;
    }
}
