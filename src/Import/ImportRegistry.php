<?php


namespace Mutoco\Mplus\Import;


use Mutoco\Mplus\Serialize\SerializableTrait;

class ImportRegistry implements \Serializable
{
    use SerializableTrait;

    protected array $modules = [];

    public function reportImportedModule(string $name, string $id): void
    {
        if (!isset($this->modules[$name])) {
            $this->modules[$name] = [$id];
        } else {
            $this->modules[$name][] = $id;
        }
    }

    public function hasImportedModule(string $name, ?string $id = null): bool
    {
        if (!isset($this->modules[$name])) {
            return false;
        }

        if ($id !== null) {
            return in_array($id, $this->modules[$name], true);
        }

        return true;
    }

    public function getImportedIds(string $module): array
    {
        if (!isset($this->modules[$module])) {
            return [];
        }

        return $this->modules[$module];
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->modules = $this->modules;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->modules = $obj->modules;
    }
}
