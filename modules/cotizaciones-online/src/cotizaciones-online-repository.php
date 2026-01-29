<?php

namespace CMBERP\Modules\CotizacionesOnline;

if (!defined('ABSPATH')) exit;

final class Repository {
    /** @var \wpdb */
    private $wpdb;

    public function __construct(?\wpdb $wpdb = null) {
        $this->wpdb = $wpdb ?: ($GLOBALS['wpdb'] ?? null);
        if (!$this->wpdb) {
            throw new \RuntimeException('wpdb no disponible');
        }
    }

    public function table(string $suffix): string {
        return $this->wpdb->prefix . $suffix;
    }

    public function table_exists(string $table): bool {
        $exists = $this->wpdb->get_var($this->wpdb->prepare('SHOW TABLES LIKE %s', $table));
        return !empty($exists);
    }

    /**
     * Lista cotizaciones (últimas N).
     * Retorna array de filas normalizadas.
     */
    public function list_quotes(int $limit = 500): array {
        $limit = max(1, min(2000, $limit));

        $t_cot   = $this->table('qt_cotizaciones');
        $t_items = $this->table('qt_cotizacion_items');
        $t_emp   = $this->table('cl_empresas');

        if (!$this->table_exists($t_cot)) {
            return ['__error' => 'No existe la tabla de cotizaciones: ' . $t_cot];
        }

        $has_items = $this->table_exists($t_items);
        $has_emp   = $this->table_exists($t_emp);

        $items_join = '';
        $items_cnt_expr = '0';
        if ($has_items) {
            $items_join = "LEFT JOIN (SELECT cotizacion_id, COUNT(*) AS items_cnt FROM {$t_items} GROUP BY cotizacion_id) it ON it.cotizacion_id = c.id";
            $items_cnt_expr = 'COALESCE(it.items_cnt, 0)';
        }

        $emp_join = '';
        $empresa_expr = "'—'";
        if ($has_emp) {
            $emp_join = "LEFT JOIN {$t_emp} e ON e.id = c.cliente_id";
            $empresa_expr = "COALESCE(NULLIF(e.nombre_legal,''), '—')";
        }

        $sql = "
            SELECT
                c.id,
                c.cot_codigo,
                c.fecha_emision,
                c.total,
                {$empresa_expr} AS empresa,
                COALESCE(NULLIF(c.contacto_nombre,''), '—') AS contacto,
                {$items_cnt_expr} AS items_cnt
            FROM {$t_cot} c
            {$emp_join}
            {$items_join}
            ORDER BY c.id DESC
            LIMIT %d
        ";

        $rows = $this->wpdb->get_results($this->wpdb->prepare($sql, $limit), ARRAY_A);
        if (!is_array($rows)) $rows = [];

        // Normalización mínima
        foreach ($rows as &$r) {
            $r['id'] = (int)($r['id'] ?? 0);
            $r['total'] = (float)($r['total'] ?? 0);
            $r['items_cnt'] = (int)($r['items_cnt'] ?? 0);
        }
        unset($r);

        return $rows;
    }

    /**
     * Obtiene payload para PDF: cotizacion + cliente + items
     */
    public function get_quote_payload(int $id): array {
        $id = (int)$id;
        if ($id <= 0) {
            return ['__error' => 'ID inválido.'];
        }

        $t_cot   = $this->table('qt_cotizaciones');
        $t_items = $this->table('qt_cotizacion_items');
        $t_emp   = $this->table('cl_empresas');

        if (!$this->table_exists($t_cot)) {
            return ['__error' => 'No existe la tabla de cotizaciones: ' . $t_cot];
        }

        $cot = $this->wpdb->get_row(
            $this->wpdb->prepare("SELECT * FROM {$t_cot} WHERE id=%d", $id),
            ARRAY_A
        );
        if (!$cot) {
            return ['__error' => 'Cotización no encontrada.'];
        }

        $cliente = null;
        $cliente_id = (int)($cot['cliente_id'] ?? 0);
        if ($cliente_id > 0 && $this->table_exists($t_emp)) {
            $cliente = $this->wpdb->get_row(
                $this->wpdb->prepare("SELECT id,nombre_legal,nit_id,razon_social,tipo_cliente FROM {$t_emp} WHERE id=%d", $cliente_id),
                ARRAY_A
            );
        }
        if (!is_array($cliente)) {
            $cliente = [
                'id' => $cliente_id,
                'nombre_legal' => '',
                'nit_id' => '',
                'razon_social' => '',
                'tipo_cliente' => ''
            ];
        }

        $items = [];
        if ($this->table_exists($t_items)) {
            $items = $this->wpdb->get_results(
                $this->wpdb->prepare(
                    "SELECT servicio_id,codigo_servicio,nombre_servicio,descripcion,cantidad,precio_unitario,subtotal_item FROM {$t_items} WHERE cotizacion_id=%d ORDER BY orden ASC, id ASC",
                    $id
                ),
                ARRAY_A
            );
            if (!is_array($items)) $items = [];
        }

        // Totales: si la tabla tiene subtotal/descuento/impuestos, se dejan.
        foreach (['subtotal','descuento','impuestos','total'] as $k) {
            if (isset($cot[$k])) $cot[$k] = (float)$cot[$k];
        }

        return [
            'cotizacion' => $cot,
            'cliente' => $cliente,
            'items' => $items,
        ];
    }
}
