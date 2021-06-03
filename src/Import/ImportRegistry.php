<?php


namespace Mutoco\Mplus\Import;


use Mutoco\Mplus\Serialize\SerializableTrait;

class ImportRegistry implements \Serializable
{
    use SerializableTrait;

    protected array $modules = [];
    protected array $relations = [];

    public function reportImportedRelation(string $class, string $name, array $ids)
    {
        $key = $class . '.' . $name;
        $this->relations[$key] = $ids;
    }

    public function hasImportedRelation(string $class, string $name): bool
    {
        $key = $class . '.' . $name;
        return isset($this->relations[$key]);
    }

    public function getRelationIds(string $class, string $name): array
    {
        $key = $class . '.' . $name;
        return $this->relations[$key] ?? [];
    }

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
        return $this->modules[$module] ?? [];
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->modules = $this->modules;
        $obj->relations = $this->relations;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->modules = $obj->modules;
        $this->relations = $obj->relations;
    }
}
