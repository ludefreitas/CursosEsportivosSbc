<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Services\CepService;

class CepController extends Controller
{
    /**
     * Valida CEP em tempo real para o front-end.
     */
    public function validate(): void
    {
        $service = new CepService();
        $cep = (string) ($_GET['cep'] ?? '');

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($service->avaliarCep($cep), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
