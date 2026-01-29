<?php
declare(strict_types=1);
namespace CMBERP\Modules\Cashflow;

if (!defined('ABSPATH')) { exit; }

final class Settings {
    public const OPT_EGRESO_CATS = 'cmb_cashflow_categorias_egreso';
    public const OPT_METODOS = 'cmb_cashflow_metodos_pago';
    public const DB_VER_OPT = 'cmb_cashflow_db_ver';
    public const DB_VER = '1.0.6';

    public const TABLE_NAME = 'cmb_cashflow_movimientos';
    public const TABLE_PAYMENTS = 'cmb_cashflow_pagos';

    /**
     * Catálogo enterprise por defecto (editable desde CPT, se sincroniza).
     */
    public static function default_egreso_cats(): array {
        return [
            // COGS
            'Diseño gráfico (terceros)',
            'Edición de video (terceros)',

            // OPEX
            'Luz',
            'Internet',
            'Mantenimiento',
            'Teléfonos (planes/servicio)',
            'Alquiler',
            'Transporte',
            'Café / refrigerios',
            'Material de oficina',
            'Publicidad',
            'Pagos online (comisiones pasarela)',

            // Suscripciones
            'YouTube Premium',
            'Spotify',
            'CapCut',
            'Microsoft 365',
            'Dominios',
            'Hosting',

            // Finanzas
            'Intereses y comisiones bancarias',
            'Intereses – Tarjeta de crédito',
            'Pago de capital – Préstamo bancario',
            'Pago de capital – Tarjeta de crédito',

            // CAPEX
            'Cámaras / luces / trípodes / micrófonos',
            'Teléfonos celulares (equipos)',
            'Computadoras',
            'Libros',

            // Conciliación
            'IVA/IT (Pagado)',
            // Compat histórico
            'Impuestos',
        ];
    }

    public static function default_metodos(): array {
        return ['Efectivo','QR','Transferencia','Tarjeta de Crédito','Tarjeta Internacional'];
    }

    public static function cats(): array {
        $cats = get_option(self::OPT_EGRESO_CATS, self::default_egreso_cats());
        $cats = array_values(array_filter(array_map('trim', (array)$cats)));
        return !empty($cats) ? $cats : self::default_egreso_cats();
    }

    public static function mets(): array {
        $mets = get_option(self::OPT_METODOS, self::default_metodos());
        $mets = array_values(array_filter(array_map('trim', (array)$mets)));
        return !empty($mets) ? $mets : self::default_metodos();
    }
}
