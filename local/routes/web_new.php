<?php
namespace App\Routing;

use Bitrix\Main\Routing\RoutingConfigurator;
use MyApp\CatalogController;
use MyApp\ProductController;
use MyApp\ApiController;

return function (RoutingConfigurator $routes)
{
    // ---------- Страницы (HTML) ----------
    $routes->get('/catalog/', [CatalogController::class, 'index']);
    /*$routes->get('/catalog/{code}', [CatalogController::class, 'section'])
        ->where('code', '[a-zA-Z0-9_-]+');*/
    $routes->get('/catalog/section/{id}', [CatalogController::class, 'section'])
        ->where('id', '[0-9]+');
    $routes->get('/catalog/element/{id}', [ProductController::class, 'index'])
        ->where('id', '[0-9]+');

    // ---------- JSON API для Vue ----------
    $routes->get('/api/v1/catalog/filters', function () {
        return (new ApiController())->filters();
    });

    $routes->get('/api/v1/catalog/goods', function () {
        return (new ApiController())->goods();
    });

    $routes->get('/api/v1/catalog/goods/{id}', function (int $id) {
        return (new ApiController())->good($id);
    })->where('id', '[0-9]+');

    $routes->get('/api/v1/catalog/breadcrumbs', function () {
        return (new ApiController())->breadcrumbs();
    });

    $routes->post('/api/v1/catalog/cart/add', function () {
        return (new ApiController())->cartAdd();
    });
};
