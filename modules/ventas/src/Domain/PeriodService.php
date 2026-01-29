<?php
namespace CMBERP\Modules\Ventas\Domain;

if (!defined('ABSPATH')) { exit; }

final class PeriodService {
    public static function periodo_literal(string $fecha): string {
        $meses = ["", "ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE"]; 
        $ts = strtotime($fecha);
        if (!$ts) return '---';
        $m = (int) date('m', $ts);
        $y = date('Y', $ts);
        return ($meses[$m] ?? '---') . ' DE ' . $y;
    }

    public static function validate_date_ymd(string $ymd): bool {
        $ymd = sanitize_text_field($ymd);
        $dt = \DateTime::createFromFormat('Y-m-d', $ymd);
        return ($dt && $dt->format('Y-m-d') === $ymd);
    }
}
