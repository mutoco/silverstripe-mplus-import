<?php


namespace Mutoco\Mplus\Import;


use Mutoco\Mplus\Api\XmlNS;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;

class ModelImport
{
    private string $class;
    private array $mapping;
    private array $ns;

    public function __construct(string $class, array $mapping, array $ns = ['m' => XmlNS::MODULE])
    {
        $this->class = $class;
        $this->mapping = $mapping;
        $this->ns = $ns;
    }

    public function import(\DOMElement $node, \DOMDocument $xml)
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
}
