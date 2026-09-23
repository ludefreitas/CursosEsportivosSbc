<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;
use Throwable;

class ClassCopyService
{
    private const LEGACY_SEASON_ID = 8;
    private ?PDO $legacyConnection = null;

    public function prepareSchema(): void
    {
        // Estrutura preparada exclusivamente pelos scripts de migração.
        // Nenhuma requisição da aplicação deve executar DDL.
    }

    public function importLegacySeason2026(): array
    {
        $rows = $this->legacy()->prepare('SELECT t.*, m.descmodal, a.geneativ, a.prograativ,
                f.initidade, f.fimidade, h.horainicio, h.horatermino, h.diasemana,
                e.idespaco AS legacy_space_id, e.nomeespaco, l.idlocal AS legacy_location_id, l.apelidolocal, l.nomelocal
            FROM tb_turmatemporada tt
            INNER JOIN tb_turma t ON t.idturma = tt.idturma
            LEFT JOIN tb_modalidade m ON m.idmodal = t.idmodal
            LEFT JOIN tb_atividade a ON a.idativ = t.idativ
            LEFT JOIN tb_fxetaria f ON f.idfxetaria = a.idfxetaria
            LEFT JOIN tb_horario h ON h.idhorario = t.idhorario
            LEFT JOIN tb_espaco e ON e.idespaco = t.idespaco
            LEFT JOIN tb_local l ON l.idlocal = e.idlocal
            WHERE tt.idtemporada = :season_id
            ORDER BY t.idturma');
        $rows->execute([':season_id' => self::LEGACY_SEASON_ID]);
        $records = $rows->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $pdo = Database::connection();
        $upsert = $pdo->prepare('INSERT INTO turmas_externas_migracao
            (temporada_id_externa, turma_id_externa, turma_nome, modalidade_id_externa, modalidade_nome,
             local_id_externo, local_nome, local_apelido, espaco_id_externo, espaco_nome, dados_json, importado_em)
            VALUES (:season_id, :class_id, :class_name, :modality_id, :modality_name,
                    :location_id, :location_name, :location_nickname, :space_id, :space_name, :payload, NOW())
            ON DUPLICATE KEY UPDATE turma_nome=VALUES(turma_nome), modalidade_id_externa=VALUES(modalidade_id_externa),
                modalidade_nome=VALUES(modalidade_nome), local_id_externo=VALUES(local_id_externo),
                local_nome=VALUES(local_nome), local_apelido=VALUES(local_apelido), espaco_id_externo=VALUES(espaco_id_externo),
                espaco_nome=VALUES(espaco_nome), dados_json=VALUES(dados_json), importado_em=NOW()');
        $pdo->beginTransaction();
        try {
            foreach ($records as $row) {
                $upsert->execute([
                    ':season_id' => self::LEGACY_SEASON_ID,
                    ':class_id' => (int) ($row['idturma'] ?? 0),
                    ':class_name' => trim((string) ($row['descturma'] ?? '')),
                    ':modality_id' => (int) ($row['idmodal'] ?? 0),
                    ':modality_name' => trim((string) ($row['descmodal'] ?? '')),
                    ':location_id' => (int) ($row['legacy_location_id'] ?? 0) ?: null,
                    ':location_name' => trim((string) ($row['nomelocal'] ?? '')) ?: null,
                    ':location_nickname' => trim((string) ($row['apelidolocal'] ?? '')) ?: null,
                    ':space_id' => (int) ($row['legacy_space_id'] ?? 0) ?: null,
                    ':space_name' => trim((string) ($row['nomeespaco'] ?? '')) ?: null,
                    ':payload' => json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
                ]);
            }
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }
        $stored = Database::connection()->prepare('SELECT COUNT(*) FROM turmas_externas_migracao WHERE temporada_id_externa = :season_id');
        $stored->execute([':season_id' => self::LEGACY_SEASON_ID]);
        return ['temporada_id' => self::LEGACY_SEASON_ID, 'importadas' => count($records), 'armazenadas' => (int) $stored->fetchColumn()];
    }

    public function sourceSeasons(): array
    {
        $current = Database::connection()->query('SELECT id, nome FROM temporadas ORDER BY data_inicio DESC, id DESC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $legacyCount = (int) Database::connection()->query('SELECT COUNT(*) FROM turmas_externas_migracao WHERE temporada_id_externa = ' . self::LEGACY_SEASON_ID)->fetchColumn();
        $items = $legacyCount > 0 ? [['id' => 'legacy:' . self::LEGACY_SEASON_ID, 'nome' => '2026 — site antigo', 'legacy' => true]] : [];
        foreach ($current as $season) {
            $items[] = ['id' => 'current:' . (int) $season['id'], 'nome' => (string) $season['nome'], 'legacy' => false];
        }
        return $items;
    }

    public function sourceModalities(string $sourceSeason): array
    {
        [$source, $seasonId] = $this->parseSourceSeason($sourceSeason);
        if ($source === 'legacy') {
            $stmt = Database::connection()->prepare('SELECT DISTINCT modalidade_id_externa AS id, modalidade_nome AS nome
                FROM turmas_externas_migracao WHERE temporada_id_externa = :season_id
                ORDER BY modalidade_nome, modalidade_id_externa');
        } else {
            $stmt = Database::connection()->prepare('SELECT DISTINCT m.id, m.nome
                FROM turmas t INNER JOIN modalidades m ON m.id = t.modalidade_id
                WHERE t.temporada_id = :season_id ORDER BY m.nome, m.id');
        }
        $stmt->execute([':season_id' => $seasonId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function sourceClasses(string $sourceSeason, int $modalityId): array
    {
        if ($modalityId <= 0) throw new RuntimeException('Selecione uma modalidade.');
        [$source, $seasonId] = $this->parseSourceSeason($sourceSeason);
        if ($source === 'legacy') {
            $stmt = Database::connection()->prepare('SELECT turma_id_externa AS id, turma_nome AS nome
                FROM turmas_externas_migracao
                WHERE temporada_id_externa = :season_id AND modalidade_id_externa = :modality_id
                ORDER BY turma_id_externa, turma_nome');
        } else {
            $stmt = Database::connection()->prepare('SELECT id, nome FROM turmas
                WHERE temporada_id = :season_id AND modalidade_id = :modality_id ORDER BY id, nome');
        }
        $stmt->execute([':season_id' => $seasonId, ':modality_id' => $modalityId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function sourceClassesForDestinationFilters(string $sourceSeason, int $destinationModalityId, int $destinationLocationId): array
    {
        if ($destinationModalityId <= 0) throw new RuntimeException('Selecione primeiro a modalidade da nova turma.');
        if ($destinationLocationId <= 0) throw new RuntimeException('Selecione primeiro o local da nova turma.');
        $stmt = Database::connection()->prepare('SELECT nome FROM modalidades WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $destinationModalityId]);
        $destinationName = trim((string) $stmt->fetchColumn());
        if ($destinationName === '') throw new RuntimeException('A modalidade selecionada não foi encontrada.');

        foreach ($this->sourceModalities($sourceSeason) as $sourceModality) {
            if ($this->normalize((string) ($sourceModality['nome'] ?? '')) === $this->normalize($destinationName)) {
                [$source, $seasonId] = $this->parseSourceSeason($sourceSeason);
                $sourceModalityId = (int) ($sourceModality['id'] ?? 0);
                if ($source === 'current') {
                    $classes = Database::connection()->prepare('SELECT id, nome FROM turmas
                        WHERE temporada_id = :season_id AND modalidade_id = :modality_id AND local_treino_id = :location_id
                        ORDER BY id, nome');
                    $classes->execute([':season_id' => $seasonId, ':modality_id' => $sourceModalityId, ':location_id' => $destinationLocationId]);
                    return $classes->fetchAll(PDO::FETCH_ASSOC) ?: [];
                }

                $classes = Database::connection()->prepare('SELECT turma_id_externa AS id, turma_nome AS nome,
                        local_id_externo AS legacy_location_id, local_apelido AS apelidolocal, local_nome AS nomelocal
                    FROM turmas_externas_migracao
                    WHERE temporada_id_externa = :season_id AND modalidade_id_externa = :modality_id
                    ORDER BY turma_id_externa, turma_nome');
                $classes->execute([':season_id' => $seasonId, ':modality_id' => $sourceModalityId]);
                $pdo = Database::connection();
                return array_values(array_filter($classes->fetchAll(PDO::FETCH_ASSOC) ?: [], function (array $class) use ($pdo, $destinationLocationId): bool {
                    return $this->mappedLocation($pdo, (int) ($class['legacy_location_id'] ?? 0), (string) ($class['apelidolocal'] ?? ''), (string) ($class['nomelocal'] ?? '')) === $destinationLocationId;
                }));
            }
        }
        return [];
    }

    public function copyData(string $sourceSeason, int $sourceClassId, int $destinationSeasonId, int $destinationModalityId = 0, int $destinationLocationId = 0): array
    {
        if ($sourceClassId <= 0 || $destinationSeasonId <= 0) {
            throw new RuntimeException('Selecione a temporada de destino e a turma de origem.');
        }
        [$source, $sourceSeasonId] = $this->parseSourceSeason($sourceSeason);
        $record = $source === 'legacy'
            ? $this->legacyClass($sourceSeasonId, $sourceClassId, $destinationSeasonId)
            : $this->currentClass($sourceSeasonId, $sourceClassId, $destinationSeasonId);
        if ($destinationModalityId <= 0 || (int) ($record['modalidade_id'] ?? 0) !== $destinationModalityId) {
            throw new RuntimeException('A turma de origem não pertence à modalidade selecionada.');
        }
        if ($destinationLocationId <= 0 || (int) ($record['local_treino_id'] ?? 0) !== $destinationLocationId) {
            throw new RuntimeException('A turma de origem não pertence ao local selecionado.');
        }
        $warnings = $this->previousCopies($source, $sourceSeasonId, $sourceClassId);
        return ['record' => $record, 'previous_copies' => $warnings];
    }

    public function recordCopy(int $destinationClassId, int $destinationSeasonId, array $data, int $accountId): void
    {
        $source = trim((string) ($data['copia_origem_tipo'] ?? ''));
        $sourceSeasonId = (int) ($data['copia_origem_temporada_id'] ?? 0);
        $sourceClassId = (int) ($data['copia_origem_turma_id'] ?? 0);
        if ($destinationClassId <= 0 || $destinationSeasonId <= 0 || $sourceSeasonId <= 0 || $sourceClassId <= 0 || !in_array($source, ['legacy', 'current'], true)) return;
        $stmt = Database::connection()->prepare('INSERT INTO turmas_copias
            (origem_tipo, origem_temporada_id, origem_turma_id, destino_temporada_id, destino_turma_id, copiado_por_conta_id, copiado_em)
            VALUES (:source, :source_season, :source_class, :destination_season, :destination_class, :account_id, NOW())');
        $stmt->execute([':source' => $source, ':source_season' => $sourceSeasonId, ':source_class' => $sourceClassId,
            ':destination_season' => $destinationSeasonId, ':destination_class' => $destinationClassId, ':account_id' => $accountId]);
    }

    private function currentClass(int $sourceSeasonId, int $sourceClassId, int $destinationSeasonId): array
    {
        $stmt = Database::connection()->prepare('SELECT * FROM turmas WHERE id = :class_id AND temporada_id = :season_id LIMIT 1');
        $stmt->execute([':class_id' => $sourceClassId, ':season_id' => $sourceSeasonId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new RuntimeException('A turma selecionada não pertence à temporada de origem.');
        $record = $row;
        unset($record['id'], $record['professor_conta_id'], $record['status'], $record['ativo'], $record['created_at'], $record['updated_at']);
        $record['temporada_id'] = $destinationSeasonId;
        $record['cronograma_modalidade_id'] = $this->destinationSchedule($destinationSeasonId, (int) $row['modalidade_id']);
        $record['niveis_aceitos'] = json_decode((string) ($row['niveis_aceitos_json'] ?? '[]'), true) ?: [];
        $record['excecoes_idade'] = json_decode((string) ($row['excecoes_idade_json'] ?? '{}'), true) ?: [];
        $record['copia_origem_tipo'] = 'current';
        $record['copia_origem_temporada_id'] = $sourceSeasonId;
        $record['copia_origem_turma_id'] = $sourceClassId;
        $record['campos_obrigatorios_pendentes'] = $this->requiredMissing($record);
        return $record;
    }

    private function legacyClass(int $sourceSeasonId, int $sourceClassId, int $destinationSeasonId): array
    {
        $stmt = Database::connection()->prepare('SELECT dados_json FROM turmas_externas_migracao
            WHERE temporada_id_externa = :season_id AND turma_id_externa = :class_id LIMIT 1');
        $stmt->execute([':season_id' => $sourceSeasonId, ':class_id' => $sourceClassId]);
        $payload = $stmt->fetchColumn();
        $row = is_string($payload) ? json_decode($payload, true) : null;
        if (!$row) throw new RuntimeException('A turma não foi encontrada na temporada 2026 do site antigo.');

        $pdo = Database::connection();
        $modalityId = $this->findByNormalizedName($pdo, 'modalidades', 'nome', (string) ($row['descmodal'] ?? ''));
        $locationId = $this->mappedLocation($pdo, (int) ($row['legacy_location_id'] ?? 0), (string) ($row['apelidolocal'] ?? ''), (string) ($row['nomelocal'] ?? ''));
        $spaceId = $this->mappedSpace($pdo, $locationId, (string) ($row['nomeespaco'] ?? ''));
        $program = $this->program((string) ($row['prograativ'] ?? ''));
        $general = max(0, (int) ($row['vagas'] ?? 0));
        $pcd = max(0, (int) ($row['vagaspcd'] ?? 0));
        $plm = max(0, (int) ($row['vagaslaudo'] ?? 0));
        $pvs = max(0, (int) ($row['vagaspvs'] ?? 0));
        $record = [
            'temporada_id' => $destinationSeasonId, 'modalidade_id' => $modalityId,
            'cronograma_modalidade_id' => $modalityId > 0 ? $this->destinationSchedule($destinationSeasonId, $modalityId) : 0,
            'local_treino_id' => $locationId, 'espaco_treino_id' => $spaceId,
            'nome' => trim((string) ($row['descturma'] ?? '')), 'observacao' => trim((string) ($row['obs'] ?? '')),
            'programa' => $program, 'dias_semana' => $this->weekdays((string) ($row['diasemana'] ?? '')),
            'hora_inicio' => $this->time((string) ($row['horainicio'] ?? '')), 'hora_fim' => $this->time((string) ($row['horatermino'] ?? '')),
            'idade_minima' => max(0, (int) ($row['initidade'] ?? 0)), 'idade_maxima' => max(0, (int) ($row['fimidade'] ?? 120)),
            'criterio_faixa_etaria' => 'idade_exata', 'sexo' => $this->sex((string) ($row['geneativ'] ?? '')),
            'vagas_totais' => $general + $pcd + $plm + $pvs, 'vagas_geral' => $general,
            'vagas_pcd' => $pcd, 'vagas_plm' => $plm, 'vagas_pvs' => $pvs,
            'vagas_espera_geral' => 0, 'vagas_espera_pcd' => 0, 'vagas_espera_plm' => 0, 'vagas_espera_pvs' => 0,
            'inscricoes_abertas' => 0, 'copia_origem_tipo' => 'legacy',
            'copia_origem_temporada_id' => $sourceSeasonId, 'copia_origem_turma_id' => $sourceClassId,
        ];
        $record['campos_obrigatorios_pendentes'] = $this->requiredMissing($record);
        return $record;
    }

    private function previousCopies(string $source, int $sourceSeasonId, int $sourceClassId): array
    {
        $stmt = Database::connection()->prepare('SELECT tc.destino_turma_id AS turma_id, te.nome AS temporada_nome
            FROM turmas_copias tc INNER JOIN temporadas te ON te.id = tc.destino_temporada_id
            WHERE tc.origem_tipo = :source AND tc.origem_temporada_id = :season_id AND tc.origem_turma_id = :class_id
            ORDER BY tc.copiado_em DESC, tc.id DESC');
        $stmt->execute([':source' => $source, ':season_id' => $sourceSeasonId, ':class_id' => $sourceClassId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function destinationSchedule(int $seasonId, int $modalityId): int
    {
        if ($seasonId <= 0 || $modalityId <= 0) return 0;
        $stmt = Database::connection()->prepare('SELECT id FROM cronogramas_modalidade WHERE temporada_id = :season_id AND modalidade_id = :modality_id ORDER BY id');
        $stmt->execute([':season_id' => $seasonId, ':modality_id' => $modalityId]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return count($ids) === 1 ? (int) $ids[0] : 0;
    }

    private function requiredMissing(array $record): array
    {
        $labels = ['modalidade_id' => 'Modalidade', 'cronograma_modalidade_id' => 'Cronograma da modalidade', 'local_treino_id' => 'Local', 'espaco_treino_id' => 'Espaço', 'nome' => 'Nome da turma'];
        $missing = [];
        foreach ($labels as $field => $label) if (trim((string) ($record[$field] ?? '')) === '' || (is_numeric($record[$field] ?? null) && (int) $record[$field] <= 0)) $missing[] = ['campo' => $field, 'rotulo' => $label];
        return $missing;
    }

    private function mappedLocation(PDO $pdo, int $legacyId, string $nickname, string $name): int
    {
        try {
            $stmt = $pdo->prepare('SELECT local_treino_id FROM locais_externos_vinculos WHERE id_externo = :id LIMIT 1');
            $stmt->execute([':id' => $legacyId]);
            $mapped = (int) $stmt->fetchColumn();
            if ($mapped > 0) return $mapped;
        } catch (Throwable $e) {}
        foreach ($pdo->query('SELECT id, apelido_local, nome_local FROM locais_treino')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            if (in_array($this->normalize($nickname ?: $name), [$this->normalize((string) $row['apelido_local']), $this->normalize((string) $row['nome_local'])], true)) return (int) $row['id'];
        }
        return 0;
    }

    private function mappedSpace(PDO $pdo, int $locationId, string $name): int
    {
        if ($locationId <= 0 || trim($name) === '') return 0;
        $stmt = $pdo->prepare('SELECT id, nome FROM espacos_treino WHERE local_treino_id = :location_id');
        $stmt->execute([':location_id' => $locationId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) if ($this->normalize((string) $row['nome']) === $this->normalize($name)) return (int) $row['id'];
        return 0;
    }

    private function findByNormalizedName(PDO $pdo, string $table, string $column, string $name): int
    {
        foreach ($pdo->query("SELECT id, {$column} AS nome FROM {$table}")->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) if ($this->normalize((string) $row['nome']) === $this->normalize($name)) return (int) $row['id'];
        return 0;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        return preg_replace('/[^a-z0-9]+/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value) ?? '';
    }

    private function weekdays(string $value): string
    {
        $normalized = $this->normalize($value);
        $map = ['segunda' => 1, 'terca' => 2, 'quarta' => 3, 'quinta' => 4, 'sexta' => 5, 'sabado' => 6, 'domingo' => 7];
        $days = [];
        foreach ($map as $name => $number) if (str_contains($normalized, $name)) $days[] = $number;
        return implode(',', $days);
    }

    private function time(string $value): string { return preg_match('/^(\d{1,2}):(\d{2})/', trim($value), $m) ? str_pad($m[1], 2, '0', STR_PAD_LEFT) . ':' . $m[2] : ''; }
    private function sex(string $value): string { $value = $this->normalize($value); return str_contains($value, 'femin') ? 'feminino' : (str_contains($value, 'mascul') ? 'masculino' : ''); }
    private function program(string $value): string { $n = $this->normalize($value); foreach (['Corpo em Ação', 'Hora do Treino', 'Campeões da Vida', 'GR São Bernardo'] as $program) if ($this->normalize($program) === $n) return $program; return ''; }

    private function parseSourceSeason(string $value): array
    {
        if (!preg_match('/^(legacy|current):(\d+)$/', trim($value), $matches)) throw new RuntimeException('Selecione uma temporada de origem válida.');
        $id = (int) $matches[2];
        if ($matches[1] === 'legacy' && $id !== self::LEGACY_SEASON_ID) throw new RuntimeException('Somente a temporada 2026 do site antigo está disponível.');
        return [$matches[1], $id];
    }

    private function legacy(): PDO
    {
        if ($this->legacyConnection instanceof PDO) return $this->legacyConnection;
        $file = ROOT_PATH . '/config/external_database.local.php';
        if (!is_file($file)) throw new RuntimeException('A conexão com o site antigo ainda não foi configurada.');
        $config = require $file;
        try {
            $this->legacyConnection = new PDO('mysql:host=' . $config['host'] . ';port=' . ($config['port'] ?? 3306) . ';dbname=' . $config['dbname'] . ';charset=' . ($config['charset'] ?? 'utf8mb4'), $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 8]);
        } catch (Throwable $e) { throw new RuntimeException('Não foi possível consultar as turmas do site antigo.', 0, $e); }
        return $this->legacyConnection;
    }

}
