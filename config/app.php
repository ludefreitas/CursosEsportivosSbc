<?php

$config = [
    'name' => 'Cursos Esportivos SBC',
    'base_url' => '',
    'session_name' => 'cursos_esportivos_sbc_session',
    'turnstile_site_key' => (string) (getenv('TURNSTILE_SITE_KEY') ?: ''),
    'turnstile_secret_key' => (string) (getenv('TURNSTILE_SECRET_KEY') ?: ''),
    'turnstile_login_failure_threshold' => 3,
];

$localConfigFile = __DIR__ . '/app.local.php';
if (is_file($localConfigFile)) {
    $localConfig = require $localConfigFile;
    if (is_array($localConfig)) {
        $config = array_replace($config, $localConfig);
    }
}

return $config;
