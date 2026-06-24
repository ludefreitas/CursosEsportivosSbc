<?php

namespace App\Core;

class Controller
{
    protected function view(string $view, array $data = []): void
    {
        View::render($view, $data);
    }

    protected function isAjaxRequest(): bool
    {
        return is_ajax_request();
    }

    protected function jsonResponse(array $payload, int $statusCode = 200): void
    {
        json_response($payload, $statusCode);
    }
}
