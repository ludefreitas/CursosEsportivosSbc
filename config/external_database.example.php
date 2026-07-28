<?php

/**
 * Modelo da conexão usada somente para consultar pessoas em outro banco.
 *
 * Copie como config/external_database.local.php e informe as credenciais.
 * O arquivo local é ignorado pelo Git e não deve ser publicado.
 */
return [
    'host' => 'localhost',
    'port' => '3306',
    'dbname' => 'db_cursosesportivossbc',
    'username' => 'root',
    'password' => '',
    'charset' => 'utf8mb4',
];
