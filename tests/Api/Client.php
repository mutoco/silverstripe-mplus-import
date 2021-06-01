<?php


namespace Mutoco\Mplus\Tests\Api;


use GuzzleHttp\Psr7\Utils;
use Mutoco\Mplus\Api\ClientInterface;
use Psr\Http\Message\StreamInterface;

class Client implements ClientInterface
{
    public function queryModelItem(string $model, int $id): ?StreamInterface
    {
        $filename = sprintf('%s-%d.xml', $model, $id);
        $filePath = realpath(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'data', $filename]));
        if (file_exists($filePath)) {
            return Utils::streamFor(fopen($filePath, 'r'));
        }
        return null;
    }

    public function init(): ClientInterface
    {
        return $this;
    }

    public function search(string $module, string $xml): ?StreamInterface
    {
        return null;
    }
}
