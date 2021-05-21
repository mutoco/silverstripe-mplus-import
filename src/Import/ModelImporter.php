<?php


namespace Mutoco\Mplus\Import;


use Mutoco\Mplus\Api\XmlNS;
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

    private string $model;
    private string $xpath;
    private ?\DOMDocument $xml = null;
    private ?\DOMElement $context = null;
    private array $cfg;
    private string $modelClass;
    private array $importedIds = [];

    private int $currentIndex = -1;
    private ?\DOMNodeList $nodes = null;

    /**
     * @return string
     */
    public function getXpath(): string
    {
        return $this->xpath;
    }

    /**
     * @param string $xpath
     * @return ModelImporter
     */
    public function setXpath(string $xpath): self
    {
        $this->xpath = $xpath;
        return $this;
    }

    /**
     * @return \DOMDocument|null
     */
    public function getXml(): ?\DOMDocument
    {
        return $this->xml;
    }

    /**
     * @param \DOMDocument|null $xml
     * @return ModelImporter
     */
    public function setXml(?\DOMDocument $xml): self
    {
        $this->xml = $xml;
        return $this;
    }

    /**
     * @return \DOMElement|null
     */
    public function getContext(): ?\DOMElement
    {
        return $this->context;
    }

    /**
     * @param \DOMElement|null $context
     * @return ModelImporter
     */
    public function setContext(?\DOMElement $context): self
    {
        $this->context = $context;
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
     * @param string $modelClass
     * @return ModelImporter
     */
    public function setModelClass(string $modelClass): self
    {
        $this->modelClass = $modelClass;
        return $this;
    }

    /**
     * @return array
     */
    public function getImportedIds(): array
    {
        return $this->importedIds;
    }

    /**
     * @return int
     */
    public function getCurrentIndex(): int
    {
        return $this->currentIndex;
    }


    public function __construct(string $model, string $xpath, ?\DOMDocument $xml = null, ?\DOMElement $context = null)
    {
        $this->model = $model;
        $this->xpath = $xpath;

        if ($context) {
            $this->context = $context;
        }

        if ($xml) {
            $this->xml = $xml;
            $this->initialize();
        }
    }

    public function getRemainingSteps()
    {
        if ($this->nodes) {
            return $this->nodes->count() - $this->currentIndex;
        }

        return 0;
    }

    public function initialize()
    {
        $modelsConfig = self::config()->get('models');
        if (!isset($modelsConfig[$this->model])) {
            throw new \InvalidArgumentException(sprintf('No config defined for model "%s"', $this->model));
        }

        $this->cfg = $modelsConfig[$this->model];

        if (!isset($this->cfg['class'])) {
            throw new \InvalidArgumentException(sprintf('No class defined for model "%s"', $this->model));
        }

        $this->modelClass = $this->cfg['class'];

        if (!is_subclass_of($this->modelClass, DataObject::class)) {
            throw new \InvalidArgumentException('Import target class must be a DataObject');
        }

        $this->nodes = $this->performQuery($this->xpath);
        $this->currentIndex = 0;
        $this->importedIds = [];
    }

    public function importNext()
    {
        $node = $this->nodes->item($this->currentIndex);

        $id = $node->getAttribute('id');
        if (empty($id)) {
            throw new \LogicException('Cannot import an item without ID');
        }

        $xpath = $this->createXPath();

        $existing = DataObject::get_one($this->modelClass, ['MplusID' => $id]);
        /** @var DataObject $target */
        $target = $existing ?? Injector::inst()->create($this->modelClass);

        $target->MplusID = $id;
        $target->Imported = DBDatetime::now();
        $target->Module = $this->model;

        if (!empty($this->cfg['fields'])) {
            foreach ($this->cfg['fields'] as $field => $cfg) {
                if (!is_array($cfg)) {
                    $cfg = ['xpath' => $cfg];
                }
                $result = $xpath->query($cfg['xpath'], $node);
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

        $this->importedIds[] = $target->write();

        $this->currentIndex++;
    }

    public function serialize()
    {
        $obj = new \stdClass();

        if ($this->context) {
            $this->context->setAttribute('serializedContext', 'serializedContext');
        }

        $obj->xml = $this->xml->saveXML();
        $obj->xpath = $this->xpath;
        $obj->model = $this->model;
        $obj->index = $this->currentIndex;
        $obj->importedIds = $this->importedIds;
        return serialize($obj);
    }

    public function unserialize($data)
    {
        $obj = unserialize($data);
        $this->xpath = $obj->xpath;
        $this->model = $obj->model;
        $this->xml = new \DOMDocument();
        $this->xml->loadXML($obj->xml);

        $result = $this->performQuery('//m:*[@serializedContext="serializedContext"]');
        if ($result && $result->count()) {
            $this->context = $result[0];
            $this->context->removeAttribute('serializedContext');
        }

        $this->initialize();
        $this->importedIds = $obj->importedIds;
        $this->currentIndex = $obj->index;
    }

    private function createXPath(): \DOMXPath
    {
        $ns = self::config()->get('namespaces');
        $xpath = new \DOMXPath($this->xml);
        foreach ($ns as $prefix => $namespace) {
            $xpath->registerNamespace($prefix, $namespace);
        }
        return $xpath;
    }

    /**
     * @param string $path – the xpath string
     * @return \DOMNodeList|false
     */
    private function performQuery(string $path)
    {
        return $this->createXPath()->query($path, $this->context);
    }
}
