<?php


namespace Mutoco\Mplus\Parse;


use Mutoco\Mplus\Parse\Node\CollectionParser;
use Mutoco\Mplus\Parse\Node\ObjectParser;
use Mutoco\Mplus\Parse\Node\ParserInterface;
use SilverStripe\Core\Config\Config;

class Util
{
    static function parserFromConfig(array $moduleCfg, string $module): ParserInterface
    {
        $cfg = self::getModuleConfig($moduleCfg, $module);

        $parser = new ObjectParser();
        $parser->setType($module);

        $fields = self::getNormalizedFieldConfig($moduleCfg, $module);

        if ($fields) {
            $parser->setFieldList(array_values($fields));
        }

        $relations = self::getNormalizedRelationConfig($moduleCfg, $module);
        foreach ($relations as $relationCfg) {
            $relationModule = $relationCfg['module'];
            $relationName = $relationCfg['name'];

            /** @var ObjectParser $objParser */
            $objParser = self::parserFromConfig($moduleCfg, $relationModule);
            $tag = str_ends_with($relationName, 'Ref') ? 'moduleReference' : 'repeatableGroup';
            $objParser->setTag($tag . 'Item');
            $collectionParser = new CollectionParser($tag, $objParser);
            $parser->setRelationParser($relationName, $collectionParser);
        }

        return $parser;
    }

    static function getRelationModule(array $moduleCfg, string $module, string $relationName): ?string
    {
        $relations = self::getNormalizedRelationConfig($moduleCfg, $module);
        foreach ($relations as $relationCfg) {
            if ($relationCfg['name'] === $relationName) {
                return $relationCfg['module'];
            }
        }
        return null;
    }

    static function getNormalizedFieldConfig(array $moduleCfg, string $module): array
    {
        $cfg = self::getModuleConfig($moduleCfg, $module);

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

    static function getNormalizedRelationConfig(array $moduleCfg, string $module): array
    {
        $cfg = self::getModuleConfig($moduleCfg, $module);

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

    public static function getModuleConfig(array $moduleCfg, string $module): array
    {
        $cfg = $moduleCfg[$module] ?? null;
        if (!$cfg) {
            throw new \InvalidArgumentException(sprintf('Module %s is not part of the config', $module));
        }
        return $cfg;
    }
}
