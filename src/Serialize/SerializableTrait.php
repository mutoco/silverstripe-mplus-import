<?php

namespace Mutoco\Mplus\Serialize;

trait SerializableTrait
{
    public function __serialize(): array
    {
        return $this->getSerializableArray();
    }

    public function __unserialize(array $data)
    {
        $this->unserializeFromArray($data);
    }

    public function serialize(): ?string
    {
        return serialize($this->getSerializableObject());
    }

    public function unserialize($data)
    {
        $this->unserializeFromObject(unserialize($data));
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->unserializeFromArray($obj->data);
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->data = $this->getSerializableArray();
        return $obj;
    }

    protected function unserializeFromArray(array $data): void
    {
    }

    protected function getSerializableArray(): array
    {
        return [];
    }
}
