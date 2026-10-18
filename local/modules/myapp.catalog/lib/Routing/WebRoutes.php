<?php

namespace MyApp\Catalog\Routing;

use Bitrix\Main\Routing\RoutingConfigurator;
use MyApp\Catalog\Controller\ApiController;
use MyApp\Catalog\Controller\CartController;
use MyApp\Catalog\Controller\CatalogController;
use MyApp\Catalog\Controller\CheckoutApiController;
use MyApp\Catalog\Controller\OrderController;
use MyApp\Catalog\Controller\OrderSuccessController;
use MyApp\Catalog\Controller\ProductController;

class WebRoutes
{
    public static function configure(RoutingConfigurator $routes): void
    {
        // ---------- Страницы (HTML) ----------
        $routes->get('/catalog/', [CatalogController::class, 'index']);
        /*$routes->get('/catalog/{code}', [CatalogController::class, 'section'])
            ->where('code', '[a-zA-Z0-9_-]+');*/
        $routes->get('/catalog/section/{id}', [CatalogController::class, 'section'])
            ->where('id', '[0-9]+');
        $routes->get('/catalog/element/{id}', [ProductController::class, 'index'])
            ->where('id', '[0-9]+');
        $routes->get('/cart/', [CartController::class, 'index']);
        $routes->get('/order/', [OrderController::class, 'index']);
        $routes->get('/order/success/{id}', [OrderSuccessController::class, 'index'])
            ->where('id', '[0-9]+');

        // ---------- JSON API для Vue ----------
        $routes->get('/api/v1/catalog/sections', static function () {
            return (new ApiController())->sections();
        });

        $routes->get('/api/v1/catalog/filters', static function () {
            return (new ApiController())->filters();
        });

        $routes->get('/api/v1/catalog/goods', static function () {
            return (new ApiController())->goods();
        });

        $routes->get('/api/v1/catalog/goods/{id}', static function (int $id) {
            return (new ApiController())->good($id);
        })->where('id', '[0-9]+');

        $routes->get('/api/v1/catalog/breadcrumbs', static function () {
            return (new ApiController())->breadcrumbs();
        });

        $routes->get('/api/v1/catalog/cart', static function () {
            return (new ApiController())->cart();
        });

        $routes->post('/api/v1/catalog/cart/add', static function () {
            return (new ApiController())->cartAdd();
        });

        $routes->post('/api/v1/catalog/cart/update', static function () {
            return (new ApiController())->cartUpdate();
        });

        $routes->post('/api/v1/catalog/cart/remove', static function () {
            return (new ApiController())->cartRemove();
        });

        $routes->get('/api/v1/checkout/init', static function () {
            return (new CheckoutApiController())->init();
        });

        $routes->get('/api/v1/checkout/locations', static function () {
            return (new CheckoutApiController())->locations();
        });

        $routes->post('/api/v1/checkout/calculate', static function () {
            return (new CheckoutApiController())->calculate();
        });

        $routes->post('/api/v1/checkout/submit', static function () {
            return (new CheckoutApiController())->submit();
        });
    }
}
