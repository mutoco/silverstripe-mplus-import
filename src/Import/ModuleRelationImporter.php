<?php


namespace Mutoco\Mplus\Import;


use SilverStripe\ORM\DataObject;

class ModuleRelationImporter extends RelationImporter
{
    public function __construct(string $model, ModelImporter $parent, DataObject $target, string $relationName, ?\DOMNode $context = null)
    {
        parent::__construct($model, './m:moduleReferenceItem[@moduleItemId]', $parent, $target, $relationName, $context);
    }

    protected function getTargetModule() : string
    {
        return $this->getContext()->getAttribute('targetModule');
    }

    protected function processNode(\DOMNode $node)
    {
        $id = $node->getAttribute('moduleItemId');
        if (empty($id)) {
            throw new \LogicException('Cannot import an item without ID');
        }

        $target = $this->getTargetModule();
        $key = sprintf('%s-%s', $target, $id);

        if (!isset($this->subtasks[$key])) {
            $client = $this->getApi();
            $xml = $client->queryModelItem($target, $id);
            $importer = new ModelImporter(
                $this->model,
                sprintf('//m:module[@name="%s"]/m:moduleItem', $target)
            );
            $importer->initialize($xml);
            $this->subtasks[$key] = $importer;
        }
    }

    public function finalize()
    {
        if ($relation = $this->getRelation()) {
            foreach ($this->subtasks as $key => $importer) {
                foreach ($importer->getReceivedIds() as $id) {
                    $this->importedIds[] = $id;
                    $relation->add(DataObject::get($importer->getModelClass())->find('MplusID', $id));
                }
            }
        }

        parent::finalize();
    }
}
