<?php


namespace Mutoco\Mplus\Api;


class Client
{
    const SESSION_NS = 'http://www.zetcom.com/ria/ws/session';

    private string $baseUrl;
    private string $username;
    private string $password;
    private string $sessionKey;

    private \GuzzleHttp\Client $client;

    public function __construct(string $baseUrl, string $username, string $password)
    {
        $this->setBaseUrl($baseUrl);
        $this->setUsername($username);
        $this->setPassword($password);
    }

    public function init() : self
    {
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
            $xpath->registerNamespace('ns', self::SESSION_NS);
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

    public function destroySession() : bool
    {
        $response = $this->client->delete('ria-ws/application/session/'. $this->sessionKey);
        return $response->getStatusCode() === 200;
    }

    public function search(string $module, string $xml) : \DOMDocument
    {
        $response = $this->client->post(sprintf('ria-ws/application/module/%s/search/', $module), [
            'body' => $xml
        ]);

        if ($response->getStatusCode() === 200) {
            return $this->parseXml($response->getBody());
        }
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

    private function parseXml(string $body)
    {
        $xml = new \DOMDocument();
        $xml->loadXML($body);
        return $xml;
    }
}
