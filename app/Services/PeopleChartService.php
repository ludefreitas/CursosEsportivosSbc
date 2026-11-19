<?php

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PDOException;
use RuntimeException;

final class PeopleChartService
{
    private array $unavailable = [];

    public function __construct(private ?PDO $pdo = null) {}

    private function db(): PDO
    {
        return $this->pdo ?? Database::connection();
    }

    public function options(): array
    {
        return [
            'cities' => $this->db()->query("SELECT DISTINCT cidade AS nome FROM pessoas WHERE cidade IS NOT NULL AND TRIM(cidade)<>'' ORDER BY cidade")->fetchAll(PDO::FETCH_COLUMN),
            'neighborhoods' => $this->db()->query("SELECT DISTINCT bairro AS nome FROM pessoas WHERE bairro IS NOT NULL AND TRIM(bairro)<>'' ORDER BY bairro")->fetchAll(PDO::FETCH_COLUMN),
        ];
    }

    public function report(array $input): array
    {
        $this->unavailable = [];
        $today = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));
        $filters = $this->filters($input);
        $where = [];
        $params = [];
        foreach (['from' => '>=', 'to' => '<'] as $field => $operator) {
            if ($filters[$field] === '') continue;
            $where[] = "p.created_at {$operator} ?";
            $day = new DateTimeImmutable($filters[$field]);
            $params[] = ($field === 'to' ? $day->modify('+1 day') : $day)->format('Y-m-d') . ' 00:00:00';
        }
        foreach (['city' => 'cidade', 'neighborhood' => 'bairro', 'sex' => 'sexo', 'complete' => 'cadastro_completo'] as $field => $column) {
            if ($filters[$field] === '') continue;
            $where[] = "p.{$column}=?";
            $params[] = $filters[$field];
        }
        if ($filters['condition'] !== '') {
            $where[] = 'p.eh_' . $filters['condition'] . '=1';
        }
        if ($filters['account'] !== '') $where[] = 'c.id IS ' . ($filters['account'] === 'with' ? 'NOT ' : '') . 'NULL';
        $sql = 'SELECT p.*,c.id AS conta_id,c.ativo AS conta_ativa,c.ultimo_acesso_em FROM pessoas p LEFT JOIN contas c ON c.cpf=p.cpf';
        if ($where !== []) $sql .= ' WHERE ' . implode(' AND ', $where);
        $stmt = $this->db()->prepare($sql . ' ORDER BY p.id');
        $stmt->execute($params);
        $people = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $person) {
            $age = $this->age($person['data_nascimento'], $today);
            if ($filters['age_min'] !== '' && ($age === null || $age < (int) $filters['age_min'])) continue;
            if ($filters['age_max'] !== '' && ($age === null || $age > (int) $filters['age_max'])) continue;
            $person['age'] = $age;
            $people[(int) $person['id']] = $person;
        }
        $ids = array_keys($people);
        $related = [
            'links' => $this->rows($ids, "SELECT dependente_pessoa_id,responsavel_pessoa_id FROM vinculos_responsaveis WHERE data_fim IS NULL AND (dependente_pessoa_id IN ({{ids}}) OR responsavel_pessoa_id IN ({{ids}}))", 'Vínculos familiares'),
            'roles' => $this->rows($ids, 'SELECT p.id AS pessoa_id,pa.nome FROM pessoas p INNER JOIN contas c ON c.cpf=p.cpf INNER JOIN conta_papeis cp ON cp.conta_id=c.id INNER JOIN papeis pa ON pa.id=cp.papel_id WHERE p.id IN ({{ids}})', 'Papéis das contas'),
            'certificates' => $this->rows($ids, "SELECT cp.pessoa_id,tc.slug,cp.status,cp.validade_certificado,cp.tipos_deficiencia_pcd,cp.beneficio_social,EXISTS(SELECT 1 FROM documentos_certificados dc WHERE dc.certificado_pessoa_id=cp.id) AS has_pdf FROM certificados_pessoa cp INNER JOIN tipos_certificados tc ON tc.id=cp.tipo_certificado_id WHERE cp.pessoa_id IN ({{ids}}) AND tc.slug IN ('pcd','plm','pvs') ORDER BY COALESCE(cp.updated_at,cp.created_at) DESC,cp.id DESC", 'Certificados das condições'),
            'health' => $this->rows($ids, 'SELECT * FROM atestados_saude WHERE pessoa_id IN ({{ids}}) ORDER BY id DESC', 'Atestados internos'),
            'imported' => $this->rows($ids, "SELECT p.id AS pessoa_id,a.tipo_atestado,a.validade_certificado,a.data_emissao FROM atestados_saude_importados a INNER JOIN pessoas p ON p.cpf=a.cpf WHERE p.id IN ({{ids}}) AND a.status_importacao='ativo'", 'Atestados importados'),
            'parq' => $this->rows($ids, 'SELECT pessoa_id,tem_resposta_positiva,vence_em,renovar_em FROM questionarios_par_q WHERE pessoa_id IN ({{ids}})', 'PAR-Q'),
            'enrollments' => $this->rows($ids, 'SELECT pessoa_id,status,publico_alvo,tipo_inscricao,COUNT(*) AS total FROM inscricoes_turma WHERE pessoa_id IN ({{ids}}) GROUP BY pessoa_id,status,publico_alvo,tipo_inscricao', 'Inscrições em turmas'),
            'bookings' => $this->rows($ids, 'SELECT pessoa_id,status,COUNT(*) AS total FROM agendamentos WHERE pessoa_id IN ({{ids}}) GROUP BY pessoa_id,status', 'Agendamentos semanais'),
            'special' => $this->rows($ids, 'SELECT pessoa_id,status,COUNT(*) AS total FROM agenda_horario_especial_inscricoes WHERE pessoa_id IN ({{ids}}) GROUP BY pessoa_id,status', 'Agendamentos especiais'),
            'attendance' => $this->rows($ids, 'SELECT i.pessoa_id,ch.status,COUNT(*) AS total FROM turmas_chamadas ch INNER JOIN inscricoes_turma i ON i.id=ch.inscricao_turma_id WHERE i.pessoa_id IN ({{ids}}) GROUP BY i.pessoa_id,ch.status', 'Frequência em turmas'),
            'evaluations' => $this->rows($ids, 'SELECT pessoa_id,situacao FROM avaliacoes_fisicas WHERE pessoa_id IN ({{ids}}) ORDER BY data_avaliacao DESC,id DESC', 'Avaliações físicas'),
            'levels' => $this->rows($ids, "SELECT cn.pessoa_id,n.nome FROM certificados_nivel_modalidade cn INNER JOIN niveis_modalidade n ON n.id=cn.nivel_modalidade_id WHERE cn.pessoa_id IN ({{ids}}) AND cn.status='ativo'", 'Níveis de modalidade'),
        ];
        return $this->compile($people, $related, $filters, $today);
    }

    private function rows(array $ids, string $sql, string $label): array
    {
        if ($ids === []) return [];
        $params = [];
        for ($index = 0; $index < substr_count($sql, '{{ids}}'); $index++) $params = array_merge($params, $ids);
        $sql = str_replace('{{ids}}', implode(',', array_fill(0, count($ids), '?')), $sql);
        try {
            $stmt = $this->db()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $error) {
            if ($error->getCode() !== '42S02' && !str_contains($error->getMessage(), 'no such table:')) throw $error;
            $this->unavailable[] = $label;
            return [];
        }
    }

    private function filters(array $input): array
    {
        $filters = [];
        foreach (['from','to','age_min','age_max','city','neighborhood','sex','complete','condition','account'] as $key) {
            if (isset($input[$key]) && !is_scalar($input[$key])) throw new RuntimeException('Filtro inválido.');
            $filters[$key] = trim((string) ($input[$key] ?? ''));
        }
        foreach (['from','to'] as $key) {
            if ($filters[$key] === '') continue;
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key]);
            if (!$date || $date->format('Y-m-d') !== $filters[$key]) throw new RuntimeException('Informe datas de cadastro válidas.');
        }
        if ($filters['from'] !== '' && $filters['to'] !== '' && $filters['from'] > $filters['to']) throw new RuntimeException('A data final deve ser igual ou posterior à inicial.');
        foreach (['age_min','age_max'] as $key) {
            if ($filters[$key] !== '' && (!ctype_digit($filters[$key]) || (int) $filters[$key] > 130)) throw new RuntimeException('Informe idades entre 0 e 130 anos.');
        }
        if ($filters['age_min'] !== '' && $filters['age_max'] !== '' && (int) $filters['age_min'] > (int) $filters['age_max']) throw new RuntimeException('A idade máxima deve ser igual ou superior à mínima.');
        foreach (['sex'=>['masculino','feminino','Sexo não declarado'],'complete'=>['0','1'],'condition'=>['pcd','plm','pvs'],'account'=>['with','without']] as $key => $allowed) {
            if ($filters[$key] !== '' && !in_array($filters[$key], $allowed, true)) throw new RuntimeException('Selecione uma opção válida nos filtros.');
        }
        if (mb_strlen($filters['city']) > 120 || mb_strlen($filters['neighborhood']) > 120) throw new RuntimeException('Localização inválida.');
        return $filters;
    }

    private function age(?string $birth, DateTimeImmutable $today): ?int
    {
        $value = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $birth, $today->getTimezone());
        if (!$value || $value->format('Y-m-d') !== $birth || $value > $today) return null;
        $age = $value->diff($today)->y;
        return $age <= 130 ? $age : null;
    }

    private function add(array &$counts, string $label, int $amount = 1): void
    {
        $label = trim($label) ?: 'Não informado';
        $counts[$label] = ($counts[$label] ?? 0) + $amount;
    }

    private function ageBand(?int $age): string
    {
        if ($age === null) return 'Idade não informada ou inválida';
        foreach ([5=>'0 a 5 anos',11=>'6 a 11 anos',17=>'12 a 17 anos',29=>'18 a 29 anos',44=>'30 a 44 anos',59=>'45 a 59 anos',74=>'60 a 74 anos',130=>'75 anos ou mais'] as $maximum => $label) {
            if ($age <= $maximum) return $label;
        }
        return 'Idade não informada ou inválida';
    }

    private function compile(array $people, array $related, array $filters, DateTimeImmutable $today): array
    {
        $total = count($people);
        $counts = [];
        foreach (['overview','sex','ages','age_sex','cities','neighborhoods','states','conditions','combinations','completeness','contact','accounts','access','roles','family','registrations','clinical','dermatological','parq','participation','enrollments','publics','origins','bookings','special','attendance','evaluations','levels','quality','disabilities','benefits'] as $key) $counts[$key] = [];
        $certificates = [];
        foreach ($related['certificates'] as $row) $certificates[$row['pessoa_id']][$row['slug']] ??= $row;
        $health = [];
        foreach ($related['health'] as $row) {
            $health[$row['pessoa_id']][$row['tipo_atestado']]['internal'] ??= $row;
        }
        foreach ($related['imported'] as $row) {
            $current = $health[$row['pessoa_id']][$row['tipo_atestado']]['imported'] ?? null;
            if (!$current || (string) $row['validade_certificado'] > (string) $current['validade_certificado']) $health[$row['pessoa_id']][$row['tipo_atestado']]['imported'] = $row;
        }
        foreach (['clinical','dermatological'] as $kind) {
            $counts[$kind . '_origin'] = [];
            $counts[$kind . '_expiry'] = [];
        }
        $parq = array_column($related['parq'], null, 'pessoa_id');
        $dependents = [];
        $responsible = [];
        foreach ($related['links'] as $link) {
            $dependents[$link['dependente_pessoa_id']] = true;
            $responsible[$link['responsavel_pessoa_id']][$link['dependente_pessoa_id']] = true;
        }
        $participants = [];
        $active = [];
        foreach ($related['enrollments'] as $row) {
            $participants[$row['pessoa_id']] = true;
            if ($row['status'] === 'matriculada') $active[$row['pessoa_id']] = true;
            $this->add($counts['enrollments'], AdminChartService::STATUSES[$row['status']] ?? $row['status'], (int) $row['total']);
            $this->add($counts['publics'], strtoupper($row['publico_alvo']), (int) $row['total']);
            $this->add($counts['origins'], CourseEnrollmentService::ENROLLMENT_TYPE_LABELS[$row['tipo_inscricao']] ?? 'Não identificada', (int) $row['total']);
        }
        foreach ($related['bookings'] as $row) $participants[$row['pessoa_id']] = true;
        foreach ($related['special'] as $row) $participants[$row['pessoa_id']] = true;
        $latestEvaluations = [];
        foreach ($related['evaluations'] as $row) $latestEvaluations[$row['pessoa_id']] ??= $row['situacao'];
        foreach ($related['roles'] as $row) $this->add($counts['roles'], $row['nome']);
        foreach ($related['levels'] as $row) $this->add($counts['levels'], $row['nome']);
        $statusLabels = ['agendado'=>'Agendado','cancelado'=>'Cancelado','presente'=>'Presente','falta'=>'Falta','justificado'=>'Justificado','inscrito'=>'Inscrito','ausente'=>'Ausente'];
        foreach (['bookings','special','attendance'] as $kind) {
            foreach ($related[$kind] as $row) $this->add($counts[$kind], $statusLabels[$row['status']] ?? $row['status'], (int) $row['total']);
        }
        $certificateCounts = ['pcd'=>[], 'plm'=>[], 'pvs'=>[]];
        foreach ($people as $id => $person) {
            $age = $person['age'];
            $sex = ['masculino'=>'Masculino','feminino'=>'Feminino','Sexo não declarado'=>'Sexo não declarado'][$person['sexo'] ?? ''] ?? 'Não informado';
            $band = $this->ageBand($age);
            $this->add($counts['sex'], $sex);
            $this->add($counts['ages'], $band);
            $this->add($counts['age_sex'], $band . ' · ' . $sex);
            foreach (['cities'=>'cidade','neighborhoods'=>'bairro','states'=>'uf'] as $key => $field) $this->add($counts[$key], (string) ($person[$field] ?? ''));
            $combination = [];
            foreach (['pcd','plm','pvs'] as $condition) {
                if (empty($person['eh_' . $condition])) continue;
                $combination[] = strtoupper($condition);
                $this->add($counts['conditions'], strtoupper($condition));
                $certificate = $certificates[$id][$condition] ?? null;
                $state = $certificate === null || empty($certificate['has_pdf']) ? 'Sem documentação' : $this->certificateState($certificate['status'], $certificate['validade_certificado'], $today, true);
                $this->add($certificateCounts[$condition], $state);
                if ($condition === 'pcd' && $certificate) {
                    $types = json_decode((string) $certificate['tipos_deficiencia_pcd'], true);
                    $typeLabels = ['auditiva'=>'Auditiva','visual'=>'Visual','intelectual'=>'Intelectual','fisica'=>'Física','autismo'=>'Autismo','tea'=>'TEA (Transtorno do Espectro Autista)'];
                    if (is_array($types)) foreach (array_unique(array_filter($types, 'is_string')) as $type) $this->add($counts['disabilities'], $typeLabels[$type] ?? $type);
                }
                if ($condition === 'pvs' && $certificate && trim((string) $certificate['beneficio_social']) !== '') $this->add($counts['benefits'], (string) $certificate['beneficio_social']);
            }
            $this->add($counts['combinations'], $combination ? implode(' + ', $combination) : 'Sem condição declarada');
            $this->add($counts['completeness'], !empty($person['cadastro_completo']) ? 'Cadastro completo' : 'Cadastro incompleto');
            foreach (['WhatsApp'=>'telefone_whatsapp','E-mail'=>'email','Cartão SUS'=>'numero_cartao_sus','CEP'=>'cep','Logradouro'=>'logradouro','Número do endereço'=>'numero_endereco','Bairro'=>'bairro','Cidade'=>'cidade','UF'=>'uf','Contato de emergência'=>'contato_emergencia_nome','Telefone de emergência'=>'contato_emergencia_telefone'] as $label => $field) {
                if (trim((string) ($person[$field] ?? '')) !== '') $this->add($counts['contact'], $label . ' informado');
            }
            if ($age === null) $this->add($counts['quality'], 'Data de nascimento ausente ou inválida');
            if (!validar_cpf((string) $person['cpf'])) $this->add($counts['quality'], 'CPF inválido');
            if (trim((string) ($person['email'] ?? '')) !== '' && !filter_var($person['email'], FILTER_VALIDATE_EMAIL)) $this->add($counts['quality'], 'E-mail com formato inválido');
            $phone = preg_replace('/\D+/', '', (string) ($person['telefone_whatsapp'] ?? ''));
            if ($phone === '') $this->add($counts['quality'], 'WhatsApp não informado');
            elseif (!in_array(strlen($phone), [10,11,12,13], true)) $this->add($counts['quality'], 'WhatsApp com tamanho inválido');
            $this->add($counts['accounts'], empty($person['conta_id']) ? 'Sem conta própria' : (!empty($person['conta_ativa']) ? 'Conta ativa' : 'Conta inativa'));
            if (!empty($person['conta_id'])) {
                $last = (string) ($person['ultimo_acesso_em'] ?? '');
                $this->add($counts['access'], $last === '' ? 'Sem acesso registrado' : ($last >= $today->modify('-30 days')->format('Y-m-d') ? 'Acesso nos últimos 30 dias' : 'Último acesso há mais de 30 dias'));
            }
            $this->add($counts['family'], isset($dependents[$id]) ? 'Dependente com responsável vinculado' : 'Sem vínculo de dependência');
            if (isset($responsible[$id])) $this->add($counts['overview'], 'Responsáveis com dependentes');
            if ($age !== null && $age < 18 && !isset($dependents[$id])) $this->add($counts['quality'], 'Menor sem vínculo ativo de responsável');
            $created = substr((string) $person['created_at'], 0, 7);
            if (preg_match('/^\d{4}-\d{2}$/', $created)) $this->add($counts['registrations'], $created);
            foreach (['clinical'=>'clinico','dermatological'=>'dermatologico'] as $key => $type) {
                $sources = $health[$id][$type] ?? [];
                $internal = $sources['internal'] ?? null;
                $hasImported = isset($sources['imported']);
                $this->add($counts[$key . '_origin'], $internal
                    ? ($hasImported ? 'Enviado pelo sistema e importado ativo' : 'Enviado pelo sistema')
                    : ($hasImported ? 'Somente importado ativo' : 'Sem atestado'));
                $validities = [];
                if ($internal && $internal['status_validacao'] === 'validado' && $internal['validade_certificado']) $validities[] = $internal['validade_certificado'];
                if (!empty($sources['imported']['validade_certificado'])) $validities[] = $sources['imported']['validade_certificado'];
                if ($validities) {
                    rsort($validities);
                    $state = $this->certificateState('validado', $validities[0], $today);
                    $expiry = new DateTimeImmutable($validities[0], $today->getTimezone());
                    $days = (int) $today->diff($expiry)->format('%r%a');
                    $expiryLabel = $days < 0 ? 'Vencido' : ($days <= 30 ? 'Vence em até 30 dias' : ($days <= 60 ? 'Vence em 31 a 60 dias' : ($days <= 90 ? 'Vence em 61 a 90 dias' : 'Vence após 90 dias')));
                } else {
                    $state = $internal ? ($internal['status_validacao'] === 'validado' ? 'Validado sem validade informada' : $this->certificateState($internal['status_validacao'], null, $today)) : (isset($sources['imported']) ? 'Validado sem validade informada' : 'Sem atestado');
                    $expiryLabel = $state === 'Validado sem validade informada' ? 'Validado sem validade informada' : 'Sem atestado validado';
                }
                $this->add($counts[$key], $state);
                $this->add($counts[$key . '_expiry'], $expiryLabel);
            }
            $q = $parq[$id] ?? null;
            $parqStatus = !$q ? 'Não respondido' : ($q['vence_em'] < $today->format('Y-m-d') ? 'Vencido' : ($q['renovar_em'] <= $today->format('Y-m-d') ? 'Em período de renovação' : 'Vigente'));
            $this->add($counts['parq'], $parqStatus);
            if ($q && !empty($q['tem_resposta_positiva'])) $this->add($counts['overview'], 'PAR-Q com resposta positiva');
            $this->add($counts['participation'], isset($participants[$id]) ? 'Com histórico de inscrição ou agendamento' : 'Sem histórico de inscrição ou agendamento');
            $this->add($counts['evaluations'], ['apto'=>'Apto','nao_apto'=>'Não apto','pendente'=>'Pendente'][$latestEvaluations[$id] ?? ''] ?? 'Sem avaliação');
        }
        ksort($counts['registrations']);
        $orderedAges = [];
        foreach ([0,6,12,18,30,45,60,75,null] as $age) {
            $label = $this->ageBand($age);
            if (isset($counts['ages'][$label])) $orderedAges[$label] = $counts['ages'][$label];
        }
        $counts['ages'] = $orderedAges;
        $counts['overview'] = ['Pessoas cadastradas'=>$total,'Cadastros completos'=>$counts['completeness']['Cadastro completo'] ?? 0,'Com conta própria'=>$total - ($counts['accounts']['Sem conta própria'] ?? 0),'Com matrícula ativa'=>count($active),'Com histórico de atividades'=>count($participants)] + $counts['overview'];
        $slides = [];
        $definitions = [
            'overview'=>['Panorama das pessoas','Indicadores do conjunto de pessoas selecionado.'],
            'registrations'=>['Evolução dos cadastros','Quantidade de pessoas cadastradas em cada mês.'],
            'ages'=>['Faixas etárias','Idade calculada na data de referência do relatório.'],
            'sex'=>['Sexo declarado','Distribuição conforme o cadastro.'],
            'age_sex'=>['Idade e sexo','Cruzamento das faixas etárias com o sexo declarado.'],
            'cities'=>['Pessoas por cidade','Cidade de residência informada no cadastro.'],
            'neighborhoods'=>['Pessoas por bairro','Bairro de residência informado no cadastro.'],
            'states'=>['Pessoas por UF','Unidade federativa informada no cadastro.'],
            'conditions'=>['Condições declaradas','Uma pessoa pode declarar mais de uma condição.'],
            'combinations'=>['Combinações de condições','Cada pessoa aparece em uma única combinação.'],
            'disabilities'=>['Tipos de deficiência declarados','Tipos registrados no certificado PCD mais recente; uma pessoa pode aparecer em mais de um tipo.'],
            'benefits'=>['Benefícios sociais informados','Benefício registrado no certificado PVS mais recente.'],
            'completeness'=>['Situação dos cadastros','Conclusão do cadastro conforme o sistema.'],
            'contact'=>['Informações preenchidas','Cada indicador conta pessoas com o respectivo campo informado.'],
            'quality'=>['Pendências nos dados','Uma pessoa pode apresentar mais de uma pendência.'],
            'family'=>['Vínculos com responsáveis','Vínculos de dependência ativos no sistema.'],
            'accounts'=>['Contas de acesso','Situação da conta própria da pessoa.'],
            'access'=>['Acesso às contas','Pessoas com conta própria, agrupadas pelo último acesso registrado.'],
            'roles'=>['Papéis dos usuários','Uma conta pode possuir mais de um papel.'],
            'clinical'=>['Atestado clínico','Considera atestados internos validados e importados ativos; prevalece a maior validade.'],
            'dermatological'=>['Atestado dermatológico','Considera atestados internos validados e importados ativos; prevalece a maior validade.'],
            'clinical_origin'=>['Origem dos atestados clínicos','Distingue documentos enviados pelo sistema e registros importados ativos. Importações substituídas são desconsideradas.'],
            'clinical_expiry'=>['Vencimento dos atestados clínicos','Usa a maior validade entre o documento interno validado e os importados ativos. O dia do vencimento está incluído na vigência.'],
            'dermatological_origin'=>['Origem dos atestados dermatológicos','Distingue documentos enviados pelo sistema e registros importados ativos. Importações substituídas são desconsideradas.'],
            'dermatological_expiry'=>['Vencimento dos atestados dermatológicos','Usa a maior validade entre o documento interno validado e os importados ativos. O dia do vencimento está incluído na vigência.'],
            'parq'=>['Situação do PAR-Q','Vigência e período de renovação dos questionários.'],
            'participation'=>['Participação nas atividades','Histórico de inscrições em turmas ou agendamentos.'],
            'enrollments'=>['Situação das inscrições em turmas','Conta inscrições; uma pessoa pode ter várias.'],
            'publics'=>['Categorias das inscrições','Categoria efetivamente ocupada por cada inscrição.'],
            'origins'=>['Origem das inscrições','Tipo de inscrição registrado pelo sistema.'],
            'bookings'=>['Agendamentos semanais','Conta agendamentos de todo o histórico das pessoas selecionadas.'],
            'special'=>['Inscrições em horários especiais','Conta inscrições de todo o histórico das pessoas selecionadas.'],
            'attendance'=>['Frequência nas turmas','Conta registros de chamada das pessoas selecionadas.'],
            'evaluations'=>['Avaliação física mais recente','Uma avaliação por pessoa, considerando a data mais recente.'],
            'levels'=>['Níveis de modalidade ativos','Uma pessoa pode ter certificados em diferentes modalidades.'],
        ];
        foreach ($definitions as $key => [$title, $description]) {
            $records = in_array($key, ['enrollments','publics','origins','bookings','special','attendance','levels'], true);
            $base = $records ? array_sum($counts[$key]) : $total;
            if ($key === 'access' || $key === 'roles') $base = $total - ($counts['accounts']['Sem conta própria'] ?? 0);
            $slides[] = $this->slide($key, $title, $description, $counts[$key], $base, $records ? 'registros' : 'pessoas', in_array($key, ['registrations','ages'], true));
        }
        foreach ($certificateCounts as $condition => $values) {
            $slides[] = $this->slide('certificate_' . $condition, 'Documentação ' . strtoupper($condition), 'Situação do certificado mais recente das pessoas que declararam esta condição.', $values, array_sum($values), 'pessoas');
        }
        $slides = array_merge($slides, $this->healthDetailSlides($health));
        $filterLabels = [];
        foreach (['from'=>'Cadastro a partir de','to'=>'Cadastro até','age_min'=>'Idade mínima','age_max'=>'Idade máxima','city'=>'Cidade','neighborhood'=>'Bairro','sex'=>'Sexo','complete'=>'Cadastro completo','condition'=>'Condição','account'=>'Conta'] as $key => $label) {
            if ($filters[$key] === '') continue;
            $value = $filters[$key];
            if (in_array($key, ['from','to'], true)) $value = (new DateTimeImmutable($value))->format('d/m/Y');
            if ($key === 'complete') $value = $value === '1' ? 'Sim' : 'Não';
            if ($key === 'account') $value = $value === 'with' ? 'Com conta própria' : 'Sem conta própria';
            if ($key === 'condition') $value = strtoupper($value);
            if ($key === 'sex') $value = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
            $filterLabels[] = $label . ' — ' . $value;
        }
        return [
            'summary'=>['people'=>$total,'complete'=>$counts['completeness']['Cadastro completo'] ?? 0,'accounts'=>$total - ($counts['accounts']['Sem conta própria'] ?? 0),'active'=>count($active),'participants'=>count($participants)],
            'slides'=>$slides,
            'reference_date'=>$today->format('d/m/Y'),
            'generated_at'=>(new DateTimeImmutable('now', $today->getTimezone()))->format('d/m/Y H:i:s'),
            'filters_text'=>$filterLabels ? implode(' · ', $filterLabels) : 'Todas as pessoas cadastradas',
            'unavailable'=>$this->unavailable,
        ];
    }

    private function healthDetailSlides(array $health): array
    {
        $slides = [];
        $locations = ['servico_publico'=>'Serviço público','clinica_particular'=>'Clínica particular','clinica_convenio'=>'Clínica de convênio'];
        foreach (['clinico'=>'clínicos','dermatologico'=>'dermatológicos'] as $type => $name) {
            $counts = array_fill_keys(['location','term','issued','review','delay','fields','imported_issued'], []);
            $counts['location'] = array_fill_keys(array_values($locations), 0);
            $counts['location']['Local não informado'] = 0;
            foreach (['CRM do médico','Local do atendimento','Emissão declarada','Emissão validada','Prazo em meses','Data de vencimento','Data da análise','Observação do envio','Observação da análise'] as $field) $counts['fields'][$field . ' informado'] = 0;
            $internalTotal = 0;
            $importedTotal = 0;
            foreach ($health as $sources) {
                $internal = $sources[$type]['internal'] ?? null;
                $imported = $sources[$type]['imported'] ?? null;
                if ($imported) {
                    $importedTotal++;
                    $month = substr((string) ($imported['data_emissao'] ?? ''), 0, 7);
                    $this->add($counts['imported_issued'], preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : 'Emissão não informada');
                }
                if (!$internal) continue;
                $internalTotal++;
                $this->add($counts['location'], $locations[$internal['local_atendimento'] ?? ''] ?? 'Local não informado');
                $months = (int) ($internal['validade_meses'] ?? 0);
                $this->add($counts['term'], $months > 0 ? $months . ($months === 1 ? ' mês' : ' meses') : 'Prazo não informado');
                $issue = $internal['data_emissao_validada'] ?? $internal['data_emissao'] ?? '';
                $month = substr((string) $issue, 0, 7);
                $this->add($counts['issued'], preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : 'Emissão não informada');
                $this->add($counts['review'], ['validado'=>'Validado','reprovado'=>'Reprovado','pendente'=>'Pendente'][$internal['status_validacao']] ?? 'Não informado');
                $created = $internal['created_at'] ?? '';
                $validated = $internal['validado_em'] ?? '';
                $delay = 'Sem data de análise registrada';
                if ($created && $validated) {
                    try {
                        $days = (int) (new DateTimeImmutable($created))->diff(new DateTimeImmutable($validated))->format('%r%a');
                        $delay = $days < 0 ? 'Datas de análise inconsistentes' : ($days === 0 ? 'No mesmo dia' : ($days <= 7 ? 'Em 1 a 7 dias' : ($days <= 30 ? 'Em 8 a 30 dias' : 'Após 30 dias')));
                    } catch (\Exception) { $delay = 'Datas de análise inconsistentes'; }
                }
                $this->add($counts['delay'], $delay);
                foreach (['CRM do médico'=>'crm_medico','Local do atendimento'=>'local_atendimento','Emissão declarada'=>'data_emissao','Emissão validada'=>'data_emissao_validada','Prazo em meses'=>'validade_meses','Data de vencimento'=>'validade_certificado','Data da análise'=>'validado_em','Observação do envio'=>'observacoes','Observação da análise'=>'observacao_validacao'] as $label => $field) {
                    if (trim((string) ($internal[$field] ?? '')) !== '') $this->add($counts['fields'], $label . ' informado');
                }
            }
            $definitions = [
                'location'=>['Local do atendimento','Serviço público, clínica particular ou clínica de convênio. Importações não possuem esse campo.'],
                'term'=>['Prazo de validade definido','Prazo em meses registrado durante a validação, separado da data de vencimento.'],
                'issued'=>['Mês de emissão','Prioriza a emissão validada e utiliza a declarada quando não há validação.'],
                'review'=>['Análise dos documentos enviados','Status do documento interno, mesmo quando existe importação ativa com validade maior.'],
                'delay'=>['Tempo até a análise','Dias corridos entre o envio e a data de análise registrada.'],
                'fields'=>['Preenchimento dos dados','Quantidade com cada campo preenchido. CRM e textos das observações são contabilizados sem exposição do conteúdo.'],
                'imported_issued'=>['Emissão dos registros importados','Mês de emissão dos atestados importados ativos. Importações substituídas são desconsideradas.'],
            ];
            foreach ($definitions as $key => [$title, $description]) {
                if (in_array($key, ['issued','imported_issued'], true)) ksort($counts[$key]);
                $imported = $key === 'imported_issued';
                $slides[] = $this->slide('health_' . $type . '_' . $key, 'Atestados ' . $name . ' · ' . $title,
                    $description . ($imported ? ' Base de pessoas com registro importado ativo.' : ' Base de pessoas com documento interno; um por pessoa e tipo.'),
                    $counts[$key], $imported ? $importedTotal : $internalTotal, 'pessoas', in_array($key, ['issued','imported_issued'], true));
            }
        }
        return $slides;
    }

    private function certificateState(string $status, ?string $validity, DateTimeImmutable $today, bool $partial = false): string
    {
        if ($status === 'validado' || ($partial && $status === 'validado_parcial')) {
            if ($validity && $validity < $today->format('Y-m-d')) return 'Vencido';
            return $status === 'validado_parcial' ? 'Validado parcialmente' : 'Validado e vigente';
        }
        return $status === 'reprovado' ? 'Reprovado' : 'Pendente';
    }

    private function slide(string $id, string $title, string $description, array $counts, int $base, string $unit, bool $chronological = false): array
    {
        if (!$chronological) arsort($counts, SORT_NUMERIC);
        $rows = [];
        foreach ($counts as $label => $count) $rows[] = ['label'=>(string) $label,'count'=>$count];
        return compact('id','title','description','rows','base','unit');
    }

    public static function previewReport(): array
    {
        return [
            'summary'=>['people'=>120,'complete'=>96,'accounts'=>85,'active'=>72,'participants'=>90],
            'slides'=>[
                ['id'=>'ages','title'=>'Faixas etárias','description'=>'Dados fictícios para pré-visualização.','rows'=>[['label'=>'6 a 11 anos','count'=>35],['label'=>'12 a 17 anos','count'=>25],['label'=>'18 a 27 anos','count'=>20],['label'=>'28 a 37 anos','count'=>18],['label'=>'60 anos ou mais','count'=>22]],'base'=>120,'unit'=>'pessoas'],
            ],
            'reference_date'=>'19/11/2026','generated_at'=>'19/11/2026 10:00:00','filters_text'=>'Amostra com dados fictícios','unavailable'=>[],
        ];
    }
}
