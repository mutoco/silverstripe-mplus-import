<?php


namespace Mutoco\Mplus\Parse\Result;


class ObjectResult extends AbstractResult
{
    protected string $type = 'unknown';
    protected array $fields = [];
    protected array $relations = [];
    protected ?string $id;

    public function __construct(string $tag, array $attributes)
    {
        parent::__construct($tag, $attributes);
        $this->id = $attributes['ID'] ?? $attributes['MODULEITEMID'] ?? null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getValue()
    {
        $fields = [];
        foreach ($this->fields as $field) {
            $fields[$field->getName()] = $field->getValue();
        }

        return [
            'id' => $this->id,
            'type' => $this->type,
            'attributes' => $this->attributes,
            'fields' => $fields
        ];
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
     * @return ObjectResult
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

    public function getRelations(): array
    {
        return $this->relations;
    }

    public function addField(FieldResult $field): self
    {
        $this->fields[] = $field;
        return $this;
    }

    public function addRelation(CollectionResult $result): self
    {
        $this->relations[] = $result;
        return $this;
    }
}
