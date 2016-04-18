<?php

namespace Keboola\Shinyapps\Tests;

use Keboola\Shinyapps\Client;
use \Keboola\StorageApi\ClientException;
use \Keboola\StorageApi\Components;
use \Keboola\StorageApi\Options\Components\Configuration;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\StreamOutput;


class ApiTest extends \PHPUnit_Framework_TestCase
{
    const SHINY_COMPONENT_NAME = "shiny";

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

    /*
    protected function createConfigurationFromFile($configId, $fileName)
    {
        $conf = new Configuration();
        $conf->setComponentId(self::SHINY_COMPONENT_NAME);
        $conf->setConfigurationId($configId);
        $conf->setConfiguration(json_decode(file_get_contents(__DIR__ . '/data/' . $fileName), true));
        $conf->setName('shinyClientTest');
        $this->_components->addConfiguration($conf);
    }
    */

    protected function createSourceData($tableName, $fileName)
    {
        $table = new \Keboola\StorageApi\Table($this->_client, "in.c-shinyapps-test-data." . $tableName);
        $data = $table::csvStringToArray(file_get_contents(__DIR__ . '/_data/' . $fileName));
        $table->setFromArray($data, true);
        $table->save(true);
    }

    private function deleteConfig($config)
    {
        try{
            $this->_components->deleteConfiguration(self::SHINY_COMPONENT_NAME, $config);
        } catch(ClientException $ce) {
            // This is ok, it didn't exist
        }
    }

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->_client = new \Keboola\StorageApi\Client(array(
            'token' => STORAGE_API_TOKEN,
            'backoffMaxTries' => 1,
        ));
        $this->_components = new \Keboola\StorageApi\Components($this->_client);

        $this->_shinyappsClient = Client::factory(['token' => STORAGE_API_TOKEN, 'url' => SHINYAPPS_API_URL]);

        if ($this->_client->bucketExists("in.c-shinyapps-test")) {
            // Delete tables
            foreach ($this->_client->listTables("in.c-shinyapps-test") as $table) {
                $this->_client->dropTable($table["id"]);
            }
            // Delete bucket
            $this->_client->dropBucket("in.c-shinyapps-test");
        }

        // Delete the test configurations if they exist
        $this->deleteConfig("shinyapps-test");
        $this->deleteConfig("shinyapps-private-test");
    }

    /**
     * @inheritdoc
     */
    public function tearDown()
    {
        $this->_client = new Client(["token" => STORAGE_API_TOKEN]);

        if ($this->_client->bucketExists("in.c-shinyapps-test")) {
            // Delete tables
            foreach ($this->storageApiClient->listTables("in.c-shinyapps-test") as $table) {
                $this->storageApiClient->dropTable($table["id"]);
            }
            // Delete bucket
            $this->_client->dropBucket("in.c-shinyapps-test");
        }
    }

    public function testStandardApp()
    {
        $configId = "shinyapps-test";

        $appConfig = json_decode(file_get_contents(__DIR__ . '/_data/shinyapps-test.json'), true);

        $result = $this->_shinyappsClient->createApp("test app", "test app description", $appConfig);
        echo "create returned";
        $this->assertArrayHasKey("url",$result);
        $this->assertArrayHasKey("configId",$result);
        $this->assertEquals($configId, $result['configId']);

        try {
            $appExists = $this->_shinyappsClient->pingApp($result['url']);
            $this->assertTrue($appExists, "The application was created successfully.");
        } catch(HTTPException $e) {
            $this->assertTrue(false,"Application Ping failed: " . $e->getMessage());
        }

        $this->_shinyappsClient->archiveApp($configId);

        try {
            $appExists = $this->_shinyappsClient->pingApp($result['url']);
            $this->fail("Application should no longer exist so ping should throw exception");
        } catch (\Exception $e) {

        }
    }

    public function testPrivateApp()
    {
        $configId = "shinyapps-private-test";

        $appConfig = json_decode(file_get_contents(__DIR__ . '/_data/shinyapps-private-test.json'), true);

        $result = $this->_shinyappsClient->createApp("Pivate app", "Private app description", $appConfig);

        $this->assertArrayHasKey("url",$result);
        $this->assertArrayHasKey("configId",$result);
        $this->assertEquals($configId, $result['configId']);

        try {
            $appExists = $this->appManagementService->pingApp($result['url']);
            $this->assertTrue($appExists, "The application was created successfully.");
        } catch(Exception $e) {
            $this->assertTrue(false,"Application Ping failed: " . $e->getMessage());
        }

        $this->appManagementService->archiveApp($configId);

        try {
            $appExists = $this->appManagementService->pingApp($result['url']);
            $this->fail("Application should no longer exist so ping should throw exception");
        } catch (\Exception $e) {

        }
    }
}
