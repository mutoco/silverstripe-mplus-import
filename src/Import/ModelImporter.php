<?php


namespace Mutoco\Mplus\Import;


use Mutoco\Mplus\Api\Client;
use Mutoco\Mplus\Api\ClientInterface;
use Mutoco\Mplus\Api\XmlNS;
use Mutoco\Mplus\Extension\DataRecordExtension;
use Ramsey\Uuid\Uuid;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBDatetime;

class ModelImporter implements \Serializable
{
    use Configurable;

    private static $models = [];
    private static $namespaces = [
        'm' => XmlNS::MODULE
    ];

    protected string $model;
    protected string $xpath;
    protected ?ModelImporter $parent = null;
    protected ?\DOMDocument $xml = null;
    protected ?\DOMNode $context = null;
    protected array $cfg = [];
    protected string $modelClass;
    protected array $importedIds = [];
    protected array $skippedIds = [];
    protected string $uuid;
    protected array $subtasks = [];
    protected bool $isFinalized = false;
    protected int $currentIndex = 0;
    protected ?\DOMNodeList $nodes = null;
    protected ?ClientInterface $api = null;

    /**
     * @return Client|null
     */
    public function getApi(): ?ClientInterface
    {
        if (!$this->api && $this->parent) {
            return $this->parent->getApi();
        }

        return $this->api;
    }

    /**
     * @param ClientInterface|null $api
     * @return ModelImporter
     */
    public function setApi(?ClientInterface $api): self
    {
        $this->api = $api;
        return $this;
    }

    /**
     * @return string
     */
    public function getUUID(): string
    {
        return $this->uuid;
    }

    /**
     * @return string
     */
    public function getXpath(): string
    {
        return $this->xpath;
    }

    /**
     * @return \DOMDocument|null
     */
    public function getXml(): ?\DOMDocument
    {
        if (!$this->xml && $this->parent) {
            return $this->parent->getXml();
        }

        return $this->xml;
    }

    /**
     * @return \DOMNode|null
     */
    public function getContext(): ?\DOMNode
    {
        return $this->context;
    }

    /**
     * @param \DOMNode|null $context
     * @return ModelImporter
     */
    public function setContext(?\DOMNode $context): ModelImporter
    {
        if ($this->context) {
            $this->context->removeAttribute('c-' . $this->uuid);
        }

        $this->context = $context;
        if ($this->context) {
            $this->context->setAttribute('c-' . $this->uuid, 'context');
        }

        return $this;
    }

    /**
     * @return string
     */
    public function getModelClass(): string
    {
        return $this->modelClass;
    }

    /**
     * @return array - IDs of records that were imported.
     */
    public function getImportedIds(): array
    {
        return $this->importedIds;
    }

    /**
     * @return array - IDs of records that were skipped, because they were not modified
     */
    public function getSkippedIds(): array
    {
        return $this->skippedIds;
    }

    /**
     * @return array - all IDs that were received by this importer for the current model
     */
    public function getReceivedIds(): array
    {
        return array_merge($this->skippedIds, $this->importedIds);
    }

    /**
     * @return int
     */
    public function getCurrentIndex(): int
    {
        return $this->currentIndex;
    }

    /**
     * @return ModelImporter|null
     */
    public function getParent(): ?ModelImporter
    {
        return $this->parent;
    }

    /**
     * @param ModelImporter|null $parent
     * @return ModelImporter
     */
    public function setParent(?ModelImporter $parent): self
    {
        $this->parent = $parent;
        if ($this->parent) {
            $this->initialize($this->getXml());
        }
        return $this;
    }

    /**
     * @return bool - Whether or not the import has concluded
     */
    public function getIsFinalized(): bool
    {
        return $this->isFinalized;
    }

    public function __construct(string $model, string $xpath, ModelImporter $parent = null, ?\DOMNode $context = null)
    {
        $this->model = $model;
        $this->xpath = $xpath;
        $this->parent = $parent;
        $this->uuid = Uuid::uuid4()->toString();

        $cfg = self::getModelConfig($this->model);
        if (!$cfg) {
            throw new \InvalidArgumentException(sprintf('No config defined for model "%s"', $this->model));
        }

        $this->cfg = $cfg;

        if (!isset($this->cfg['class'])) {
            throw new \InvalidArgumentException(sprintf('No class defined for model "%s"', $this->model));
        }

        $this->modelClass = $this->cfg['class'];

        if (!is_subclass_of($this->modelClass, DataObject::class)) {
            throw new \InvalidArgumentException('Import target class must be a DataObject');
        }

        if ($context) {
            $this->setContext($context);
        }
    }

    public function getImportedIdsPerModel(): array
    {
        $arr = $this->getIdsPerModel();
        foreach ($this->subtasks as $name => $task) {
            $arr = array_merge_recursive($arr, $task->getImportedIdsPerModel());
        }
        return $arr;
    }

    public function getTotalSteps()
    {
        $steps = 0;
        if ($this->nodes) {
            $steps += $this->nodes->count();
        }

        foreach ($this->subtasks as $name => $task) {
            $steps += $task->getTotalSteps();
        }

        return $steps;
    }

    public function getProcessedSteps()
    {
        $steps = $this->currentIndex;

        foreach ($this->subtasks as $name => $task) {
            $steps += $task->getProcessedSteps();
        }

        return $steps;
    }

    public function initialize(?\DOMDocument $xml = null)
    {
        $this->xml = $xml;

        if (!$this->context) {
            $result = $this->performQuery(sprintf('//m:*[@c-%s="context"]', $this->uuid));
            if ($result && $result->count()) {
                $this->setContext($result[0]);
            }
        }

        $this->nodes = $this->performQuery($this->xpath);
    }

    public function importNext(): bool
    {
        if ($this->isFinalized) {
            return false;
        }

        foreach ($this->subtasks as $name => $subtask) {
            if ($subtask->importNext()) {
                return true;
            }
        }

        if ($this->getRemainingSteps() <= 0) {
            $this->finalize();
            return false;
        }

        $this->processNode($this->getCurrent());
        $this->currentIndex++;

        return true;
    }

    public final function finalize()
    {
        if ($this->isFinalized) {
            return;
        }

        $this->performFinalize();

        $this->isFinalized = true;
        $this->xml = null;
    }

    public final function cleanup($idList)
    {
        foreach ($this->subtasks as $key => $task) {
            $task->cleanup($idList);
        }

        $this->performCleanup($idList);
    }

    public final function serialize()
    {
        return serialize($this->getSerializableObject());
    }

    public final function unserialize($data)
    {
        $this->unserializeFromObject(unserialize($data));
    }

    public static function getModelConfig(string $model): ?array
    {
        $modelsConfig = self::config()->get('models');
        if (!isset($modelsConfig[$model])) {
            return null;
        }
        return $modelsConfig[$model];
    }

    /**
     * @param string $path – the xpath string
     * @param \DOMNode|null $context - the context to use
     * @return \DOMNodeList|false
     */
    public function performQuery(string $path, \DOMNode $context = null)
    {
        return $this->createXPath()->query($path, $context ?? $this->context);
    }

    protected function getIdsPerModel(): array
    {
        return [$this->modelClass => $this->getReceivedIds()];
    }

    protected function performCleanup($idList)
    {
        if (isset($idList[$this->modelClass])) {
            // Find all records only exists in the DB but no longer remotely
            $obsolete = DataObject::get($this->modelClass)->exclude(['MplusID' => $idList[$this->modelClass]]);
            /** @var DataObject $record */
            foreach ($obsolete as $record) {
                $this->deleteRecord($record);
            }
        }
    }

    protected function performFinalize()
    {

    }

    protected function getRemainingSteps()
    {
        $steps = 0;
        if ($this->nodes) {
            $steps += $this->nodes->count() - $this->currentIndex;
        }

        return $steps;
    }

    protected function getCurrent(): \DOMNode
    {
        if (!$this->nodes) {
            $this->nodes = $this->performQuery($this->xpath);
        }

        return $this->nodes->item($this->currentIndex);
    }

    protected function processNode(\DOMNode $node)
    {
        $id = $node->getAttribute('id');
        if (empty($id)) {
            throw new \LogicException('Cannot import an item without ID');
        }

        $skipped = false;
        $record = $this->createOrUpdate($id, $node, $skipped);
        if ($skipped) {
            $this->skippedIds[] = $id;
        } else {
            $this->importedIds[] = $id;
        }

        if (!empty($this->cfg['relations'])) {
            foreach ($this->cfg['relations'] as $relation => $relationCfg) {
                $this->importRelation($record, $node, $relation, $relationCfg);
            }
        }
    }

    protected function createOrUpdate($id, \DOMNode $node, bool &$skipped = false)
    {
        $xpath = $this->createXPath();

        $existing = DataObject::get_one($this->modelClass, ['MplusID' => $id]);
        /** @var DataObject $target */
        $target = $existing ?? Injector::inst()->create($this->modelClass);

        if (!$target->hasExtension(DataRecordExtension::class)) {
            throw new \LogicException('Dataobject import target needs to have the DataRecordExtension');
        }

        // Skip over existing records that were not modified remotely
        if ($target->isInDB()) {
            $lastModifiedNode = $xpath->query('.//m:systemField[@name="__lastModified"]/m:value', $node);
            $lastModified = 0;
            if ($lastModifiedNode && $lastModifiedNode->count()) {
                $lastModified = strtotime($lastModifiedNode->item(0)->nodeValue);
            }

            if ($lastModified > 0 && $lastModified <= strtotime($target->Imported)) {
                // Get result from skip call and filter out any `null` value returns
                $skipCallbackResult = array_filter(
                    $target->extend('beforeMplusSkip', $this, $node),
                    function ($v) {
                        return !is_null($v);
                    }
                );
                // If any callback returned false, we won't skip
                if (empty($skipCallbackResult) || min($skipCallbackResult) !== false) {
                    $skipped = true;
                    return $target;
                }
            }
        }

        $target->extend('beforeMplusImport', $this, $node);

        $target->MplusID = $id;
        $target->setField('Imported', DBDatetime::now());
        $target->Module = $this->model;

        if (!empty($this->cfg['fields'])) {
            foreach ($this->cfg['fields'] as $field => $cfg) {
                if (!is_array($cfg)) {
                    $cfg = ['xpath' => $cfg];
                }
                $result = $xpath->query($this->makeRelative($cfg['xpath']), $node);
                if ($result && $result->count()) {
                    $value = $result[0]->nodeValue;
                    if ($target->hasDatabaseField($field)) {
                        if (isset($cfg['transform'])) {
                            $value = call_user_func($cfg['transform'], $value);
                        }
                        $target->setField($field, $value);
                    }
                }
            }
        }

        $target->write();
        $this->persistedRecord($target);
        $target->extend('afterMplusImport', $this, $node);
        return $target;
    }

    protected function persistedRecord(DataObject $record)
    {

    }

    protected function deleteRecord(DataObject $record)
    {
        $rules = array_filter($record->extend('beforeMplusDelete', $this), function ($v) {
            return !is_null($v);
        });

        if (!empty($rules) && min($rules) === false) {
            return false;
        }

        $record->delete();
        return true;
    }

    protected function importRelation(DataObject $target, \DOMNode $node, string $relationName, array $relationCfg)
    {
        if (!isset($this->subtasks[$relationName])) {
            if (isset($relationCfg['moduleRef'])) {
                $parts = explode('.', $relationCfg['moduleRef']);
                $module = $this->performQuery(sprintf('.//m:moduleReference[@name="%s"]', array_shift($parts)), $node);
                if ($module && ($moduleNode = $module->item(0))) {
                    $importer = new ModuleRelationImporter($relationCfg['model'], $this, $target, $relationName, $moduleNode);
                    $importer->setSubRelations($parts);
                }
            } else {
                $importer = new RelationImporter($relationCfg['model'], $relationCfg['xpath'], $this, $target, $relationName, $node);
            }

            if ($importer) {
                $importer->initialize();
                $this->subtasks[$relationName] = $importer;
            }
        }
    }

    protected function createXPath(?\DOMDocument $xml = null): \DOMXPath
    {
        $ns = self::config()->get('namespaces');
        $xpath = new \DOMXPath($xml ?? $this->getXml());
        foreach ($ns as $prefix => $namespace) {
            $xpath->registerNamespace($prefix, $namespace);
        }
        return $xpath;
    }

    protected function makeRelative(string $xpath): string
    {
        return '.' . ltrim($xpath, '.');
    }

    protected function getSerializableObject(): \stdClass
    {
        $obj = new \stdClass();

        if ($this->xml) {
            $obj->xml = $this->xml->saveXML();
        }

        $obj->uuid = $this->uuid;
        $obj->isFinalized = $this->isFinalized;
        $obj->xpath = $this->xpath;
        $obj->model = $this->model;
        $obj->index = $this->currentIndex;
        $obj->importedIds = $this->importedIds;
        $obj->skippedIds = $this->skippedIds;
        $obj->subtasks = $this->subtasks;

        return $obj;
    }

    protected function unserializeFromObject(\stdClass $obj): void
    {
        $this->xpath = $obj->xpath;
        $this->model = $obj->model;
        $this->uuid = $obj->uuid;
        $this->isFinalized = $obj->isFinalized;
        $this->importedIds = $obj->importedIds;
        $this->skippedIds = $obj->skippedIds;
        $this->currentIndex = $obj->index;
        $this->cfg = self::getModelConfig($this->model);
        $this->modelClass = $this->cfg['class'];

        if (isset($obj->xml)) {
            $this->xml = new \DOMDocument();
            $this->xml->loadXML($obj->xml);
            $this->initialize($this->xml);
        }

        $this->subtasks = $obj->subtasks;
        foreach ($this->subtasks as $name => $task) {
            $task->setParent($this);
        }
    }


}
