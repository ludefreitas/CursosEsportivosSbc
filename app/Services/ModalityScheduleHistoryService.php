<?php
namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

class ModalityScheduleHistoryService
{
    public static function attach(array $schedules): array
    {
        if (!$schedules) return [];
        $ids = array_map('intval', array_column($schedules, 'id'));
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::connection()->prepare("SELECT l.id,l.entidade_id,l.conta_id,l.tipo_evento,l.created_at,p.nome_completo FROM logs_auditoria l LEFT JOIN contas c ON c.id=l.conta_id LEFT JOIN pessoas p ON p.cpf=c.cpf WHERE l.tipo_entidade='cronogramas_modalidade' AND l.tipo_evento IN ('cronograma_modalidade.criado','cronograma_modalidade.atualizado') AND l.entidade_id IN ($marks) ORDER BY l.id DESC");
        $stmt->execute($ids);
        $history = []; $authors = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $log) {
            $id = (int)$log['entidade_id'];
            $item = ['id'=>(int)$log['id'], 'nome'=>$log['nome_completo'] ?: 'Usuário não identificado', 'data'=>$log['created_at']];
            if ($log['tipo_evento'] === 'cronograma_modalidade.criado') $history[$id]['criacao'] = $item;
            elseif (count($authors[$id] ?? []) < 2 && !isset($authors[$id][(int)$log['conta_id']])) {
                $history[$id]['alteracoes'][] = $item;
                $authors[$id][(int)$log['conta_id']] = true;
            }
        }
        foreach ($schedules as &$schedule) $schedule['historico'] = $history[(int)$schedule['id']] ?? [];
        unset($schedule);
        return $schedules;
    }

    public static function details(int $id): array
    {
        $stmt = Database::connection()->prepare("SELECT l.*,p.nome_completo,cm.nome AS cronograma_nome FROM logs_auditoria l INNER JOIN cronogramas_modalidade cm ON cm.id=l.entidade_id LEFT JOIN contas c ON c.id=l.conta_id LEFT JOIN pessoas p ON p.cpf=c.cpf WHERE l.id=? AND l.tipo_entidade='cronogramas_modalidade' AND l.tipo_evento IN ('cronograma_modalidade.criado','cronograma_modalidade.atualizado')");
        $stmt->execute([$id]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$log) throw new RuntimeException('Registro do cronograma não encontrado.');
        $payload = json_decode((string)$log['payload_json'], true) ?: [];
        $labels = ['nome'=>'Nome', 'temporada_id'=>'ID da temporada', 'modalidade_id'=>'ID da modalidade', 'observacao'=>'Observação', 'data_inicio'=>'Início da publicação', 'data_fim'=>'Fim da publicação', 'inscricoes_inicio'=>'Início das inscrições', 'inscricoes_fim'=>'Fim das inscrições', 'matriculas_inicio'=>'Início das matrículas', 'matriculas_fim'=>'Fim das matrículas', 'inscricoes_abertas_inicio'=>'Início das inscrições abertas', 'inscricoes_abertas_fim'=>'Fim das inscrições abertas', 'aulas_inicio'=>'Início das aulas', 'aulas_fim'=>'Fim das aulas', 'abrangencia_semanal'=>'Abrangência semanal', 'permitir_inscricao_periodo_matricula'=>'Inscrições durante a matrícula', 'possui_edital'=>'Possui edital', 'numero_edital'=>'Número do edital', 'link_edital'=>'Link do edital', 'permitir_multiplas_inscricoes_modalidade'=>'Múltiplas inscrições na modalidade', 'limite_inscricoes_modalidade'=>'Limite de inscrições na modalidade', 'data_liberacao_multiplas_inscricoes_modalidade'=>'Liberação de inscrições adicionais'];
        $changes = [];
        foreach ($labels as $key=>$label) {
            if (!array_key_exists($key, $payload['depois'] ?? [])) continue;
            $before = $payload['antes'][$key] ?? null; $after = $payload['depois'][$key];
            if ((string)$before !== (string)$after) $changes[] = ['campo'=>$label,'antes'=>self::format($key,$before),'depois'=>self::format($key,$after)];
        }
        return ['cronograma'=>$log['cronograma_nome'], 'usuario'=>$log['nome_completo'] ?: 'Usuário não identificado', 'data'=>date('d/m/Y H:i:s', strtotime($log['created_at'])), 'acao'=>$log['tipo_evento']==='cronograma_modalidade.criado'?'Criação':'Alteração', 'detalhes_disponiveis'=>isset($payload['depois']), 'alteracoes'=>$changes];
    }

    private static function format(string $key, mixed $value): string
    {
        if ($value === null || $value === '') return 'Não informado';
        if (in_array($key,['possui_edital','permitir_inscricao_periodo_matricula','permitir_multiplas_inscricoes_modalidade'],true)) return (int)$value ? 'Sim' : 'Não';
        if ($key === 'abrangencia_semanal') return $value==='segunda_domingo' ? 'Segunda-feira a domingo' : 'Segunda a sexta-feira';
        if (preg_match('/^(data_|inscricoes_|matriculas_|aulas_)/',$key) && preg_match('/^\d{4}-\d{2}-\d{2}/',(string)$value)) return date(strlen((string)$value)>10?'d/m/Y H:i':'d/m/Y',strtotime((string)$value));
        return (string)$value;
    }
}
