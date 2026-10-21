<?php
require __DIR__.'/../app/Services/PosterService.php';
$path=sys_get_temp_dir().'/poster-test-'.bin2hex(random_bytes(6)).'.json';
$service=new App\Services\PosterService($path);
$base=['name'=>'Cartaz de teste','local_id'=>1,'temporada_id'=>1,'title'=>'Orquídeas','site'=>'https://example.org/','primary'=>'#145d74','accent'=>'#ed8a27','background'=>'#f4f0e5','text'=>'#164454','modalities'=>"Ginástica\nPilates",'photo1_x'=>80,'photo1_y'=>25,'photo1_zoom'=>170];
try {
    $record=$service->save($base,7);
    if($record['photo1_x']!==80||$record['photo1_zoom']!==170||$record['updated_by']!==7)throw new RuntimeException('Enquadramento ou autoria incorretos.');
    $record['title']='Novo título';$service->save($record,7);
    if(count($service->all())!==1||$service->all()[$record['id']]['title']!=='Novo título')throw new RuntimeException('Edição não preservada.');
    $stored=$service->all()[$record['id']];
    if($stored['version']!==2||count($stored['history'])!==1||$stored['history'][0]['title']!=='Orquídeas')throw new RuntimeException('Histórico de campanha perdido.');
    try{$service->save($record,7);throw new LogicException('Versão desatualizada aceita.');}catch(RuntimeException $expected){}
    unset($record['id']);$copy=$service->save($record,7);
    if(count($service->all())!==2)throw new RuntimeException('Cópia não criada.');
    $copy['site']='www.exemplo.com.br';$copy=$service->save($copy,7);
    if($copy['site']!=='www.exemplo.com.br')throw new RuntimeException('Texto livre do site rejeitado.');
    $copy['site']='';$copy=$service->save($copy,7);
    if($copy['site']!=='')throw new RuntimeException('Texto opcional do site rejeitado.');
    foreach([['photo1'=>'/etc/passwd'],['primary'=>'red'],['id'=>'../test']] as $invalid){
        try{$service->save(array_replace($base,$invalid),7);throw new LogicException('Entrada inválida aceita.');}catch(RuntimeException $expected){}
    }
    echo "Cartazes: salvar, editar, duplicar, enquadramento, autoria e validação de URLs/imagens/cores OK.\n";
}finally{if(is_file($path))unlink($path);}
