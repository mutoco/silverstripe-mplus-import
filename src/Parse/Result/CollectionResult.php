<?php


namespace Mutoco\Mplus\Parse\Result;


class CollectionResult extends AbstractResult
{
    protected array $items = [];

    public function addItem(ResultInterface $result)
    {
        $this->items[] = $result;
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getValue()
    {
        return [
            'tag' => $this->tag,
            'attributes' => $this->attributes,
            'count' => $this->count(),
            'items' => $this->items
        ];
    }
}
