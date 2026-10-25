<?php
// Executar separadamente: php scripts/migrate_course_interest.php --apply
if (PHP_SAPI !== 'cli' || ($argv[1] ?? '') !== '--apply') { fwrite(STDERR,"Use: php scripts/migrate_course_interest.php --apply\n"); exit(1); }
define('ROOT_PATH',dirname(__DIR__));
require ROOT_PATH.'/app/Helpers/functions.php';
require ROOT_PATH.'/app/Core/Database.php';
$pdo=App\Core\Database::connection();
$pdo->exec("CREATE TABLE IF NOT EXISTS curso_interesse_diario (
    dia DATE NOT NULL,
    tipo ENUM('local','modalidade','turma') NOT NULL,
    entidade_id BIGINT UNSIGNED NOT NULL,
    temporada_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    local_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    modalidade_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    turma_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    periodo ENUM('manha','tarde','noite','sem_horario') NOT NULL DEFAULT 'sem_horario',
    total BIGINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY(dia,tipo,entidade_id),
    INDEX idx_interesse_temporada (temporada_id,dia,tipo),
    INDEX idx_interesse_turma (turma_id,dia)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "Contagem diária de interesse preparada. Nenhum histórico de cliques foi criado artificialmente.\n";
