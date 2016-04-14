<?php

namespace Keboola\ShinyappsBundle\Tests\Services;

use Keboola\StorageApi\ClientException;
use Monolog\Logger;
use Keboola\ShinyappsBundle\Services\AppManagementService;
use Keboola\Syrup\Service\StorageApi\StorageApiService;
use Keboola\ShinyappsBundle\Services\JobExecutorService;
use Keboola\StorageApi\Client;
use Keboola\Syrup\Job\Metadata\Job;
use Keboola\StorageApi\Components;
use Keboola\StorageApi\Table;
use Keboola\StorageApi\Options\Components\Configuration;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\HttpFoundation\RequestStack;


class AppsTest extends KernelTestCase
{
    const SHINY_COMPONENT_NAME = "shiny";

    /**
     * @var Keboola\StorageApi\Client
     */
    protected $_client;

    /**
     * @var Keboola\StorageApi\Components
     */
    protected $_components;

    public function setUp()
    {
        $this->_client = new Keboola\StorageApi\Client(array(
            'token' => STORAGE_API_TOKEN,
            'url' => STORAGE_API_URL,
            'backoffMaxTries' => 1,
        ));
        $this->_components = new Keboola\StorageApi\Components($this->_client);
    }


    protected function createConfigurationFromFile($configId, $fileName)
    {
        $conf = new Configuration();
        $conf->setComponentId(self::SHINY_COMPONENT_NAME);
        $conf->setConfigurationId($configId);
        $conf->setConfiguration(json_decode(file_get_contents(__DIR__ . '/data/' . $fileName), true));
        $conf->setName('shinyClientTest');

        $this->_components->addConfiguration($conf);
    }

    protected function createSourceData($tableName, $fileName)
    {
        $table = new Keboola\StorageApi\Table($this->_client, "in.c-shinyapps-test-data." . $tableName);
        $data = $table::csvStringToArray(file_get_contents(__DIR__ . '/data/' . $fileName));
        $table->setFromArray($data, true);
        $table->save(true);
    }

    private function deleteConfig($config) {
        $cmp = new Components($this->storageApiClient);
        try{
            $cmp->deleteConfiguration(AppManagementService::COMPONENT_NAME, $config);
        } catch(ClientException $ce) {
            // This is ok, it didn't exist
        }
    }

    /**
     * @inheritdoc
     */
    public function setUp()
    {
        $this->log = new Logger("shinyapps-test");

        $this->shinyConfig = array(
            "account" => "keboola",
            "token" => SHINYAPPS_TOKEN,
            "secret" => SHINYAPPS_SECRET,
            "redirectUrl" => "https://syrup.keboola.com/shinyapps"
        );

        $this->storageApiClient = new Client(["token" => STORAGE_API_TOKEN]);

        if ($this->storageApiClient->bucketExists("in.c-shinyapps-test")) {
            // Delete tables
            foreach ($this->storageApiClient->listTables("in.c-shinyapps-test") as $table) {
                $this->storageApiClient->dropTable($table["id"]);
            }
            // Delete bucket
            $this->storageApiClient->dropBucket("in.c-shinyapps-test");
        }

        self::bootKernel();

        // Delete the test configurations if they exist
        $this->deleteConfig("shinyapps-test");
        $this->deleteConfig("shinyapps-private-test");

        // Set the appmanagement and job execution service instances
        $sapiService = new StorageApiService(new RequestStack());
        $sapiService->setClient($this->storageApiClient);
        $this->appManagementService = new AppManagementService($this->log, $this->shinyConfig, $sapiService);

        // make jobe executor service
        $this->executor = new JobExecutorService(
            $this->log, $this->shinyConfig, $this->appManagementService
        );
    }

    /**
     * @inheritdoc
     */
    public function tearDown()
    {
        $this->storageApiClient = new Client(["token" => STORAGE_API_TOKEN]);

        if ($this->storageApiClient->bucketExists("in.c-shinyapps-test")) {
            // Delete tables
            foreach ($this->storageApiClient->listTables("in.c-shinyapps-test") as $table) {
                $this->storageApiClient->dropTable($table["id"]);
            }
            // Delete bucket
            $this->storageApiClient->dropBucket("in.c-shinyapps-test");
        }
    }

    /**
     *  This should create an app using the supplied configuration
     * @param String $configId - the component config to use.
     */
    private function createApp($configId) {

        // Create the job configuration shinyapps-test from the file data/test-config.csv
        $this->createConfigurationFromFile($configId,$configId . ".json");
        $encryptor = self::$kernel->getContainer()->get('syrup.object_encryptor');

        $job = new Job($encryptor);
        $job->setParams(
            array(
                "config"=>$configId,
            )
        );
        $this->log->debug("ALL SET, Executing deployApp job");

        $result = $this->executor->execute($job);

        $this->log->debug("Deploy job finished");

        return $result;
    }

    public function testStandardApp()
    {
        $configId = "shinyapps-test";
        $result = $this->createApp($configId);

        $this->assertArrayHasKey("url",$result);
        $this->assertArrayHasKey("configId",$result);
        $this->assertEquals($configId, $result['configId']);

        $this->log->debug("Application URL: " . $result['url']);

        try {
            $appExists = $this->appManagementService->pingApp($result['url']);
            $this->assertTrue($appExists, "The application was created successfully.");
        } catch(Exception $e) {
            $this->assertTrue(false,"Application Ping failed: " . $e->getMessage());
        }
        $this->log->debug("App deployed successfully. Test Delete ");

        $this->appManagementService->archiveApp($configId);

        try {
            $appExists = $this->appManagementService->pingApp($result['url']);
            $this->fail("Application should no longer exist so ping should throw exception");
        } catch (\Exception $e) {

        }
    }

    public function testPrivateApp()
    {
        $configId = "shinyapps-private-test";
        $result = $this->createApp($configId);

        $this->assertArrayHasKey("url",$result);
        $this->assertArrayHasKey("configId",$result);
        $this->assertEquals($configId, $result['configId']);

        $this->log->debug("Application URL: " . $result['url']);

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
