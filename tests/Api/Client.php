<?php


namespace Mutoco\Mplus\Tests\Api;


use GuzzleHttp\Psr7\Utils;
use Mutoco\Mplus\Api\ClientInterface;
use Psr\Http\Message\StreamInterface;

class Client implements ClientInterface
{
    public function __construct()
    {

    }

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
        $searchDoc = new \SimpleXMLElement($xml);
        $search = $searchDoc->modules[0]->module[0]->search[0];
        $page = floor($search['offset'] / $search['limit']) + 1;

        $filename = sprintf('%s-p%d.xml', $module, $page);
        $filePath = realpath(implode(DIRECTORY_SEPARATOR, [__DIR__, '..', 'data', 'search', $filename]));
        if (file_exists($filePath)) {
            return Utils::streamFor(fopen($filePath, 'r'));
        }
        return null;
    }
}
