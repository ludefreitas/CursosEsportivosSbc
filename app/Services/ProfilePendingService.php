<?php

namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

/** Diagnóstico de leitura: não marca o cadastro como completo nem altera a pessoa. */
class ProfilePendingService
{
    public function report(int $id, string $type): array
    {
        if ($id < 1 || !in_array($type, ['pessoa', 'conta'], true)) { throw new RuntimeException('Selecione um cadastro válido.'); }
        $pdo = Database::connection();
        $query = $type === 'conta'
            ? 'SELECT p.* FROM pessoas p INNER JOIN contas c ON c.cpf=p.cpf WHERE c.id=:id'
            : 'SELECT p.* FROM pessoas p WHERE p.id=:id';
        $statement = $pdo->prepare($query);
        $statement->execute([':id' => $id]);
        $person = $statement->fetch(PDO::FETCH_ASSOC);
        if (!$person) { throw new RuntimeException('Cadastro não encontrado.'); }
        $account = $pdo->prepare('SELECT id FROM contas WHERE cpf=:cpf');
        $account->execute([':cpf' => $person['cpf']]);
        $ownAccount = (bool) $account->fetchColumn();
        $link = $pdo->prepare('SELECT r.id, r.telefone_whatsapp FROM vinculos_responsaveis vr INNER JOIN pessoas r ON r.id=vr.responsavel_pessoa_id WHERE vr.dependente_pessoa_id=:id AND vr.data_fim IS NULL ORDER BY vr.id DESC LIMIT 1');
        $link->execute([':id' => $person['id']]);
        $responsible = $link->fetch(PDO::FETCH_ASSOC);
        $otherResponsible = $responsible && (int) $responsible['id'] !== (int) $person['id'];
        if (!$ownAccount && $otherResponsible) { $person['telefone_whatsapp'] = $responsible['telefone_whatsapp']; }
        $pending = self::diagnose($person, $ownAccount, static fn(string $cep): array => (new CepService())->avaliarCep($cep));
        if ($ownAccount) {
            $phones = $pdo->prepare('SELECT p.contato_emergencia_telefone FROM vinculos_responsaveis vr INNER JOIN pessoas p ON p.id=vr.dependente_pessoa_id WHERE vr.responsavel_pessoa_id=:id AND p.id<>:proprio');
            $phones->execute([':id' => $person['id'], ':proprio' => $person['id']]);
            $whatsapp = preg_replace('/\D/', '', (string) ($person['telefone_whatsapp'] ?? ''));
            foreach ($phones->fetchAll(PDO::FETCH_COLUMN) as $phone) {
                if ($whatsapp !== '' && $whatsapp === preg_replace('/\D/', '', (string) $phone)) {
                    $pending[] = 'O WhatsApp é igual ao telefone de emergência de um dependente. Informe outro número ou atualize o contato de emergência correspondente.';
                    break;
                }
            }
        }
        if ($ownAccount && $otherResponsible && !(int) $person['cadastro_completo']) {
            $pending[] = 'Este cadastro está vinculado como dependente de outro responsável. É necessário transferir a responsabilidade para o próprio CPF antes de concluir o cadastro da conta.';
        }
        return ['name' => (string) $person['nome_completo'], 'complete' => (int) $person['cadastro_completo'] === 1,
            'pending' => $pending, 'guidance' => (int) $person['cadastro_completo'] === 1
                ? 'O cadastro já está marcado como completo. A lista pode ter sido atualizada por outro usuário.'
                : 'Após corrigir os itens, conclua e salve o formulário de cadastro completo. A consulta das pendências não altera o cadastro.'];
    }

    public static function diagnose(array $person, bool $ownAccount, ?callable $evaluateCep = null): array
    {
        $pending = [];
        if (!validar_nome_cadastro((string) ($person['nome_completo'] ?? ''))) $pending[] = 'Nome completo: informe no mínimo 14 caracteres, usando apenas letras, espaços, hífen ou apóstrofo.';
        if (!validar_cpf((string) ($person['cpf'] ?? ''))) $pending[] = 'CPF inválido. Solicite a correção do cadastro.';
        $date = (string) ($person['data_nascimento'] ?? '');
        $birth = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        $today = new \DateTimeImmutable('today', new \DateTimeZone('America/Sao_Paulo'));
        if (!$birth || $birth->format('Y-m-d') !== $date || $birth > $today) $pending[] = 'Data de nascimento ausente ou inválida. Solicite a correção do cadastro.';
        elseif ($ownAccount && $birth->diff($today)->y < 18) $pending[] = 'A conta precisa pertencer a uma pessoa maior de idade. Verifique a data de nascimento e o responsável.';
        if (!in_array($person['sexo'] ?? '', ['masculino','feminino','Sexo não declarado'], true)) $pending[] = 'Selecione o sexo: masculino, feminino ou Sexo não declarado.';
        foreach (['telefone_whatsapp'=>'WhatsApp','logradouro'=>'Endereço','numero_endereco'=>'Número do endereço','bairro'=>'Bairro','cidade'=>'Cidade','uf'=>'UF','contato_emergencia_nome'=>'Nome do contato de emergência','contato_emergencia_telefone'=>'Telefone do contato de emergência'] as $field => $label) {
            if (trim((string) ($person[$field] ?? '')) === '') $pending[] = $label . ' não informado.';
        }
        if (!filter_var(trim((string) ($person['email'] ?? '')), FILTER_VALIDATE_EMAIL)) $pending[] = 'E-mail ausente ou inválido.';
        $cep = normalize_cep((string) ($person['cep'] ?? ''));
        if (strlen($cep) !== 8) $pending[] = 'Informe um CEP válido com 8 dígitos.';
        elseif ($evaluateCep !== null) {
            $evaluation = $evaluateCep($cep);
            if (empty($evaluation['aceito'])) $pending[] = 'CEP: ' . $evaluation['mensagem'];
        }
        $validResponsible = false;
        foreach ([1,2] as $number) {
            $name = trim((string) ($person['responsavel'.$number.'_nome'] ?? ''));
            if (!validar_nome_pessoa($name)) $pending[] = 'Corrija o nome do responsável ' . $number . ': use apenas letras, espaços, hífen ou apóstrofo.';
            if ($name !== '' && validar_cpf((string) ($person['responsavel'.$number.'_cpf'] ?? ''))) $validResponsible = true;
        }
        if (!$validResponsible) $pending[] = 'Informe pelo menos um responsável com nome e CPF válidos. Pode ser a própria pessoa.';
        $emergencyName = (string) ($person['contato_emergencia_nome'] ?? '');
        if ($emergencyName !== '' && !validar_nome_pessoa($emergencyName)) $pending[] = 'Corrija o nome do contato de emergência: use apenas letras, espaços, hífen ou apóstrofo.';
        $phone = preg_replace('/\D/', '', (string) ($person['telefone_whatsapp'] ?? ''));
        $emergency = preg_replace('/\D/', '', (string) ($person['contato_emergencia_telefone'] ?? ''));
        if ($phone !== '' && $phone === $emergency) $pending[] = 'O telefone de emergência deve ser diferente do WhatsApp do responsável.';
        if (array_sum(array_map(static fn(string $key): int => !empty($person[$key]) ? 1 : 0, ['eh_pcd','eh_plm','eh_pvs'])) > 1) $pending[] = 'Selecione somente uma condição entre PCD, PLM e PVS.';
        $sus = trim((string) ($person['numero_cartao_sus'] ?? ''));
        if ($sus !== '' && !SusCardService::isValid($sus)) $pending[] = 'Número do cartão SUS inválido. Confira os 15 dígitos no cartão.';
        if (!$pending && empty($person['cadastro_completo'])) $pending[] = 'Os campos obrigatórios estão preenchidos. Falta concluir e salvar o formulário de cadastro completo para confirmar a finalização.';
        return $pending;
    }
}
