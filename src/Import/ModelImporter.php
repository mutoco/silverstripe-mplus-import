<?php


namespace Mutoco\Mplus\Import;


use Exception;
use http\Exception\InvalidArgumentException;
use Mutoco\Mplus\Api\XmlNS;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;

class ModelImporter implements \Serializable
{
    use Configurable;

    private static $models = [];
    private static $namespaces = [
        'm' => XmlNS::MODULE
    ];

    private string $model;
    private string $xpath;
    private \DOMDocument $xml;
    private \DOMElement $context;
    private array $cfg;

    private int $currentIndex = 0;
    private \DOMNodeList $nodes;

    public function __construct(string $model, string $xpath, \DOMDocument $xml = null, \DOMElement $context = null)
    {
        $modelsConfig = self::config()->get('models');
        if (isset($modelsConfig[$model])) {
            throw new InvalidArgumentException(sprintf('No config defined for model "%s"', $model));
        }

        $this->cfg = $modelsConfig[$model];
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
        $this->nodes = $this->performQuery($this->xpath);
        $this->currentIndex = 0;
    }

    public function importNext()
    {
        $node = $this->nodes->item($this->currentIndex);


        $this->currentIndex++;
    }

    public function import(string $model, \DOMElement $node, \DOMDocument $xml)
    {
        if (!is_subclass_of($this->class, DataObject::class)) {
            throw new \LogicException('Import target must be a DataObject');
        }

        $id = $node->getAttribute('id');
        if (empty($id)) {
            throw new \LogicException('Cannot import an item without ID');
        }

        //if (!isset(self::config()->get('modules')))

        $xpath = new \DOMXPath($xml);

        $existing = DataObject::get_one($this->class, ['MplusID' => $id]);
        /** @var DataObject $target */
        $target = $existing ?? Injector::inst()->create($this->class);

        foreach ($this->ns as $short => $long) {
            $xpath->registerNamespace($short, $long);
        }

        //TODO: Check modified fields

        foreach ($this->mapping as $field => $xpath) {
            $result = $xpath->query($xpath);
            if ($result && $result->count()) {
                $value = $result[0]->nodeValue;
                if ($target->hasDatabaseField($field)) {
                    $target->dbObject($field)->setValue($value);
                }
            }
        }

        $target->write();
    }

    public function serialize()
    {
        // TODO: Implement serialize() method.
    }

    public function unserialize($data)
    {
        // TODO: Implement unserialize() method.
    }

    /**
     * @param string $path – the xpath string
     * @return \DOMNodeList|false
     */
    private function performQuery(string $path)
    {
        $ns = self::config()->get('namespaces');
        $xpath = new \DOMXPath($this->xml);
        foreach ($ns as $prefix => $namespace) {
            $xpath->registerNamespace($prefix, $namespace);
        }

        return $xpath->query($path, $this->context);
    }
}
