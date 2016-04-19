<?php
namespace Keboola\Shinyapps\Test;

use \Keboola\Shinyapps\Client;
use \Keboola\StorageApi\ClientException;

class ApiTest extends AbstractApiTest
{

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


    public function testStandardApp()
    {
        $configId = "shinyapps-test";

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

        $appConfig = json_decode(file_get_contents(__DIR__ . '/_data/shinyapps-test.json'), true);

        $result = $this->_shinyappsClient->createApp("test app", "test app description", $appConfig);
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
            $this->assertEquals(404, $e->getCode());
        }
    }

    public function testPrivateApp()
    {
        $configId = "shinyapps-private-test";

        if ($this->_client->bucketExists("in.c-shinyapps-test")) {
            // Delete tables
            foreach ($this->_client->listTables("in.c-shinyapps-test") as $table) {
                $this->_client->dropTable($table["id"]);
            }
            // Delete bucket
            $this->_client->dropBucket("in.c-shinyapps-test");
        }

        // Delete the test configurations if they exist
        $this->deleteConfig("shinyapps-private-test");

        $appConfig = json_decode(file_get_contents(__DIR__ . '/_data/shinyapps-private-test.json'), true);

        $result = $this->_shinyappsClient->createApp("Pivate app", "Private app description", $appConfig);

        $this->assertArrayHasKey("url",$result);
        $this->assertArrayHasKey("configId",$result);
        $this->assertEquals($configId, $result['configId']);

        try {
            $appExists = $this->appManagementService->pingApp($result['url']);
            $this->assertTrue($appExists, "The application was created successfully.");
        } catch(Exception $e) {
            $this->fail("Application Ping failed: " . $e->getMessage());
        }

        $this->_shinyappsClient->archiveApp($configId);

        try {
            $appExists = $this->appManagementService->pingApp($result['url']);
            $this->fail("Application should no longer exist so ping should throw exception");
        } catch (\Exception $e) {
            $this->assertEquals(404, $e->getCode());
        }
    }
}
