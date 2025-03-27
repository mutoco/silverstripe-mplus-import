<?php

/**
 * A helper to create valid search queries for the MpRIA.
 * This does not cover all the possible use-cases!
 */

namespace Mutoco\Mplus\Api;

use Mutoco\Mplus\Serialize\SerializableTrait;
use Mutoco\Mplus\Util;

class SearchBuilder implements \Serializable
{
    use SerializableTrait;

    protected string $module;
    protected int $start;
    protected int $limit;
    protected array $select;
    protected array $expert;
    protected array $sort;
    protected bool $prettyPrint;
    protected ?string $fulltext;

    public function __construct(string $module, int $start = 0, int $limit = 10)
    {
        $this->start = $start;
        $this->limit = $limit;
        $this->module = $module;
        $this->select = [];
        $this->expert = [];
        $this->sort = [];
        $this->prettyPrint = false;
        $this->fulltext = null;
    }

    public function getModule(): string
    {
        return $this->module;
    }

    /**
     * @return int
     */
    public function getStart(): int
    {
        return $this->start;
    }

    /**
     * @param int $start
     * @return SearchBuilder
     */
    public function setStart(int $start): self
    {
        $this->start = $start;
        return $this;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     * @return SearchBuilder
     */
    public function setLimit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * @return array
     */
    public function getSelect(): array
    {
        return $this->select;
    }

    /**
     * @param array $select
     * @return SearchBuilder
     */
    public function setSelect(array $select): self
    {
        $this->select = $select;
        return $this;
    }

    /**
     * @return bool
     */
    public function getPrettyPrint(): bool
    {
        return $this->prettyPrint;
    }

    /**
     * @param bool $prettyPrint
     * @return SearchBuilder
     */
    public function setPrettyPrint(bool $prettyPrint): self
    {
        $this->prettyPrint = $prettyPrint;
        return $this;
    }

    /**
     * @return string
     */
    public function getFulltext(): string
    {
        return $this->fulltext;
    }

    /**
     * @param string $fulltext
     * @return SearchBuilder
     */
    public function setFulltext(string $fulltext): self
    {
        $this->fulltext = $fulltext;
        return $this;
    }

    /**
     * @return array
     */
    public function getExpert(): array
    {
        return $this->expert;
    }

    /**
     * @param array $expert
     * @return SearchBuilder
     */
    public function setExpert(array $expert): self
    {
        $this->expert = $expert;
        return $this;
    }

    public function hasSort(string $field): bool
    {
        return isset($this->sort[$field]);
    }

    public function addSort(string $field, bool $asc = true): self
    {
        $this->sort[$field] = $asc ? 'Ascending' : 'Descending';
        return $this;
    }

    public function removeSort(string $field): self
    {
        unset($this->sort[$field]);
        return $this;
    }

    public function isValid(): bool
    {
        $valid = true;
        $oldHandler = set_error_handler(function ($code, $str) use (&$valid) {
            $valid = false;
        });
        try {
            $this->__toString();
        } catch (\Error $err) {
            $valid = false;
        }

        set_error_handler($oldHandler);

        return $valid;
    }

    public function __toString(): string
    {
        $xml = new \DOMDocument('1.0', 'UTF-8');

        $xml->appendChild($root = $xml->createElementNS(XmlNS::SEARCH, 'application'));

        $root->setAttributeNS(
            'http://www.w3.org/2000/xmlns/',
            'xmlns:xsi',
            'http://www.w3.org/2001/XMLSchema-instance'
        );
        $root->setAttributeNS(
            'http://www.w3.org/2001/XMLSchema-instance',
            'schemaLocation',
            'http://www.zetcom.com/ria/ws/module/search http://docs.zetcom.com/ws/module/search/search_1_6.xsd'
        );

        $root->appendChild($modules = $xml->createElementNS(XmlNS::SEARCH, 'modules'));
        $modules->appendChild($child = $xml->createElementNS(XmlNS::SEARCH, 'module'));
        $child->setAttribute('name', $this->module);
        $child->appendChild($search = $xml->createElementNS(XmlNS::SEARCH, 'search'));
        $search->setAttribute('limit', $this->limit);
        $search->setAttribute('offset', $this->start);

        if (!empty($this->select)) {
            $search->appendChild($select = $xml->createElementNS(XmlNS::SEARCH, 'select'));
            foreach ($this->select as $field) {
                $select->appendChild($fieldNode = $xml->createElementNS(XmlNS::SEARCH, 'field'));
                $fieldNode->setAttribute('fieldPath', $field);
            }
        }

        if (!empty($this->sort)) {
            $search->appendChild($sort = $xml->createElementNS(XmlNS::SEARCH, 'sort'));
            foreach ($this->sort as $field => $direction) {
                $sort->appendChild($fieldNode = $xml->createElementNS(XmlNS::SEARCH, 'field'));
                $fieldNode->setAttribute('fieldPath', $field);
                $fieldNode->setAttribute('direction', $direction);
            }
        }

        if ($this->fulltext) {
            $search->appendChild($fulltext = $xml->createElementNS(XmlNS::SEARCH, 'fulltext'));
            $fulltext->nodeValue = $this->fulltext;
        }

        if (!empty($this->expert)) {
            $search->appendChild($expert = $xml->createElementNS(XmlNS::SEARCH, 'expert'));
            $this->buildExpertTree($this->expert, $expert, $xml);
        }

        $xml->schemaValidate(realpath(join(DIRECTORY_SEPARATOR, [__DIR__, '..', '..', 'schema', 'search_1_6.xsd'])));

        if ($this->prettyPrint) {
            $xml->preserveWhiteSpace = false;
            $xml->formatOutput = true;
        }

        return $xml->saveXML();
    }

    protected function buildExpertTree(array $config, \DOMNode $parent, \DOMDocument $doc)
    {
        foreach ($config as $item => $value) {
            if (!is_array($value)) {
                continue;
            }
            if (Util::isAssoc($value) && isset($value['type'])) {
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
                $this->buildExpertTree($value, $child, $doc);
            }
        }
    }

    protected function getSerializableArray(): array
    {
        return [
            'start' => $this->start,
            'limit' => $this->limit,
            'module' => $this->module,
            'select' => $this->select,
            'expert' => $this->expert,
            'sort' => $this->sort,
            'prettyPrint' => $this->prettyPrint,
            'fulltext' => $this->fulltext
        ];
    }

    protected function unserializeFromArray(array $data): void
    {
        $this->start = $data['start'];
        $this->limit = $data['limit'];
        $this->module = $data['module'];
        $this->select = $data['select'];
        $this->expert = $data['expert'];
        $this->sort = $data['sort'];
        $this->prettyPrint = $data['prettyPrint'];
        $this->fulltext = $data['fulltext'];
    }
}
