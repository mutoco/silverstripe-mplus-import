<?php


namespace Mutoco\Mplus\Parse\Result;


use Mutoco\Mplus\Parse\Node\ObjectParser;

class ObjectResult extends AbstractResult
{
    protected array $fields = [];
    protected array $relations = [];
    protected ?string $id;
    protected string $type = ObjectParser::TYPE_UNKNOWN;

    public function __construct(string $tag, array $attributes)
    {
        parent::__construct($tag, $attributes);
        $this->id = $attributes['id'] ?? $attributes['moduleItemId'] ?? null;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getValue(): array
    {
        $fields = [];
        foreach ($this->fields as $name => $field) {
            $fields[$name] = $field->getValue();
        }

        foreach ($this->relations as $name => $relation){
            $fields[$name] = $relation->getValue();
        }

        return $fields;
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

    public function getFieldResult($name): ?FieldResult
    {
        return $this->fields[$name] ?? null;
    }

    public function getRelations(): array
    {
        return $this->relations;
    }

    public function getRelationResult($name): ?CollectionResult
    {
        return $this->relations[$name] ?? null;
    }

    public function addField(FieldResult $field): self
    {
        $this->fields[$field->getName()] = $field;
        return $this;
    }

    public function addRelation(CollectionResult $result): self
    {
        $this->relations[$result->getName()] = $result;
        return $this;
    }

    public function __get(string $name)
    {
        if ($field = $this->getFieldResult($name)) {
            return $field->getValue();
        }

        if ($attr = parent::__get($name)) {
            return $attr;
        }

        if ($collection = $this->getRelationResult($name)) {
            return $collection->getItems();
        }

        return null;
    }

}
