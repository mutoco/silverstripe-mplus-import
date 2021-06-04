<?php


namespace Mutoco\Mplus\Import;


use Mutoco\Mplus\Parse\Node\CollectionParser;
use Mutoco\Mplus\Parse\Node\ObjectParser;
use Mutoco\Mplus\Parse\Node\ParserInterface;
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
    public function applyConfig(array $config): self
    {
        $this->config = [];

        foreach ($config as $module => $cfg) {
            $this->config[$module] = $this->getNormalizedModuleConfig($cfg);
        }

        return $this;
    }

    public function parserForModule(string $module): ParserInterface
    {
        if (!isset($this->config[$module])) {
            throw new \InvalidArgumentException(sprintf('Module %s is not part of the config', $module));
        }

        $cfg = $this->config[$module];
        $parser = new ObjectParser();
        $parser->setType($module);

        if (!empty($cfg['fields'])) {
            $parser->setFieldList(array_values($cfg['fields']));
        }

        foreach ($cfg['relations'] as $relationCfg) {
            $relationModule = $relationCfg['module'];
            $relationName = $relationCfg['name'];

            /** @var ObjectParser $objParser */
            $objParser = $this->parserForModule($relationModule);
            $tag = str_ends_with($relationName, 'Ref') ? 'moduleReference' : 'repeatableGroup';
            $objParser->setTag($tag . 'Item');
            $collectionParser = new CollectionParser($tag, $objParser);
            $parser->setRelationParser($relationName, $collectionParser);
        }

        return $parser;
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
                return $relationCfg['module'];
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
                    $relationModule = $relationName = $relationCfg;
                } else {
                    $relationModule = $relationCfg['module'];
                    $relationName = $relationCfg['name'];
                }
                $relations[$key] = [
                    'module' => $relationModule,
                    'name' => $relationName
                ];
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
