<?php
require __DIR__ . '/../app/Services/ModalityLevelPdfService.php';
$certificate = ['id'=>12,'nome_completo'=>'Aluno de Exemplo','data_nascimento'=>'2004-06-11','modalidade_nome'=>'Natação','nivel_nome'=>'Intermediário','situacao_avaliacao'=>'apto','status'=>'ativo','data_avaliacao'=>'2026-10-14 10:30:00','created_at'=>'2026-10-14 10:30:00','avaliador_nome'=>'Professor de Exemplo'];
$pdf = (new \App\Services\ModalityLevelPdfService())->render($certificate,['institution'=>'Instituição Esportiva de Exemplo','logo'=>'']);
if (!str_starts_with($pdf,'%PDF-1.4') || !str_contains($pdf,'251.64 757.89 92 64 re S') || !str_contains($pdf,'Certificado N')) throw new RuntimeException('Certificado ou espaço do logo inválido.');
if (!empty($argv[1])) file_put_contents($argv[1],$pdf);
$certificate['status']='inativo';
$historical=(new \App\Services\ModalityLevelPdfService())->render($certificate,['institution'=>'Instituição de Exemplo','logo'=>'']);
if (!str_contains($historical,'Inativo - documento hist')) throw new RuntimeException('Certificado histórico não identificado.');
$certificate['nome_completo']=str_repeat('W',150);
$certificate['modalidade_nome']=str_repeat('W',150);
$certificate['nivel_nome']=str_repeat('W',100);
$certificate['avaliador_nome']=str_repeat('W',150);
$long=(new \App\Services\ModalityLevelPdfService())->render($certificate,['institution'=>str_repeat('W',150),'logo'=>'']);
if (!str_contains($long,'/Count 2')) throw new RuntimeException('Certificado longo não recebeu paginação.');
if (!empty($argv[1])) file_put_contents(dirname($argv[1]) . '/certificado-nivel-longo.pdf',$long);
echo "PDF de nível: cabeçalho, identificação e histórico validados.\n";
