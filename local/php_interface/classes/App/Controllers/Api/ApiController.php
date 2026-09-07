<?php

namespace MyApp;

use Bitrix\Main\Context;
use Bitrix\Main\Engine\Response\Json;
use MyApp\CartService;

class ApiController
{
    private function request()
    {
        return Context::getCurrent()->getRequest();
    }

    public function filters(): Json
    {
        $sectionId = $this->request()->get('section') ? (int)$this->request()->get('section') : null;

        try {
            $data = CatalogService::getFilters((int)$sectionId);
            return new Json($data);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function goods(): Json
    {
        $request = $this->request();

        $manufacturers = $this->normalizeIntList($request->get('manufacturer') ?? $request->get('manufacturers'));
        $sizes = $this->normalizeIntList($request->get('size') ?? $request->get('sizes'));
        $materials = $this->normalizeStringList($request->get('material') ?? $request->get('materials'));

        $filter = [
            'article'      => (string)($request->get('article') ?? ''),
            'price_from'   => $request->get('price_from') !== null ? (float)$request->get('price_from') : null,
            'price_to'     => $request->get('price_to') !== null ? (float)$request->get('price_to') : null,
            'sizes'        => $sizes,
            'manufacturers'=> $manufacturers,
            'materials'    => $materials,
        ];

        $page  = max(1, (int)$request->get('page') ?: 1);
        $limit = min(48, max(1, (int)$request->get('limit') ?: 12));
        $sectionId = max(0, (int)$request->get('section'));

        try {
            $result = CatalogService::getGoods($filter, $page, $limit, $sectionId);
            return new Json($result);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function good(int $id): Json
    {
        try {
            $product = CatalogService::getProduct($id);
            if (!$product) {
                return new Json(['error' => 'Товар не найден'], 404);
            }
            return new Json($product);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function breadcrumbs(): Json
    {
        $request = $this->request();
        $pageType = (string)($request->get('pageType') ?? 'catalog');
        $sectionId = max(0, (int)$request->get('section'));
        $productId = max(0, (int)$request->get('product'));

        try {
            $items = CatalogService::getBreadcrumbs($pageType, $sectionId, $productId);
            return new Json(['items' => $items]);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    public function cartAdd(): Json
    {
        $request = $this->request();

        $body = json_decode(file_get_contents('php://input'), true);

        $productId = (int)($body['productId'] ?? 0);
        $quantity  = max(1, (int)($body['quantity'] ?: 1));

        if ($productId <= 0) {
            return new Json([
                'error' => 'Некорректный productId',
                'data' => $body
            ], 400);
        }

        try {
            $result = CartService::addProduct($productId, $quantity);

            if ($body['sessid'] !== bitrix_sessid()) {
                return new Json([
                    'error' => 'Invalid sessid',
                    'success' => false
                ], 400);
            }

            return new Json($result);
        } catch (\Throwable $e) {
            return $this->errorResponse($e);
        }
    }

    private function errorResponse(\Throwable $e): Json
    {
        // В проде — не отдавать $e->getMessage() наружу без надобности,
        // логировать через \Bitrix\Main\Diag\Debug::writeToFile или монитор ошибок.

        //return new Json(['error' => 'Внутренняя ошибка сервера'], 500);
        return new Json(['error' => $e->getMessage()], 500);
    }

    private function normalizeIntList(mixed $value): array
    {
        $items = is_array($value) ? $value : [$value];
        $result = [];
        foreach ($items as $item) {
            if ($item === null || $item === '') {
                continue;
            }
            $intValue = (int)$item;
            if ($intValue > 0) {
                $result[] = $intValue;
            }
        }

        return array_values(array_unique($result));
    }

    private function normalizeStringList(mixed $value): array
    {
        $items = is_array($value) ? $value : [$value];
        $result = [];
        foreach ($items as $item) {
            if ($item === null) {
                continue;
            }
            $str = trim((string)$item);
            if ($str !== '') {
                $result[] = $str;
            }
        }

        return array_values(array_unique($result));
    }
}