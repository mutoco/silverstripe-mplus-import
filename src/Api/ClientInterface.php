<?php


namespace Mutoco\Mplus\Api;


interface ClientInterface
{
    public function queryModelItem(string $model, int $id): ?\DOMDocument;
}
