<?php


namespace Mutoco\Mplus\Parse\Result;


class ModuleResult extends AbstractResult
{
    public string $type;
    public array $fields = [];
    protected ?string $id;

    public function __construct(string $tag, array $attributes)
    {
        parent::__construct($tag, $attributes);
        $this->id = $attributes['ID'] ?? null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getValue()
    {
        // TODO: Implement getValue() method.
    }
}
