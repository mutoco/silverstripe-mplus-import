<?php


namespace Mutoco\Mplus\Parse\Result;


class CollectionResult extends AbstractResult
{
    protected array $items = [];

    public function getName(): ?string
    {
        return $this->attributes['name'] ?? null;
    }

    public function addItem(ResultInterface $result)
    {
        $this->items[] = $result;
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return ResultInterface[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getValue(): array
    {
        return $this->getItems();
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = parent::getSerializableObject();
        $obj->items = $this->items;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->items = $obj->items;
        parent::unserializeFromObject($obj);
    }
}
