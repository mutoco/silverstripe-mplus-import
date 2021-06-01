<?php


namespace Mutoco\Mplus\Parse\Result;


class ModuleResult extends AbstractResult
{
    protected string $type;
    protected array $fields = [];
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

    /**
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @param string $type
     * @return ModuleResult
     */
    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return array
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    public function addField(FieldResult $field): self
    {
        $this->fields[] = $field;
        return $this;
    }
}
