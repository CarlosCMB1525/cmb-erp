<?php
namespace CMBERP\Modules\Ventas\Domain;

if (!defined('ABSPATH')) { exit; }

/**
 * Sanitiza items de venta y calcula total.
 * Formato normalizado del legacy: [{n,p,c,m}] donde m=1 manual.
 * Regla: precio negativo solo permitido si item es manual.
 */
final class SaleItemsService {
    /**
     * @param mixed $raw JSON string o array
     * @return array{ok:bool, items:array, json:string, total:float, error:string}
     */
    public static function sanitize_and_total($raw): array {
        $data = $raw;
        if (is_string($raw)) {
            $data = json_decode(wp_unslash($raw), true);
        }
        if (!is_array($data)) {
            return ['ok'=>false,'items'=>[],'json'=>'[]','total'=>0.0,'error'=>'Los ítems no son un JSON válido.'];
        }
        $clean = [];
        $total = 0.0;
        foreach ($data as $it) {
            if (!is_array($it)) { continue; }
            $nombre = $it['n'] ?? ($it['nombre'] ?? '');
            $precio = $it['p'] ?? ($it['precio'] ?? 0);
            $cantidad = $it['c'] ?? ($it['cantidad'] ?? 1);
            $manual = $it['m'] ?? ($it['manual'] ?? 0);

            $nombre = sanitize_text_field((string)$nombre);
            if ($nombre === '') { continue; }

            $precio = (float)$precio;
            $cantidad = (int)$cantidad;
            if ($cantidad < 1) { $cantidad = 1; }

            $manual = (int) (is_bool($manual) ? ($manual ? 1 : 0) : (int)$manual);
            $is_manual = ($manual === 1);
            if (!$is_manual && $precio < 0) { $precio = 0; }

            $precio = round($precio, 2);
            $sub = round($precio * $cantidad, 2);
            $total += $sub;

            $clean[] = ['n'=>$nombre,'p'=>$precio,'c'=>$cantidad,'m'=>$is_manual ? 1 : 0];
        }
        $total = round($total, 2);
        return ['ok'=>true,'items'=>$clean,'json'=>wp_json_encode($clean),'total'=>$total,'error'=>''];
    }

    /**
     * Convierte items de Cotizaciones (payload) a formato de Ventas.
     * @param array $quoteItems [{descripcion, precio_unitario, cantidad, codigo, ...}]
     */
    public static function from_quote_items(array $quoteItems): array {
        $out = [];
        foreach ($quoteItems as $it) {
            if (!is_array($it)) continue;
            $desc = sanitize_text_field((string)($it['descripcion'] ?? $it['nombre_servicio'] ?? ''));
            if ($desc === '') continue;
            $cant = (float)($it['cantidad'] ?? 1);
            if (!is_finite($cant) || $cant <= 0) $cant = 1;
            $pu = (float)($it['precio_unitario'] ?? 0);
            if (!is_finite($pu)) $pu = 0;
            $out[] = ['n'=>$desc,'p'=>round($pu,2),'c'=>(int)round($cant,0),'m'=>0];
        }
        return $out;
    }
}
