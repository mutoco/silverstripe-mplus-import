<?php
/**
 * A helper to create valid search queries for the MpRIA.
 * This does not cover all the possible use-cases!
 */

namespace Mutoco\Mplus\Api;


class SearchBuilder
{
    private array $modules = [];
    private $start = 0;
    private $limit = 10;

    public function __construct(string $module, array $params, $start = 0, $limit = 10)
    {
        $this->start = $start;
        $this->limit = $limit;
        $this->modules[$module] = $params;
    }

    public function __toString() : string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');

        $xml->appendChild($root = $xml->createElementNS(XmlNS::SEARCH,'application'));

        $root->setAttributeNS('http://www.w3.org/2000/xmlns/' ,'xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $root->setAttributeNS('http://www.w3.org/2001/XMLSchema-instance', 'schemaLocation', 'http://www.zetcom.com/ria/ws/module/search http://docs.zetcom.com/ws/module/search/search_1_6.xsd');

        $root->appendChild($modules = $xml->createElementNS(XmlNS::SEARCH,'modules'));
        foreach ($this->modules as $module => $config) {
            $modules->appendChild($child = $xml->createElementNS(XmlNS::SEARCH, 'module'));
            $child->setAttribute('name', $module);
            $child->appendChild($search = $xml->createElementNS(XmlNS::SEARCH, 'search'));
            $search->setAttribute('limit', $this->limit);
            $search->setAttribute('offset', $this->start);
            $search->appendChild($expert = $xml->createElementNS(XmlNS::SEARCH, 'expert'));

            if (is_array($config)) {
                $this->serialize($config, $expert, $xml);
            }
        }

        $xml->schemaValidate(realpath(join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'schema', 'search_1_6.xsd'])));

        return $xml->saveXML();
    }

    protected function serialize(array $config, \DOMNode $parent, \DOMDocument $doc)
    {
        foreach ($config as $item => $value) {
            if (!is_array($value)) {
                continue;
            }
            if ($this->isAssoc($value)) {
                $child = $doc->createElementNS(XmlNS::SEARCH, $value['type']);
                foreach ($value as $k => $v) {
                    if ($k === 'type') {
                        continue;
                    }
                    $child->setAttribute($k, $v);
                }
                $parent->appendChild($child);
            } else {
                $child = $doc->createElementNS(XmlNS::SEARCH, $item);
                $parent->appendChild($child);
                $this->serialize($value, $child, $doc);
            }
        }
    }

    protected function isAssoc(array $arr) : bool
    {
        if (array() === $arr) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
}
