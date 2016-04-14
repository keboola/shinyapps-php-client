<?php

namespace Keboola\Shinyapps;

use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\MessageFormatter;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Class Client
 * @package Keboola\Orchestrator
 */
class Client extends \GuzzleHttp\Client
{
    const DEFAULT_API_URL = 'https://syrup.keboola.com';
    const DEFAULT_USER_AGENT = 'Keboola Shinyapps PHP Client';
    const DEFAULT_BACKOFF_RETRIES = 11;

    protected $jobFinishedStates = ["cancelled", "canceled", "success", "error", "terminated"];

    /**
     * @var int Maximum delay between queries for job state
     */
    protected $maxDelay = 10;

    /**
     * @var string Name of parent component
     */
    protected $super = '';

    /**
     * @var string storageApi token
     */
    private $token;

    /*
     * @var string Actual base request URL.
     */
    private $url;


    private static function createDefaultDecider($maxRetries = 3)
    {
        return function (
            $retries,
            RequestInterface $request,
            ResponseInterface $response = null,
            $error = null
        ) use ($maxRetries) {
            if ($retries >= $maxRetries) {
                return false;
            } elseif ($response && $response->getStatusCode() > 499) {
                return true;
            } elseif ($error) {
                return true;
            } else {
                return false;
            }
        };
    }


    /**
     * Create a client instance
     *
     * @param array $config Client configuration settings:
     *     - token: (required) Storage API token.
     *     - runId: (optional) Storage API runId.
     *     - url: (optional) Syrup API URL to override the default (DEFAULT_API_URL).
     *     - super: (optional) Name of parent component if any.
     *     - userAgent: (optional) Custom user agent (appended to the default).
     *     - backoffMaxTries: (optional) Number of retries in case of backend error.
     *     - logger: (optional) instance of Psr\Log\LoggerInterface.
     *     - handler: (optional) instance of GuzzleHttp\HandlerStack.
     * @param callable $delay Optional custom delay method to apply (default is exponential)
     * @return Client
     */
    public static function factory(array $config = [], callable $delay = null)
    {
        if (empty($config['token'])) {
            throw new \InvalidArgumentException('Storage API token must be set.');
        }

        $apiUrl = self::DEFAULT_API_URL;
        if (!empty($config['url'])) {
            $apiUrl = $config['url'];
        }
        $runId = '';
        if (!empty($config['runId'])) {
            $runId = $config['runId'];
        }
        $userAgent = self::DEFAULT_USER_AGENT;
        if (!empty($config['userAgent'])) {
            $userAgent .= ' - ' . $config['userAgent'];
        }
        $maxRetries = self::DEFAULT_BACKOFF_RETRIES;
        if (!empty($config['backoffMaxTries'])) {
            $maxRetries = $config['backoffMaxTries'];
        }

        // Initialize handlers (start with those supplied in constructor)
        if (isset($config['handler']) && is_a($config['handler'], HandlerStack::class)) {
            $handlerStack = HandlerStack::create($config['handler']);
        } else {
            $handlerStack = HandlerStack::create();

        }
        // Set exponential backoff for cases where job detail returns error
        $handlerStack->push(Middleware::retry(
            self::createDefaultDecider($maxRetries),
            $delay
        ));
        // Set handler to set default headers
        $handlerStack->push(Middleware::mapRequest(
            function (RequestInterface $request) use ($token, $runId, $userAgent) {
                $req = $request->withHeader('X-StorageApi-Token', $token)
                    ->withHeader('User-Agent', $userAgent);
                if (!$req->hasHeader('content-type')) {
                    $req = $req->withHeader('Content-type', 'application/json');
                }
                if ($runId) {
                    $req = $req->withHeader('X-KBC-RunId', $runId);
                }
                return $req;
            }
        ));

        // Set client logger
        if (isset($config['logger']) && is_a($config['logger'], LoggerInterface::class)) {
            $handlerStack->push(Middleware::log(
                $config['logger'],
                new MessageFormatter(
                    "{hostname} {req_header_User-Agent} - [{ts}] \"{method} {resource} {protocol}/{version}\" " .
                    "{code} {res_header_Content-Length}"
                )
            ));
        }

        // finally create the instance
        $client = new static(['base_url' => $apiUrl, 'handler' => $handlerStack]);
        $client->setUrl($apiUrl);
        // attach the token to the client
        $client->setToken($config['token']);
        if (!empty($config['super'])) {
            $client->setSuper($config['super']);
        }
        return $client;
    }


    /**
     * Set parent component.
     * @param string $super Name of the parent component.
     */
    protected function setSuper($super)
    {
        $this->super = $super;
    }


    /**
     * Set request URL
     * @param string $url Base url for requests.
     */
    protected function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * set storageApi token
     * @param string $token storageApi token
     */
    protected function setToken($token)
    {
        $this->token  = $token;
    }

    /**
     * Decode a JSON response.
     * @param Response $response
     * @return array Parsed response.
     * @throws ClientException In case response cannot be read properly.
     */
    private function decodeResponse(Response $response)
    {
        $data = json_decode($response->getBody()->read($response->getBody()->getSize()), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ClientException('Unable to parse response body into JSON: ' . json_last_error());
        }
        return $data === null ? array() : $data;
    }

    /**
     * Not implemented yet
     * List all Shiny applications in project.
     *
     * @return array Array with applications, each item has elements:
     *  'appId', 'name', 'description', 'url', 'status', 'dateCreated', 'version', 'source' which
     * contains 'packages', 'global', 'server', 'ui', 'rmarkdown'
     * @throws ClientException
    public function listApps()
    {
        $uri = new Uri($this->url);
        $uri = $uri->withPath("shinyapps/apps/");
        try {
            $request = new Request('GET', $uri);
            $response = $this->send($request);
        } catch (RequestException $e) {
            throw new ClientException($e->getMessage(), 0, $e);
        }
        $ret = $this->decodeResponse($response);
        return $ret['apps'];
    }
     */

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
     * @return array Job structure with items 'id', 'status', 'result'. Result contains array of app id and url.
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
        var_dump($ret)
        if (!isset($ret['result'])) {
            throw new ClientException("Invalid response.");
        } else {
            return $ret['result'];
        }
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

    public function pingApp($appId) {
        $uri = new Uri(this->uri);
        $uri = $uri->withPath("shinyapps/configs/" . $appId);
        try {
            $request = new Request('POST', $uri, ['X-StorageApi-Token' => $this->token], );
            $response = $this->send($request);
        } catch (RequestException $e) {
            throw new ClientException($e->getMessage(), 0, $e);
        }
        return $response->getStatusCode() == 200 || 202;
    }
}
