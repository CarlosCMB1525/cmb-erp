<?php
namespace CMBERP\Modules\Cotizaciones\Domain;

if (!defined('ABSPATH')) { exit; }

final class Validators {
    public static function date_or_today(string $fecha): string {
        $fecha = sanitize_text_field($fecha);
        $dt = \DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$dt || $dt->format('Y-m-d') !== $fecha) {
            return date('Y-m-d');
        }
        return $fecha;
    }

    public static function currency(string $moneda): string {
        $m = sanitize_text_field($moneda);
        return ($m === 'USD') ? 'USD' : 'BOB';
    }

    public static function text(string $v, int $max = 255): string {
        $v = sanitize_text_field($v);
        if ($max > 0) $v = substr($v, 0, $max);
        return $v;
    }
}
