<?php


namespace Mutoco\Mplus\Serialize;


trait SerializableTrait
{
    public function serialize()
    {
        return serialize($this->getSerializableObject());
    }

    public function unserialize($data)
    {
        $this->unserializeFromObject(unserialize($data));
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {

    }

    protected function getSerializableObject(): \stdClass
    {
        return new \stdClass();
    }
}
