<?php


namespace Mutoco\Mplus\Api;


use SilverStripe\Dev\Debug;
use Symfony\Component\Config\Util\Exception\XmlParsingException;

class Client
{
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
            $this->sessionKey = (string)$response->getBody();
            $this->client = new \GuzzleHttp\Client([
                'base_uri' => $this->getBaseUrl(),
                'auth' => [
                    $this->username,
                    $this->sessionKey
                ]
            ]);
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

    private function parseXml(string $body, array $config = [])
    {
        $internalErrors = libxml_use_internal_errors(true);
        try {
            // Allow XML to be retrieved even if there is no response body
            $xml = new \SimpleXMLElement(
                $body ?: '<root />',
                $config['libxml_options'] ?? LIBXML_NONET,
                false,
                $config['ns'] ?? 'http://www.zetcom.com/ria/ws/module',
                $config['ns_is_prefix'] ?? false
            );
            libxml_use_internal_errors($internalErrors);
        } catch (\Exception $e) {
            libxml_use_internal_errors($internalErrors);
            throw new XmlParsingException(
                'Unable to parse response body into XML: ' . $e->getMessage(),
                null,
                $e
            );
        }
        return $xml;
    }
}
