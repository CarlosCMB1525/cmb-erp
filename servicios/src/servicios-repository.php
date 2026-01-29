<?php
namespace CMBERP\Modules\Servicios;
if (!defined('ABSPATH')) exit;
final class Repository {
    /** @var \wpdb */
    private $wpdb;
    public function __construct(?\wpdb $wpdb = null) {
        $this->wpdb = $wpdb ?: ($GLOBALS['wpdb'] ?? null);
        if (!$this->wpdb) throw new \RuntimeException('wpdb no disponible');
    }
    private function table(): string { return $this->wpdb->prefix . 'sv_servicios'; }
    public function list(int $limit = 200): array {
        $limit = max(1, min(500, (int)$limit));
        $t = $this->table();
        $rows = $this->wpdb->get_results("SELECT * FROM {$t} ORDER BY id DESC LIMIT {$limit}", ARRAY_A);
        return is_array($rows) ? $rows : [];
    }
    public function get(int $id): ?array {
        $id = (int)$id; if ($id<=0) return null;
        $t = $this->table();
        $row = $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM {$t} WHERE id=%d", $id), ARRAY_A);
        return is_array($row) ? $row : null;
    }
    public function search(string $q, int $limit = 200): array {
        $t = $this->table();
        $limit = max(1, min(500, (int)$limit));
        $q = trim((string)$q);
        if ($q==='') return $this->list($limit);
        $like = '%' . $this->wpdb->esc_like(strtoupper($q)) . '%';
        $sql = $this->wpdb->prepare(
            "SELECT * FROM {$t} WHERE nombre_servicio LIKE %s OR detalle_tecnico LIKE %s OR codigo_unico LIKE %s ORDER BY id DESC LIMIT {$limit}",
            $like, $like, $like
        );
        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        return is_array($rows) ? $rows : [];
    }
    private function normalize_tipo(string $t): string {
        $t = strtoupper(trim((string)$t));
        return in_array($t, ['UNICO','MENSUAL','ANUAL'], true) ? $t : 'UNICO';
    }
    private function safe_money($v): float {
        $n=(float)$v; if ($n<0) $n=0; return round($n,2);
    }
    private function upper($v): string {
        return strtoupper(trim(sanitize_text_field((string)$v)));
    }
    public function save(array $in): array {
        $t = $this->table();
        $id = (int)($in['id'] ?? 0);
        $nombre = trim(sanitize_text_field((string)($in['nombre_servicio'] ?? '')));
        $codigo = $this->upper($in['codigo_unico'] ?? '');
        $tipo = $this->normalize_tipo((string)($in['tipo_servicio'] ?? 'UNICO'));
        $detalle = trim(sanitize_textarea_field((string)($in['detalle_tecnico'] ?? '')));
        $precio = $this->safe_money($in['monto_unitario'] ?? 0);
        if ($nombre==='') return ['__error'=>'Nombre obligatorio.'];
        if ($codigo==='') return ['__error'=>'Código obligatorio.'];
        if ($precio<=0) return ['__error'=>'Precio debe ser mayor a 0.'];
        $dup = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT id FROM {$t} WHERE codigo_unico=%s AND id != %d LIMIT 1",
            $codigo, $id
        ));
        if ($dup) return ['__error'=>'⚠️ EL CÓDIGO ÚNICO YA EXISTE.'];
        $data=[
            'nombre_servicio'=>$nombre,
            'codigo_unico'=>$codigo,
            'tipo_servicio'=>$tipo,
            'detalle_tecnico'=>$detalle,
            'monto_unitario'=>$precio,
        ];
        $formats=['%s','%s','%s','%s','%f'];
        if ($id>0){
            $ok=$this->wpdb->update($t,$data,['id'=>$id],$formats,['%d']);
            if($ok===false) return ['__error'=>'DB: '.$this->wpdb->last_error];
            return ['row'=>$this->get($id),'msg'=>'Servicio actualizado.'];
        }
        $ok=$this->wpdb->insert($t,$data,$formats);
        if($ok===false) return ['__error'=>'DB: '.$this->wpdb->last_error];
        $new_id=(int)$this->wpdb->insert_id;
        return ['row'=>$this->get($new_id),'msg'=>'Servicio registrado.'];
    }
    public function delete(int $id): array {
        $id=(int)$id; if($id<=0) return ['__error'=>'ID inválido.'];
        $t=$this->table();
        $ok=$this->wpdb->delete($t,['id'=>$id],['%d']);
        if($ok===false) return ['__error'=>'DB: '.$this->wpdb->last_error];
        return ['id'=>$id,'msg'=>'Servicio eliminado.'];
    }
}
