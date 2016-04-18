<?php

namespace Keboola\Shinyapps;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Keboola\StorageApi\Client as SapiClient;
use Keboola\Syrup\Client as SyrupClient;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Options\Components\Configuration;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Exception\Exception;

/**
 * Class Client
 * @package Keboola\Orchestrator
 */
class Client extends \Keboola\Syrup\Client
{
    const SHINYAPPS_COMPONENT = "shiny";

    /**
     * @var SapiClient $sapilient
     */
    protected $sapilient;

    /**
     * @var string storageApi token
     */
    private $token;

    /**
     * set storageApi token
     * @param string $token storageApi token
     */
    protected function setToken($token)
    {
        $this->token  = $token;
    }

    /**
     * @param array $config
     * @param callable|null $delay
     * @return static ShinyappsClient
     */
    public static function factory(array $config = [], callable $delay = null)
    {
        $client = parent::factory($config, $delay);

        if (!empty($config['url'])) {
            $client->setUrl($config['url']);
        } else {
            $client->setUrl(self::DEFAULT_API_URL);
        }
        $client->setToken($config['token']);
        var_dump($client);
        return $client;
    }

    /**
     * Not implemented yet
     * List all Shiny applications in project.
     * Currently receives 'unimplemented exception' on request
     * @return array Array with applications
     * @throws ClientException
     */
    public function listApps()
    {
        $uri = new Uri($this->url);
        $uri = $uri->withPath("shinyapps/configs/");
        try {
            $request = new Request('GET', $uri);
            $response = $this->send($request);
        } catch (RequestException $e) {
            throw new ClientException($e->getMessage(), $e->getCode(), $e);
        }
        $ret = $this->decodeResponse($response);
        return $ret['apps'];
    }

    /**
     * Create a new Shiny application.
     * Note that if this is the first instance of the specified repository being created,
     * this action will debloy the application to shinyapps.io and the process will take some time.
     * If the app had been previously deployed and the latest version is up to date,
     * this action will only register the new shinyapp configuration supplied here and be much quicker
     ***
     * @param string $name Name of the application.
     * @param string $description Optional description of the application.
     * @param array $appConfig Required app configuration containing
     ***
     * @param array $options Optional cURL request options.
     ***
     * @return full app configuration including url
     * @throws ClientException
     */
    public function createApp($name, $description, $appConfig, $options=array())
    {
        $params = $options;
        $params['name'] = $name;
        $params['description'] = $description;

        $uri = new Uri($this->url);
        $uri = $uri->withPath("shinyapps/configs");
        try {
            $request = new Request('POST', $uri, [], json_encode($params));
            $response = $this->send($request);
        } catch (RequestException $e) {
            throw new ClientException($e->getMessage(), 0, $e);
        }
        $ret = $this->decodeResponse($response);
        $sapiClient = new \Keboola\StorageApi\Client(['token' => $this->token]);
        $cmp = new Components($sapiClient);
        $cfg = new Configuration();
        $cfg->setComponentId(self::SHINYAPPS_COMPONENT);
        $cfg->setConfigurationId($ret['id']);
        $cfg->setName($name);
        $cfg->setDescription($description);
        $cfg->setConfiguration($appConfig);
        try {
            $cmp->getConfiguration(self::SHINYAPPS_COMPONENT, $ret['id']);
            $cmp->updateConfiguration($cfg);
        } catch (\Keboola\StorageApi\ClientException $e) {
            if ($e->getCode() == 404) {
                // component configuration doesn't exist yet, need to create it
                $cmp->addConfiguration($cfg);
            } else {
                throw new ClientException($e->getMessage(), $e->getCode(),$e);
            }
        }

        try {
            $this->pingApp($ret['id']);
        } catch (ClientException $e) {
            // the app doesn't exist, so we need to deploy it
            $res = $this->runJob(self::SHINYAPPS_COMPONENT,["config" => $ret['id']]);
        }
        $uri = new Uri($this->url);
        $uri = $uri->withPath("shinyapps/configs/" . $ret['id']);
        return [
            "configId" => $ret['id'],
            "url" => $uri
        ];
    }

    /**
     * Delete a Shiny application.
     *
     * @param string $appId Application Id {@see listApps()}.
     * @return bool True on success, false on failure.
     * @throws ClientException
     */
    public function deleteApp($appId)
    {

        $uri = new Uri($this->url);
        $uri = $uri->withPath("shinyapps/configs/" . $appId);
        try {
            $request = new Request('DELETE', $uri);
            $response = $this->send($request);
        } catch (RequestException $e) {
            throw new ClientException($e->getMessage(), 0, $e);
        }
        return $response->getStatusCode() == 204;
    }

    private function pingApp($appId) {
        $uri = new Uri($this->url);
        $uri = $uri->withPath("shinyapps/configs/" . $appId);
        try {
            $request = new Request('POST', $uri, [], json_encode(['X-StorageApi-Token' => $this->token]));
            $response = $this->send($request);
        } catch (RequestException $e) {
            throw new ClientException($e->getMessage(), 0, $e);
        }
        return $response->getStatusCode() == 200 || 202;
    }
}
