<?php

namespace CMBERP\Modules\Facturacion;

if (!defined('ABSPATH')) exit;

final class Repository {
    /** @var \wpdb */
    private $wpdb;

    public function __construct(?\wpdb $wpdb = null) {
        $this->wpdb = $wpdb ?: ($GLOBALS['wpdb'] ?? null);
        if (!$this->wpdb) throw new \RuntimeException('wpdb no disponible');
    }

    private function t_ventas(): string { return $this->wpdb->prefix . 'vn_ventas'; }
    private function t_empresas(): string { return $this->wpdb->prefix . 'cl_empresas'; }
    private function t_facturas(): string { return $this->wpdb->prefix . 'vn_facturas'; }

    public function table_columns(string $table): array {
        $cols = $this->wpdb->get_results("SHOW COLUMNS FROM `{$table}`");
        $out = [];
        if (is_array($cols)) {
            foreach ($cols as $c) {
                $out[(string)$c->Field] = true;
            }
        }
        return $out;
    }

    public function safe_date(string $d): string {
        $d = sanitize_text_field($d);
        $dt = \DateTime::createFromFormat('Y-m-d', $d);
        if (!$dt || $dt->format('Y-m-d') !== $d) return '';
        return $d;
    }

    public function formatear_periodo($fecha_raw): string {
        if (empty($fecha_raw)) return '---';
        $meses = ["", "ENERO","FEBRERO","MARZO","ABRIL","MAYO","JUNIO","JULIO","AGOSTO","SEPTIEMBRE","OCTUBRE","NOVIEMBRE","DICIEMBRE"];
        $ts = strtotime((string)$fecha_raw);
        if (!$ts) return '---';
        return $meses[(int)date('m',$ts)] . ' DE ' . date('Y',$ts);
    }

    public function list_ventas(): array {
        $t_ventas = $this->t_ventas();
        $t_emp = $this->t_empresas();
        $t_fact = $this->t_facturas();

        $sql = "
        SELECT v.id, v.total_bs, v.fecha_venta, c.nombre_legal, f.nro_comprobante, f.tipo_documento
        FROM {$t_ventas} v
        JOIN {$t_emp} c ON v.cliente_id=c.id
        LEFT JOIN {$t_fact} f ON v.id=f.venta_id
        ORDER BY v.id DESC
        ";

        $rows = $this->wpdb->get_results($sql);
        return is_array($rows) ? $rows : [];
    }

    public function list_docs(): array {
        $t_ventas = $this->t_ventas();
        $t_emp = $this->t_empresas();
        $t_fact = $this->t_facturas();

        $sql = "
        SELECT f.*, v.fecha_venta, c.nombre_legal
        FROM {$t_fact} f
        JOIN {$t_ventas} v ON f.venta_id=v.id
        JOIN {$t_emp} c ON v.cliente_id=c.id
        ORDER BY f.id DESC
        ";

        $rows = $this->wpdb->get_results($sql);
        return is_array($rows) ? $rows : [];
    }

    public function venta_existe(int $venta_id): bool {
        $t_ventas = $this->t_ventas();
        $id = $this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$t_ventas} WHERE id=%d", $venta_id));
        return !empty($id);
    }

    public function venta_tiene_doc(int $venta_id): bool {
        $t_fact = $this->t_facturas();
        $id = $this->wpdb->get_var($this->wpdb->prepare("SELECT id FROM {$t_fact} WHERE venta_id=%d", $venta_id));
        return !empty($id);
    }

    public function factura_duplicada_en_anio(string $nro, string $fec): bool {
        $t_fact = $this->t_facturas();
        $anio = (int) date('Y', strtotime($fec));
        $dup = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$t_fact} WHERE tipo_documento='Factura' AND nro_comprobante=%s AND YEAR(fecha_emision)=%d LIMIT 1",
            $nro, $anio
        ));
        return !empty($dup);
    }

    public function insert_doc(int $venta_id, string $tipo, string $nro, string $fec, float $mon): array {
        $t_fact = $this->t_facturas();

        // Impuestos (Factura)
        $iva = 0.00; $it = 0.00; $neto = round($mon,2);
        if ($tipo === 'Factura') {
            $iva = round($mon * 0.13, 2);
            $it = round($mon * 0.03, 2);
            $neto = round($mon - $iva - $it, 2);
        }

        $cols = $this->table_columns($t_fact);
        $data = [
            'venta_id' => $venta_id,
            'tipo_documento' => $tipo,
            'nro_comprobante' => $nro,
            'monto_total' => round($mon,2),
            'fecha_emision' => $fec,
        ];
        if (isset($cols['impuesto_iva'])) $data['impuesto_iva'] = $iva;
        if (isset($cols['impuesto_it'])) $data['impuesto_it'] = $it;
        if (isset($cols['monto_neto'])) $data['monto_neto'] = $neto;

        $ok = $this->wpdb->insert($t_fact, $data);
        if ($ok === false) {
            return ['__error' => 'Error DB: ' . $this->wpdb->last_error];
        }
        return ['msg' => 'Documento asignado'];
    }

    public function delete_doc(int $doc_id): array {
        $t_fact = $this->t_facturas();
        $ok = $this->wpdb->delete($t_fact, ['id' => $doc_id], ['%d']);
        if ($ok === false) {
            return ['__error' => 'Error DB: ' . $this->wpdb->last_error];
        }
        return [];
    }
}
