<?php
require __DIR__ . '/../app/Services/DeclarationUrlService.php';
use App\Services\DeclarationUrlService;
foreach ([
    [['HTTP_HOST'=>'dominio-antigo.test','HTTPS'=>'on'], 'https://dominio-antigo.test/cursos/declaracao?codigo=abc'],
    [['HTTP_HOST'=>'dominio-novo.test','HTTP_X_FORWARDED_PROTO'=>'https'], 'https://dominio-novo.test/cursos/declaracao?codigo=abc'],
    [['HTTP_HOST'=>'127.0.0.1:8128','HTTPS'=>'off'], 'http://127.0.0.1:8128/cursos/declaracao?codigo=abc'],
    [['HTTP_HOST'=>'novo.test','HTTP_X_FORWARDED_PROTO'=>'https,http'], 'https://novo.test/cursos/declaracao?codigo=abc'],
] as [$server,$expected]) {
    if (DeclarationUrlService::absolute('/cursos/declaracao?codigo=abc',$server) !== $expected) { throw new RuntimeException('Origem incorreta.'); }
}
try { DeclarationUrlService::absolute('/cursos/declaracao',['HTTP_HOST'=>"site.test\r\nX-Test: injected"]); throw new LogicException('Host inválido aceito.'); } catch (RuntimeException $e) { }
echo "Endereços de declaração: domínio dinâmico, HTTPS e porta verificados.\n";
