<?php

$config = [
    'name' => 'Cursos Esportivos SBC',
    'base_url' => '',
    'session_name' => 'cursos_esportivos_sbc_session',
    'human_verification_login_failure_threshold' => 3,
    'performance_slow_request_seconds' => max(
        0.1,
        (float) (getenv('PERFORMANCE_SLOW_REQUEST_SECONDS') ?: 2)
    ),
];

$localConfigFile = __DIR__ . '/app.local.php';
if (is_file($localConfigFile)) {
    $localConfig = require $localConfigFile;
    if (is_array($localConfig)) {
        $config = array_replace($config, $localConfig);
    }
}

return $config;
