<?php
namespace App\Services;
use RuntimeException;

final class SusCardService
{
    public const HELP='Informe os 15 dígitos do Cartão Nacional de Saúde (CNS). Esse dado é importante para manter seu cadastro atualizado. Se não souber o número ou tiver dúvidas, procure uma Unidade Básica de Saúde (UBS) para obter ou confirmar a informação e atualize este campo.';
    public static function normalize(string $value): string {return preg_replace('/\D/','',$value) ?? '';}
    public static function isValid(string $value): bool
    {
        if (preg_match('/[^0-9.\s-]/u',$value)) return false;
        $number=self::normalize($value);
        if (!preg_match('/^[12789][0-9]{14}$/',$number)) return false;
        if (preg_match('/([0-9])\1{10}/',$number)) return false;
        for ($period=1;$period<=7;$period++) if (substr(str_repeat(substr($number,0,$period),15),0,15)===$number) return false;
        foreach ([1,-1] as $direction) {
            $sequential=true;
            for ($i=1;$i<15;$i++) if (((int)$number[$i-1]+$direction+10)%10!==(int)$number[$i]) {$sequential=false;break;}
            if ($sequential) return false;
        }
        for ($start=0;$start<=20;$start++) {
            $sequence='';for ($i=$start;$i<$start+30;$i++) $sequence.=(string)$i;
            if (substr($sequence,0,15)===$number) return false;
        }
        // Algoritmos CNS publicados pela ANS: prefixos 1/2 e provisórios 7/8/9.
        $sum=0;
        if ($number[0]==='1'||$number[0]==='2') {
            for ($i=0;$i<11;$i++) $sum+=(int)$number[$i]*(15-$i);
            $digit=11-($sum%11);if ($digit===11)$digit=0;
            $tail=$digit===10 ? '001'.(11-(($sum+2)%11)) : '000'.$digit;
            return $number===substr($number,0,11).$tail;
        }
        for ($i=0;$i<15;$i++) $sum+=(int)$number[$i]*(15-$i);
        return $sum%11===0;
    }
    public static function assertOptional(string $value): void
    {
        if (trim($value)==='') return;
        if (!self::isValid($value)) throw new RuntimeException('Número do cartão SUS inválido. Confira os 15 dígitos no seu Cartão Nacional de Saúde (CNS) e corrija o número informado. Em caso de dúvida, procure uma Unidade Básica de Saúde (UBS) para confirmar a informação.');
    }
    public static function pending(string $value): bool {return !self::isValid($value);}
    public static function label(string $value): string {return self::pending($value) ? 'Pendente — cartão SUS '.(trim($value)===''?'não informado':'inválido') : 'Cartão SUS informado';}
    public static function alert(array $person): ?array
    {
        $number=(string)($person['numero_cartao_sus'] ?? '');
        if (!self::pending($number))return null;
        return ['level'=>'warning','message'=>'O cadastro de '.$person['nome_completo'].' está com pendência: cartão SUS '.(trim($number)===''?'não informado':'inválido').'. Para regularizar, informe os 15 dígitos do Cartão Nacional de Saúde (CNS). Se precisar obter ou confirmar o número, procure uma Unidade Básica de Saúde (UBS) e atualize o cadastro.','sus_person_id'=>(int)$person['id'],'action_label'=>'Informar cartão SUS','action_url'=>url('/dashboard').'?sus_pessoa='.(int)$person['id']];
    }
}
