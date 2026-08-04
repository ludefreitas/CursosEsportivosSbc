<?php
$fields = [
    'Identificador externo' => $migrationRecord['id_externo'] ?? '',
    'Nome completo' => $migrationRecord['nome_completo'] ?? '',
    'CPF' => format_cpf((string) ($migrationRecord['cpf'] ?? '')),
    'Data de nascimento' => !empty($migrationRecord['data_nascimento']) ? date('d/m/Y', strtotime((string) $migrationRecord['data_nascimento'])) : '',
    'Sexo' => $migrationRecord['sexo'] ?? '',
    'WhatsApp' => $migrationRecord['telefone_whatsapp'] ?? '',
    'E-mail' => $migrationRecord['email'] ?? '',
    'Cartão SUS' => $migrationRecord['numero_cartao_sus'] ?? '',
    'CEP' => format_cep((string) ($migrationRecord['cep'] ?? '')),
    'Logradouro' => $migrationRecord['logradouro'] ?? '',
    'Número' => $migrationRecord['numero_endereco'] ?? '',
    'Complemento' => $migrationRecord['complemento'] ?? '',
    'Bairro' => $migrationRecord['bairro'] ?? '',
    'Cidade' => $migrationRecord['cidade'] ?? '',
    'UF' => $migrationRecord['uf'] ?? '',
    'Contato de emergência' => $migrationRecord['contato_emergencia_nome'] ?? '',
    'Telefone de emergência' => $migrationRecord['contato_emergencia_telefone'] ?? '',
    'Responsável 1' => $migrationRecord['responsavel1_nome'] ?? '',
    'CPF do responsável 1' => format_cpf((string) ($migrationRecord['responsavel1_cpf'] ?? '')),
    'Responsável 2' => $migrationRecord['responsavel2_nome'] ?? '',
    'CPF do responsável 2' => format_cpf((string) ($migrationRecord['responsavel2_cpf'] ?? '')),
    'Situação na origem' => $migrationRecord['situacao_origem'] ?? '',
    'Situação da migração' => ($migrationRecord['status_migracao'] ?? '') === 'migrado' ? 'Migrado' : 'Pendente',
];
?>
<div class="popup-meta-list admin-migration-details-grid">
    <?php foreach ($fields as $label => $value) { ?>
        <p><strong><?php echo e($label); ?>:</strong> <span><?php echo e(trim((string) $value) !== '' ? (string) $value : '-'); ?></span></p>
    <?php } ?>
</div>
