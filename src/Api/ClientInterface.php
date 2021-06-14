<?php


namespace Mutoco\Mplus\Api;


use Psr\Http\Message\StreamInterface;

interface ClientInterface
{
    public function init(): self;

    public function search(string $module, string $xml): ?StreamInterface;

    public function queryModelItem(string $module, string $id): ?StreamInterface;

    public function loadAttachment(string $module, string $id, ?callable $onHeaders = null): ?StreamInterface;
}
