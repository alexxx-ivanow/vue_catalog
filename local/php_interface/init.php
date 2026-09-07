<?php

use Bitrix\Main\EventManager;
use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses(null, [
    'MyApp\BasePageController'  => '/local/php_interface/classes/App/Controllers/BasePageController.php',
    'MyApp\CatalogController'  => '/local/php_interface/classes/App/Controllers/CatalogController.php',    
    'MyApp\ApiController'  => '/local/php_interface/classes/App/Controllers/Api/ApiController.php',    
    'MyApp\ProductController'  => '/local/php_interface/classes/App/Controllers/ProductController.php',

    'MyApp\CatalogService'  => '/local/php_interface/services/CatalogService.php',
    'MyApp\CartService'  => '/local/php_interface/services/CartService.php',
]);