<?php

// Define path to application directory
define('ROOT_PATH', __DIR__);

ini_set('display_errors', true);
error_reporting(E_ALL);

date_default_timezone_set('Europe/Prague');

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

defined('STORAGE_API_URL')
|| define('STORAGE_API_URL', getenv('STORAGE_API_URL') ? getenv('STORAGE_API_URL') : 'https://connection.keboola.com');

defined('SHINYAPPS_API_URL')
|| define('SHINYAPPS_API_URL', getenv('SHINYAPPS_API_URL') ? getenv('SHINYAPPS_API_URL') : 'https://syrup.keboola.com/shinyapps');

defined('STORAGE_API_TOKEN')
|| define('STORAGE_API_TOKEN', getenv('STORAGE_API_TOKEN') ? getenv('STORAGE_API_TOKEN') : 'your_token');

require_once ROOT_PATH . '/Keboola/Shinyapps/AbstractApiTest.php';
require_once ROOT_PATH . '/../vendor/autoload.php';
