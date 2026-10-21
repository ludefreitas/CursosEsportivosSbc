<?php
$root=dirname(__DIR__);
$folder=$root.'/public/uploads/posters';if(!is_dir($folder))mkdir($folder,0755,true);
$name=bin2hex(random_bytes(16)).'.png';$file=$folder.'/'.$name;
$bytes=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
file_put_contents($file,$bytes);
function requestImage(string $path,string $root): string {
    $code='$_SERVER["REQUEST_URI"]='.var_export($path,true).';$_SERVER["REQUEST_METHOD"]="GET";require '.var_export($root.'/index.php',true).';';
    $process=proc_open([PHP_BINARY,'-r',$code],[1=>['pipe','w'],2=>['pipe','w']],$pipes,$root);
    $output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);
    if($exit!==0||$errors!=='')throw new RuntimeException('Falha no front controller: '.$errors);
    return $output;
}
try {
    if(requestImage('/uploads/posters/'.$name,$root)!==$bytes)throw new RuntimeException('Imagem não entregue pela raiz.');
    if(requestImage('/uploads/posters/'.str_repeat('0',32).'.png',$root)!=='Imagem não encontrada.')throw new RuntimeException('Imagem inexistente não tratada.');
    $source=file_get_contents($root.'/index.php');
    if(!str_contains($source,'posters/[a-f0-9]{32}')||!str_contains($source,'pdf-branding/logo-'))throw new RuntimeException('Escopo de imagens públicas ausente.');
    echo "Imagens públicas: entrega binária pela raiz e arquivo inexistente OK.\n";
} finally {unlink($file);}
