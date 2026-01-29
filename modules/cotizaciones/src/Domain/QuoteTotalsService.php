<?php
namespace CMBERP\Modules\Cotizaciones\Domain;

if (!defined('ABSPATH')) { exit; }

final class QuoteTotalsService {

    /**
     * Sanitiza items y calcula totales.
     * Regla: precio negativo solo permitido para ítems MANUAL (o servicio_id=0).
     * Soporta group_key (UI) y grupo_id (DB) sin alterar.
     */
    public static function sanitize_items(array $items): array {
        $safe_items = [];
        $subtotal = 0.0;
        $orden = 0;

        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $orden++;

            $servicio_id = absint($it['servicio_id'] ?? 0);
            $codigo = sanitize_text_field((string)($it['codigo_servicio'] ?? ''));
            $nombre = sanitize_text_field((string)($it['nombre_servicio'] ?? ''));
            $desc = wp_kses_post((string)($it['descripcion'] ?? ''));

            $cant = (float)($it['cantidad'] ?? 1);
            if (!is_finite($cant) || $cant <= 0) $cant = 1;

            $pu = (float)($it['precio_unitario'] ?? 0);
            if (!is_finite($pu)) $pu = 0;

            $is_manual = (strtoupper($codigo) === 'MANUAL' || $servicio_id === 0);
            if (!$is_manual && $pu < 0) $pu = 0;

            $sub_item = round($cant * $pu, 2);
            $subtotal += $sub_item;

            $safe_items[] = [
                // grouping
                'group_key' => sanitize_text_field((string)($it['group_key'] ?? '')),
                'grupo_id' => absint($it['grupo_id'] ?? 0),

                'servicio_id' => $servicio_id,
                'codigo_servicio' => $codigo,
                'nombre_servicio' => $nombre,
                'descripcion' => $desc,
                'cantidad' => $cant,
                'precio_unitario' => round($pu, 2),
                'subtotal_item' => $sub_item,
                'orden' => $orden,
            ];
        }

        $subtotal = round($subtotal, 2);
        $total = $subtotal;
        if ($total < 0) $total = 0;

        return [
            'items' => $safe_items,
            'subtotal' => $subtotal,
            'total' => $total,
        ];
    }
}
