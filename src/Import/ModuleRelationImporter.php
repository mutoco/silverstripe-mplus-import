<?php


namespace Mutoco\Mplus\Import;


use SilverStripe\ORM\DataObject;

class ModuleRelationImporter extends RelationImporter
{
    protected array $subRelations = [];

    /**
     * @return array
     */
    public function getSubRelations(): array
    {
        return $this->subRelations;
    }

    /**
     * @param array $subRelations
     * @return ModuleRelationImporter
     */
    public function setSubRelations(array $subRelations): self
    {
        $this->subRelations = $subRelations;
        return $this;
    }

    public function __construct(string $model, ModelImporter $parent, DataObject $target, string $relationName, ?\DOMNode $context = null)
    {
        parent::__construct($model, './m:moduleReferenceItem[@moduleItemId]', $parent, $target, $relationName, $context);
    }

    protected function getIdsPerModel(): array
    {
        if (empty($this->subRelations)) {
            return parent::getIdsPerModel();
        }

        return [];
    }

    protected function performFinalize()
    {
        if (empty($this->subRelations)) {
            if ($relation = $this->getRelation()) {
                foreach ($this->subtasks as $key => $importer) {
                    foreach ($importer->getReceivedIds() as $id) {
                        $this->importedIds[] = $id;
                        $relation->add(DataObject::get($importer->getModelClass())->find('MplusID', $id));
                    }
                }
            }
        }
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = parent::getSerializableObject();
        $obj->subRelations = $this->subRelations;
        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->subRelations = $obj->subRelations;
        parent::unserializeFromObject($obj);
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

            $importer = null;
            $parts = $this->subRelations;
            if (!empty($parts)) {
                $xpath = $this->createXPath($xml);
                $module = $xpath->query(sprintf('.//m:moduleReference[@name="%s"]', array_shift($parts)));
                if ($module && ($moduleNode = $module->item(0))) {
                    $importer = new ModuleRelationImporter($this->model, $this, $this->target, $this->relationName);
                    $importer->setContext($moduleNode);
                    $importer->initialize($xml);
                    $importer->setSubRelations($parts);
                    $this->subtasks[$key] = $importer;
                }
            } else {
                $importer = new ModelImporter(
                    $this->model,
                    sprintf('//m:module[@name="%s"]/m:moduleItem', $target)
                );

                $importer->initialize($xml);
                $this->subtasks[$key] = $importer;
            }

            if (!$importer) {
                throw new \LogicException('Broken reference chain.');
            }
        }
    }
}
