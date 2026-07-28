<?php

declare(strict_types=1);

$target = '/teste-erros';

if (!empty($_SERVER['QUERY_STRING'])) {
    $target .= '?' . $_SERVER['QUERY_STRING'];
}

header('Location: ' . $target, true, 302);
exit;

// para testar usar após o link da página: teste-erros?codigo=500&disparar=1
