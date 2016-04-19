<?php

namespace Keboola\Shinyapps\Test;

use Keboola\StorageApi\Client as SapiClient;
use Keboola\StorageApi\Components;
use Keboola\Shinyapps\Client;

use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

abstract class AbstractApiTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Shinyapps-bundle Component Name
     */
    const SHINY_COMPONENT_NAME = "shiny";

    /** @var  LoggerInterface */
    private static $logger;

    /**
     * @var \Keboola\StorageApi\Client
     */
    protected $_client;

    /**
     * @var \Keboola\StorageApi\Components
     */
    protected $_components;

    /**
     * @var \Keboola\Shinyapps\Client
     */
    protected $_shinyappsClient;

    public function __construct()
    {
        parent::__construct();

        $this->_client = new SapiClient(array(
            'token' => STORAGE_API_TOKEN,
            'backoffMaxTries' => 10,
        ));
        $this->_components = new Components($this->_client);

        $this->_shinyappsClient = Client::factory(['token' => STORAGE_API_TOKEN, 'url' => SHINYAPPS_API_URL]);
    }

    public static function getLogger()
    {
        if (!self::$logger) {
            self::$logger = new Logger(self::SHINY_COMPONENT_NAME);
            $handler = new SyslogHandler(self::SHINY_COMPONENT_NAME);
            $handler->setFormatter(new JsonFormatter());
            self::$logger->pushHandler($handler);
        }
        return self::$logger;
    }
}