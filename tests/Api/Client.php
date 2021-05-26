<?php


namespace Mutoco\Mplus\Tests\Api;


use Mutoco\Mplus\Api\ClientInterface;

class Client implements ClientInterface
{
    public function queryModelItem(string $model, int $id): ?\DOMDocument
    {
        $filename = sprintf('%s-%d.xml', $model, $id);
        $filePath = realpath(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'data', $filename]));
        if (file_exists($filePath)) {
            $xml = new \DOMDocument();
            $xml->load($filePath);
            return $xml;
        }
        return null;
    }
}
