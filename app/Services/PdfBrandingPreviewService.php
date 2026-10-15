<?php
namespace App\Services;
final class PdfBrandingPreviewService
{
    public function render(string $type,array $settings): array
    {
        return (new PdfBrandingSettings())->withOverride($type,$settings,function() use($type,$settings) {
            $enrollment=['id'=>0,'turma_id'=>0,'nome_completo'=>'Aluno de exemplo — PRÉ-VISUALIZAÇÃO','cpf'=>'00000000000','status'=>'matriculada','modalidade_nome'=>'Ginástica','temporada_nome'=>'Temporada de exemplo','local_nome'=>'Centro esportivo de exemplo','espaco_nome'=>'Quadra','dias_semana_descricao'=>'Terça e quinta','hora_inicio'=>'09:00','hora_fim'=>'10:00'];
            $class=$enrollment+['nome'=>'Turma de exemplo','professor_principal_nome'=>'Professor de exemplo'];
            if ($type==='chamada') return ['application/pdf',(new AttendanceListPdfService())->render($class,array_fill(0,22,$enrollment))];
            if ($type==='enderecos') return ['application/pdf',(new AddressListPdfService())->render($class,array_fill(0,17,$enrollment+['email'=>'aluno@example.org','logradouro'=>'Rua de exemplo','numero_endereco'=>'100','cidade'=>'São Bernardo do Campo','uf'=>'SP']))];
            if ($type==='declaracao') return ['application/pdf',(new EnrollmentDeclarationPdfService())->render($enrollment,['2026-09','2026-10'],url('/cursos/frequencia').'?codigo=EXEMPLO')];
            if (in_array($type,['grade','frequencia'],true)) {
                ob_start();
                try {
                    if ($type==='frequencia') {
                        $month='2026-10'; $months=['2026-10']; $attendance=[['data_aula'=>'2026-10-01','status'=>'presente'],['data_aula'=>'2026-10-06','status'=>'justificado']]; $code='EXEMPLO';
                        require PdfBrandingSettings::root() . '/app/Views/courses/frequency.php';
                    } else {
                        $error='';$seasons=[['id'=>1,'nome'=>'Temporada de exemplo']];$season=$seasons[0];$seasonId=1;$locationId=1;$spaceId=0;
                        $location=['id'=>1,'nome_local'=>'Centro esportivo de exemplo','apelido_local'=>'Centro de exemplo','logradouro'=>'Rua de exemplo','numero_endereco'=>'100'];$locations=[$location];$spaces=[['id'=>1,'nome'=>'Quadra']];
                        $statusOptions=['inscricoes_abertas'=>'Inscrições abertas'];$selectedStatuses=['inscricoes_abertas'];$excludedClasses=[];$classOptions=[];$fieldOptions=[];$fields=['turma','turma_id','horario','modalidade','professor','temporada'];$unscheduled=0;
                        $event=['id'=>0,'nome'=>'Ginástica funcional','modalidade'=>'Ginástica','professor'=>'Professor de exemplo','hora_inicio'=>'09:00','hora_fim'=>'10:00','horario'=>'09:00 às 10:00'];
                        $periods=[];foreach(['MANHÃ','TARDE','NOITE'] as $name) $periods[]=['nome'=>$name,'linhas'=>[[null,$event,null,$event,null,null,null],[null,null,null,null,null,null,null],[null,null,null,null,null,null,null]]];
                        $sheets=[['espaco'=>$spaces[0],'periodos'=>$periods,'total'=>1]];
                        echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><link rel="stylesheet" href="' . e(asset_url('css/schedule-grid.css')) . '"><style>.schedule-grid-controls{display:none}.sr-only{position:absolute;width:1px;height:1px;overflow:hidden}</style><body class="pagina-grades-horario">';
                        require PdfBrandingSettings::root() . '/app/Views/schedule_grid/index.php'; echo '</body></html>';
                    }
                    $html=ob_get_clean();
                    $base='<base href="' . e(url('/')) . '">';
                    $html=str_replace('<meta charset="utf-8">','<meta charset="utf-8">'.$base,$html);
                    return ['text/html; charset=UTF-8',$html];
                } catch (\Throwable $error) { ob_end_clean(); throw $error; }
            }
            $pdf=(new ModalityLevelPdfService())->render(['id'=>0,'nome_completo'=>'Aluno de exemplo — PRÉ-VISUALIZAÇÃO','data_nascimento'=>'2000-01-01','modalidade_nome'=>'Ginástica','nivel_nome'=>'Intermediário','status'=>'ativo','situacao_avaliacao'=>'apto','avaliador_nome'=>'Professor de exemplo','data_avaliacao'=>'2026-10-14','created_at'=>'2026-10-14'],$settings);
            return ['application/pdf',$pdf];
        });
    }
}
