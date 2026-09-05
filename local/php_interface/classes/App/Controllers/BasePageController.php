<?php

namespace MyApp;

use Bitrix\Main\Engine\Controller;
use Bitrix\Main\HttpResponse;

abstract class BasePageController extends Controller
{
    protected function renderPage(array $initialState): HttpResponse
    {
        $html = $this->renderView(
            '/local/templates/main/views/layouts/main.php',
            $initialState,
            withSiteTemplate: false
        );

        $response = new HttpResponse();
        return $response->appendContent($html->getContent());
    }
}
