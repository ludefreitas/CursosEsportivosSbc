<?php
namespace App\Controllers;
use App\Core\Controller;
use App\Services\CourseInterestService;

class CourseInterestController extends Controller
{
    public function record(): void
    {
        // Somente requisições do próprio site. O registro não interfere na inscrição.
        if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest') {
            $this->jsonResponse(['success'=>false],403);
        }
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin !== '' && strcasecmp((string) parse_url($origin,PHP_URL_HOST),explode(':',(string) ($_SERVER['HTTP_HOST'] ?? ''))[0]) !== 0) $this->jsonResponse(['success'=>false],403);
        $type = is_string($_POST['tipo'] ?? null) ? $_POST['tipo'] : '';
        $id = filter_var($_POST['id'] ?? null,FILTER_VALIDATE_INT);
        if (!in_array($type,['local','modalidade','turma'],true) || !$id || $id<1) $this->jsonResponse(['success'=>false],422);
        $key=$type.':'.$id; $now=time();
        $recent=(array) ($_SESSION['course_interest_recent'] ?? []);
        if (isset($recent[$key]) && $now-(int)$recent[$key]<2) $this->jsonResponse(['success'=>true,'counted'=>false]);
        try {
            $counted=(new CourseInterestService())->record($type,(int)$id);
            if ($counted) {
                $recent=array_filter($recent,static fn($timestamp)=>$now-(int)$timestamp<60);
                $recent[$key]=$now;
                $_SESSION['course_interest_recent']=array_slice($recent,-50,null,true);
            }
            $this->jsonResponse(['success'=>true,'counted'=>$counted]);
        } catch (\Throwable $error) {
            error_log('[Interesse nos cursos] '.$error->getMessage());
            $this->jsonResponse(['success'=>false],503);
        }
    }
}
