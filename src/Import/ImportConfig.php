<?php


namespace Mutoco\Mplus\Import;

use Mutoco\Mplus\Serialize\SerializableTrait;
use SilverStripe\Core\Config\Config;

class ImportConfig implements \Serializable
{
    use SerializableTrait;

    protected array $config;

    public function __construct(array $config = [])
    {
        $this->applyConfig($config);
    }

    public function getImportPaths(string $module, string $prefix = ''): array
    {
        $cfg = $this->getModuleConfig($module);
        $paths = [];
        if (isset($cfg['fields'])) {
            foreach ($cfg['fields'] as $field) {
                $paths[] = $prefix . $field;
            }
        }

        if (isset($cfg['relations'])) {
            foreach ($cfg['relations'] as $relation) {
                if (isset($relation['fields'])) {
                    foreach ($relation['fields'] as $field) {
                        $paths[] = $prefix . $field;
                    }
                }
                $paths = array_merge($paths, $this->getImportPaths($relation['type'], $prefix . $relation['name'] . '.'));
            }
        }

        return $paths;
    }

    /**
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Set a config that gets normalized
     * @param array $config
     * @return ImportConfig
     */
    public function applyConfig(array $config, bool $merge = false): self
    {
        if (!$merge) {
            $this->config = [];
        }

        foreach ($config as $module => $cfg) {
            $this->config[$module] = array_merge_recursive(
                $this->config[$module] ?? [], $this->getNormalizedModuleConfig($cfg)
            );
        }

        return $this;
    }

    public function getModuleConfig(string $module): array
    {
        return $this->config[$module] ?? [];
    }

    public function getRelationsForModule(string $module): array
    {
        $cfg = $this->getModuleConfig($module);
        return $cfg['relations'] ?? [];
    }

    public function getFieldsForModule(string $module): array
    {
        $cfg = $this->getModuleConfig($module);
        return $cfg['fields'] ?? [];
    }

    public function getRelationModule(string $module, string $relationName): ?string
    {
        $relations = $this->getRelationsForModule($module);
        foreach ($relations as $relationCfg) {
            if ($relationCfg['name'] === $relationName) {
                return $relationCfg['type'];
            }
        }
        return null;
    }

    protected function getNormalizedModuleConfig(array $cfg): array
    {
        return array_merge(
            $cfg,
            ['fields' => $this->getNormalizedFieldConfig($cfg)],
            ['relations' => $this->getNormalizedRelationConfig($cfg)],
        );
    }

    protected function getNormalizedFieldConfig(array $cfg): array
    {
        $fields = [];
        if (isset($cfg['fields'])) {
            $fields = array_merge($fields, $cfg['fields']);
        }

        $modelClass = $cfg['modelClass'] ?? null;
        if ($modelClass && ($modelFields = Config::inst()->get($modelClass, 'mplus_import_fields'))) {
            $fields = array_merge($fields, $modelFields);
        }

        return $fields;
    }

    protected function getNormalizedRelationConfig(array $cfg): array
    {
        $relations = [];

        if (isset($cfg['relations'])) {
            foreach ($cfg['relations'] as $key => $relationCfg) {
                if (is_string($relationCfg)) {
                    $relationCfg = [
                        'type' => $relationCfg,
                        'name' => $relationCfg
                    ];
                }
                $relations[$key] = $relationCfg;
            }
        }

        return $relations;
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();
        $obj->config = $this->config;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->config = $obj->config;
    }
}
