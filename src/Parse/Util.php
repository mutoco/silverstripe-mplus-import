<?php


namespace Mutoco\Mplus\Parse;


use Mutoco\Mplus\Parse\Node\CollectionParser;
use Mutoco\Mplus\Parse\Node\ObjectParser;
use Mutoco\Mplus\Parse\Node\ParserInterface;

class Util
{
    static function parserFromConfig(array $moduleCfg, string $module) : ParserInterface
    {
        $cfg = $moduleCfg[$module] ?? null;
        if (!$cfg) {
            throw new \InvalidArgumentException(sprintf('Module %s is not part of the config', $module));
        }

        $parser = new ObjectParser();
        $parser->setType($module);

        if (isset($cfg['fields'])) {
            $parser->setFieldList(array_values($cfg['fields']));
        }

        if (isset($cfg['relations'])) {
            foreach ($cfg['relations'] as $rel => $relationCfg) {
                if (is_string($relationCfg)) {
                    $relationModule = $relationName = $relationCfg;
                } else {
                    $relationModule = $relationCfg['module'];
                    $relationName = $relationCfg['name'];
                }
                /** @var ObjectParser $objParser */
                $objParser = self::parserFromConfig($moduleCfg, $relationModule);
                $tag = str_ends_with($relationName, 'Ref') ? 'moduleReference' : 'repeatableGroup';
                $objParser->setTag($tag . 'Item');
                $collectionParser = new CollectionParser($tag, $objParser);
                $parser->setRelationParser($relationName, $collectionParser);
            }
        }

        return $parser;
    }
}
