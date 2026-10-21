<?php
namespace App\Services;

use App\Core\Database;
use PDO;
use RuntimeException;

final class PosterService
{
    public function __construct(private ?string $store = null) { $this->store ??= ROOT_PATH . '/config/cartazes.json'; }
    public function all(): array
    {
        if (!is_file($this->store)) return [];
        $file = fopen($this->store, 'r');
        if (!$file || !flock($file, LOCK_SH)) throw new RuntimeException('Não foi possível ler os cartazes.');
        try { return json_decode(stream_get_contents($file), true, 512, JSON_THROW_ON_ERROR) ?: []; }
        finally { flock($file, LOCK_UN); fclose($file); }
    }
    public function save(array $data, int $account): array
    {
        $id = (string) ($data['id'] ?? '');
        if ($id !== '' && !preg_match('/^[a-f0-9]{24}$/', $id)) throw new RuntimeException('Cartaz inválido.');
        $record = ['id'=>$id ?: bin2hex(random_bytes(12)), 'local_id'=>max(0,(int)($data['local_id']??0)), 'temporada_id'=>max(0,(int)($data['temporada_id']??0)), 'updated_at'=>date('c'), 'updated_by'=>$account];
        foreach (['name','title','subtitle','modalities','address','heading','instructions','free_text','contact','footer_text'] as $field) {
            $record[$field] = mb_substr(trim((string)($data[$field]??'')), 0, $field==='modalities'?1800:600);
        }
        if ($record['name']==='' || !$record['local_id']) throw new RuntimeException('Informe o nome do cartaz e escolha um local.');
        $record['site']=mb_substr(trim((string)($data['site']??'')),0,350);
        foreach (['primary','accent','background','text'] as $field) {
            $value=(string)($data[$field]??'');
            if (!preg_match('/^#[a-f0-9]{6}$/i',$value)) throw new RuntimeException('Escolha uma cor válida.');
            $record[$field]=$value;
        }
        foreach (['photo1','photo2','photo3','photo4','logo1','logo2','logo3','bg_header','bg_body','bg_footer'] as $field) {
            $path=(string)($data[$field]??'');
            if ($path!=='' && !preg_match('~^/uploads/posters/[a-f0-9]{32}\.(jpg|png|webp)$~',$path)) throw new RuntimeException('Imagem inválida.');
            $record[$field]=$path;
        }
        foreach (['photo1','photo2','photo3','photo4'] as $photo) {
            foreach (['x'=>[0,100,50],'y'=>[0,100,50],'zoom'=>[100,300,100]] as $axis=>$range) {
                $key=$photo.'_'.$axis;
                $record[$key]=max($range[0],min($range[1],(int)($data[$key]??$range[2])));
            }
        }
        $file=fopen($this->store,'c+');
        if (!$file || !flock($file,LOCK_EX)) throw new RuntimeException('Não foi possível salvar o cartaz.');
        try {
            $records=json_decode(stream_get_contents($file)?:'[]',true,512,JSON_THROW_ON_ERROR);
            $previous=$records[$record['id']]??null;
            $history=$previous['history']??[];
            if ($previous) {
                if (isset($data['version']) && (int)$data['version'] !== (int)($previous['version']??1)) throw new RuntimeException('Esta campanha foi alterada em outra janela. Reabra-a antes de salvar.');
                unset($previous['history']);
                $history[]=$previous;
            }
            $record['version']=(int)($previous['version']??0)+1;
            $record['history']=$history;
            $records[$record['id']]=$record;
            $json=json_encode($records,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR);
            rewind($file); if (!ftruncate($file,0) || fwrite($file,$json)===false) throw new RuntimeException('Falha ao gravar o cartaz.'); fflush($file);
        } finally { flock($file,LOCK_UN); fclose($file); }
        return $record;
    }
    public function upload(array $file): string
    {
        if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || ($file['size']??0)>6*1024*1024) throw new RuntimeException('Envie uma imagem JPG, PNG ou WebP de até 6 MB.');
        $size=@getimagesize($file['tmp_name']);
        $ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$size['mime']??'']??null;
        if (!$ext || $size[0]>6000 || $size[1]>6000) throw new RuntimeException('Imagem inválida ou maior que 6.000 pixels por lado.');
        $directory=ROOT_PATH.'/public/uploads/posters';
        if (!is_dir($directory) && !mkdir($directory,0755,true)) throw new RuntimeException('Não foi possível preparar a pasta de imagens.');
        $path='/uploads/posters/'.bin2hex(random_bytes(16)).'.'.$ext;
        if (!move_uploaded_file($file['tmp_name'],ROOT_PATH.'/public'.$path)) throw new RuntimeException('Não foi possível guardar a imagem.');
        return $path;
    }
    public function options(): array
    {
        $pdo=Database::connection();
        return ['locations'=>$pdo->query('SELECT id,nome_local,apelido_local,logradouro,numero_endereco,bairro,cidade,uf FROM locais_treino WHERE ativo=1 ORDER BY nome_local')->fetchAll(PDO::FETCH_ASSOC),
            'seasons'=>$pdo->query('SELECT id,nome FROM temporadas ORDER BY data_inicio DESC,id DESC')->fetchAll(PDO::FETCH_ASSOC),
            'modalities'=>$pdo->query('SELECT DISTINCT t.local_treino_id,t.temporada_id,m.nome FROM turmas t INNER JOIN modalidades m ON m.id=t.modalidade_id WHERE t.ativo=1 AND m.ativo=1 ORDER BY m.nome')->fetchAll(PDO::FETCH_ASSOC)];
    }
}
