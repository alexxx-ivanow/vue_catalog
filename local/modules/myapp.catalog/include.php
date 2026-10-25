<?php

use Bitrix\Main\Loader;

Loader::registerAutoLoadClasses('myapp.catalog', [
    'MyApp\\Catalog\\Controller\\BasePageController' => 'lib/Controller/BasePageController.php',
    'MyApp\\Catalog\\Controller\\HomeController' => 'lib/Controller/HomeController.php',
    'MyApp\\Catalog\\Controller\\CatalogController' => 'lib/Controller/CatalogController.php',
    'MyApp\\Catalog\\Controller\\ProductController' => 'lib/Controller/ProductController.php',
    'MyApp\\Catalog\\Controller\\CartController' => 'lib/Controller/CartController.php',
    'MyApp\\Catalog\\Controller\\ApiController' => 'lib/Controller/ApiController.php',
    'MyApp\\Catalog\\Controller\\CheckoutApiController' => 'lib/Controller/CheckoutApiController.php',
    'MyApp\\Catalog\\Controller\\OrderController' => 'lib/Controller/OrderController.php',
    'MyApp\\Catalog\\Controller\\OrderSuccessController' => 'lib/Controller/OrderSuccessController.php',
    'MyApp\\Catalog\\Service\\CheckoutService' => 'lib/Service/CheckoutService.php',
    'MyApp\\Catalog\\Checkout\\DeliveryUiResolver' => 'lib/Checkout/DeliveryUiResolver.php',
    'MyApp\\Catalog\\Service\\CheckoutUserService' => 'lib/Service/CheckoutUserService.php',
    'MyApp\\Catalog\\Service\\HomeService' => 'lib/Service/HomeService.php',
    'MyApp\\Catalog\\Service\\CatalogService' => 'lib/Service/CatalogService.php',
    'MyApp\\Catalog\\Service\\CartService' => 'lib/Service/CartService.php',
    'MyApp\\Catalog\\Routing\\WebRoutes' => 'lib/Routing/WebRoutes.php',
    'MyApp\\Catalog\\Config\\Assets' => 'lib/Config/Assets.php',
    'MyApp\\Catalog\\Config\\Templates' => 'lib/Config/Templates.php',
]);
