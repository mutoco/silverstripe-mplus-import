<?php


namespace Mutoco\Mplus\Api;


use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\StreamInterface;

class Client implements ClientInterface
{
    private string $baseUrl;
    private string $username;
    private string $password;
    private ?string $sessionKey;

    private \GuzzleHttp\Client $client;

    public function __construct(string $baseUrl, string $username, string $password)
    {
        $this->setBaseUrl($baseUrl);
        $this->setUsername($username);
        $this->setPassword($password);
        $this->sessionKey = null;
    }


    /**
     * @return string
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * @param string $baseUrl
     * @return Client
     */
    public function setBaseUrl(string $baseUrl): self
    {
        $this->baseUrl = rtrim($baseUrl, '/') . '/';
        return $this;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * @param string $username
     * @return Client
     */
    public function setUsername(string $username): self
    {
        $this->username = $username;
        return $this;
    }

    /**
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * @param string $password
     * @return Client
     */
    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    public function hasSession(): bool
    {
        return $this->sessionKey !== null;
    }

    public function init(): self
    {
        $this->sessionKey = null;
        $tmpClient = new \GuzzleHttp\Client([
            'base_uri' => $this->getBaseUrl()
        ]);

        $response = $tmpClient->get('ria-ws/application/session', [
            'auth' => [
                $this->username,
                $this->password
            ]
        ]);

        if ($response->getStatusCode() === 200) {
            $doc = $this->parseXml($response->getBody());
            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('ns', XmlNS::SESSION);
            $session = $xpath->query('//ns:session[@pending="false"]/ns:key');
            if ($session && $session->count()) {
                $this->sessionKey = (string)$session[0]->nodeValue;
                $this->client = new \GuzzleHttp\Client([
                    'base_uri' => $this->getBaseUrl(),
                    'auth' => [
                        sprintf('user[%s]', $this->username),
                        sprintf('session[%s]', $this->sessionKey)
                    ]
                ]);
            }
        } else {
            throw new \Exception('Unable to establish session. Make sure credentials are correct');
        }

        return $this;
    }

    public function destroySession(): bool
    {
        if ($this->hasSession()) {
            $response = $this->client->delete('ria-ws/application/session/' . $this->sessionKey);
            return $response->getStatusCode() === 200;
        }

        return false;
    }

    public function search(string $module, string $xml): ?StreamInterface
    {
        return $this->sendApiRequest(sprintf('ria-ws/application/module/%s/search/', $module), [
            'body' => $xml
        ]);
    }

    public function queryModelItem(string $module, string $id): ?StreamInterface
    {
        return $this->sendApiRequest(sprintf('ria-ws/application/module/%s/%s', $module, $id));
    }


    public function loadAttachment(string $module, string $id, ?callable $onHeaders = null): ?StreamInterface
    {
        try {
            return $this->sendApiRequest(
                sprintf('ria-ws/application/module/%s/%s/attachment', $module, $id),
                array_merge([
                    'headers' => [
                        'Accept' => 'application/octet-stream'
                    ]
                ], $onHeaders ? ['on_headers' => $onHeaders] : [])
            );
        } catch (RequestException $ex) {
            // Request was cancelled
        }

        return null;
    }

    protected function sendApiRequest(string $url, array $options = []): ?StreamInterface
    {
        $response = $this->client->get($url, $options);

        if ($response->getStatusCode() === 200) {
            return $response->getBody();
        }

        if ($response->getStatusCode() === 403) {
            // Automatically call init and retry the request if access was forbidden
            $this->init();
            if ($this->hasSession()) {
                return $this->sendApiRequest($url, $options);
            }
        }

        return null;
    }

    private function parseXml(string $body): \DOMDocument
    {
        $xml = new \DOMDocument();
        $xml->loadXML($body);
        return $xml;
    }
}
