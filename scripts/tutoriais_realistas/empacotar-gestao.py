"""Prepara os vídeos e SQL de publicação sem acessar o banco ou o site."""
import hashlib,html,json,shutil,zipfile
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
OUT=ROOT/'output/tutoriais-gestao';OUT.mkdir(parents=True,exist_ok=True)
KINDS=['criar-temporada','criar-modalidade','criar-cronograma','criar-turma']
infos=[]
for kind in KINDS:
 folder=ROOT/f'output/tutorial-{kind}-realista'
 infos.append(json.loads((folder/'informacoes.json').read_text(encoding='utf-8')))
cards=[]
for info in infos:
 folder='../tutorial-'+info['kind']+'-realista/'
 cards.append('<article><h2>'+html.escape(info['title'])+'</h2><video controls playsinline preload="none" poster="'+folder+'capa.png" src="'+folder+'tutorial.mp4"><track kind="subtitles" srclang="pt-BR" label="Português" src="'+folder+'legendas.vtt"></video><p><a href="'+folder+'assistir.html">Assistir com capítulos</a> · <a download href="'+folder+'versao-leve-celular.mp4">Baixar versão leve</a></p></article>')
(OUT/'index.html').write_text('<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Tutoriais de gestão</title><style>body{max-width:1200px;margin:30px auto;padding:0 18px;background:#eef4f6;color:#143e50;font:17px/1.5 Arial}main{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,450px),1fr));gap:20px}article{padding:20px;background:white;border-radius:16px}video{width:100%;background:#000;border-radius:10px}a{color:#126686}</style><h1>Tutoriais de gestão dos cursos</h1><p>Telas reais, narração em português e dados demonstrativos. Nenhum cadastro foi feito no site durante as gravações.</p><main>'+''.join(cards)+'</main></html>',encoding='utf-8')
package=OUT/'publicacao';(package/'public/uploads/tutorial-videos').mkdir(parents=True,exist_ok=True)
sql=[]
for area,id in [('admin',1),('professor',2)]:
 videos=[]
 for info in infos:
  if area=='professor' and info['kind']=='criar-temporada':continue
  file=ROOT/f'output/tutorial-{info["kind"]}-realista/versao-leve-celular.mp4'
  # Cada biblioteca possui seu arquivo, permitindo remover/substituir sem afetar a outra.
  name=hashlib.md5(area.encode()+file.read_bytes()).hexdigest()+'.mp4'
  relative='/uploads/tutorial-videos/'+name
  target=package/('public'+relative);shutil.copy2(file,target)
  local=ROOT/('public'+relative);local.parent.mkdir(parents=True,exist_ok=True)
  if not local.exists():shutil.copy2(file,local)
  videos.append({'titulo':info['title'],'tipo':'local','arquivo':relative,'nome_arquivo':info['kind']+'.mp4'})
 payload=json.dumps(videos,ensure_ascii=False).replace("'","''")
 sql.append(f"UPDATE pagina_tutorial_equipe_config SET videos_json='{payload}' WHERE id={id} AND (videos_json='[]' OR videos_json='');")
(package/'publicar-videos.sql').write_text('SET NAMES utf8mb4;\n'+'\n'.join(sql)+'\n',encoding='utf-8')
shutil.copy2(ROOT/'database/migracao_ajuda_equipe.sql',package/'migracao_ajuda_equipe.sql')
(package/'LEIA-ME.txt').write_text('Publicação dos tutoriais de gestão\n\n1. Publique as alterações de código.\n2. Copie public/uploads/tutorial-videos/ preservando os nomes e sem apagar os uploads existentes.\n3. Execute migracao_ajuda_equipe.sql separadamente no banco de destino.\n4. Execute publicar-videos.sql, também separadamente. Só preenche bibliotecas vazias; não sobrescreve vídeos existentes.\n5. Abra Ajuda e tutoriais no admin e no professor. O master administra as duas bibliotecas.\n\nTemporada: apenas na biblioteca admin. Modalidade, cronograma e turma: nas duas. Os vídeos usam dados fictícios e salvamentos simulados.\nNenhum SQL é executado por este empacotador.\n',encoding='utf-8')
with zipfile.ZipFile(OUT/'publicacao-tutoriais.zip','w',zipfile.ZIP_DEFLATED) as z:
 for file in package.rglob('*'):
  if file.is_file():z.write(file,file.relative_to(package))
(OUT/'manifesto.json').write_text(json.dumps(infos,ensure_ascii=False,indent=2),encoding='utf-8')
print(json.dumps(infos,ensure_ascii=False,indent=2))
