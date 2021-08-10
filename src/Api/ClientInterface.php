<?php

namespace Mutoco\Mplus\Api;

use Psr\Http\Message\StreamInterface;

interface ClientInterface
{
    /**
     * Initialize the client (eg. establish a session or similar)
     * @return $this
     */
    public function init(): self;

    /**
     * Perform a search against the API
     * @param string $module - the module to query
     * @param string $xml - the XML search
     * @return StreamInterface|null - the XML result stream or null upon failure
     */
    public function search(string $module, string $xml): ?StreamInterface;

    /**
     * Query a module with the given ID from the API
     * @param string $module - the module to query
     * @param string $id - the module ID
     * @return StreamInterface|null - the XML result stream or null upon failure
     */
    public function queryModelItem(string $module, string $id): ?StreamInterface;

    /**
     * Query the vocabulary data for a given vocabulary-group. This will return all possible terms for this
     * vocabulary-group. Can optionally be limited to a single item by supplying the Vocabulary-Item ID.
     * @param string $vocGroup - the name of the vocabulary group, eg. "ObjDim3DTypeVgr"
     * @param string|null $itemId - (optional) the ID of the vocabulary item to query
     * @return StreamInterface|null - the XML result stream or null upon failure
     */
    public function queryVocabularyData(string $vocGroup, ?string $itemId = null): ?StreamInterface;

    /**
     * Load an attachment for the given module
     * @param string $module - the module to query
     * @param string $id - the module ID
     * @param callable|null $onHeaders - callback to abort the request if necessary,
     *  also see: https://docs.guzzlephp.org/en/stable/request-options.html#on-headers
     * @return StreamInterface|null - the Binary result stream or null
     */
    public function loadAttachment(string $module, string $id, ?callable $onHeaders = null): ?StreamInterface;
}
