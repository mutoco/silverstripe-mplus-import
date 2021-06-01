<?php


namespace Mutoco\Mplus\Api;


use Psr\Http\Message\StreamInterface;

interface ClientInterface
{
    public function init(): self;

    public function search(string $module, string $xml): ?StreamInterface;

    public function queryModelItem(string $model, int $id): ?StreamInterface;
}
