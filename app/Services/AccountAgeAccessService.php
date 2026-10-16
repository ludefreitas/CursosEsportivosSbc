<?php
namespace App\Services;
use App\Core\Database;
use PDO;
use RuntimeException;

final class AccountAgeAccessService
{
    public static function isAdult(?string $birthDate): ?bool
    {
        if (!$birthDate) return null;
        $zone=new \DateTimeZone('America/Sao_Paulo');
        $birth=\DateTimeImmutable::createFromFormat('!Y-m-d',$birthDate,$zone);
        $errors=\DateTimeImmutable::getLastErrors();
        if (!$birth || ($errors!==false && ($errors['warning_count']>0 || $errors['error_count']>0)) || $birth->format('Y-m-d')!==$birthDate) return null;
        return $birth->modify('+18 years')<=new \DateTimeImmutable('today',$zone);
    }
    public function blockMessage(array $person): ?string
    {
        if (self::isAdult($person['data_nascimento'] ?? null)!==false) return null;
        $stmt=Database::connection()->prepare('SELECT r.nome_completo FROM vinculos_responsaveis vr INNER JOIN pessoas r ON r.id=vr.responsavel_pessoa_id WHERE vr.dependente_pessoa_id=:id AND vr.data_fim IS NULL AND vr.responsavel_pessoa_id<>vr.dependente_pessoa_id ORDER BY vr.id DESC LIMIT 1');
        $stmt->execute([':id'=>(int)($person['pessoa_id'] ?? $person['id'] ?? 0)]);
        $responsible=$stmt->fetch(PDO::FETCH_ASSOC);
        $message='Este CPF pertence a uma pessoa menor de 18 anos. O acesso à conta e a recuperação de senha ficam bloqueados até que o titular complete 18 anos. O cadastro e as inscrições permanecem preservados.';
        if ($responsible) $message.=' Responsável vinculado: '.$responsible['nome_completo'].'. Acesse pela conta do responsável.';
        else $message.=' Ainda não há um responsável vinculado a este cadastro. Procure a administração para regularizar o vínculo.';
        return $message;
    }
    public function assertAllowed(array $person): void
    {
        $message=$this->blockMessage($person);
        if ($message!==null) throw new RuntimeException($message);
    }
    public function sessionBlockMessage(int $accountId): ?string
    {
        $stmt=Database::connection()->prepare('SELECT p.id AS pessoa_id,p.data_nascimento FROM contas c INNER JOIN pessoas p ON p.cpf=c.cpf WHERE c.id=:id LIMIT 1');
        $stmt->execute([':id'=>$accountId]);$person=$stmt->fetch(PDO::FETCH_ASSOC);
        return $person ? $this->blockMessage($person) : null;
    }
}
