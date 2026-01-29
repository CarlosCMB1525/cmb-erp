<?php
if (!defined('ABSPATH')) { exit; }
$rows = $data['rows'] ?? [];

if (empty($rows)) {
    echo '<tr><td colspan="8" class="cmb-erp-text-muted" style="text-align:center;padding:18px;">Sin resultados.</td></tr>';
    return;
}

foreach ($rows as $r) {
    $has_factura = ((int)($r->has_factura ?? 0)) === 1;
    $has_recibo  = ((int)($r->has_recibo ?? 0)) === 1;
    $doc_num = trim((string)($r->nro_comprobante ?? ''));

    if ($has_factura) {
        $doc_label = 'FACT: ' . ($doc_num !== '' ? $doc_num : '---');
        $doc_class = 'cmb-erp-badge cmb-erp-badge--success';
    } elseif ($has_recibo) {
        $doc_label = 'RECIBO';
        $doc_class = 'cmb-erp-badge cmb-erp-badge--warn';
    } else {
        $doc_label = 'SIN DOC';
        $doc_class = 'cmb-erp-badge';
    }

    $estado_pago = (string)($r->estado_pago ?? 'PENDIENTE');
    if ($estado_pago === 'PAGADO') $pay_class = 'cmb-erp-badge cmb-erp-badge--success';
    elseif ($estado_pago === 'PAGO PARCIAL') $pay_class = 'cmb-erp-badge cmb-erp-badge--warn';
    else $pay_class = 'cmb-erp-badge cmb-erp-badge--danger';

    $periodo = !empty($r->mes_correspondiente) ? $r->mes_correspondiente : strtoupper(date('F \d\e Y', strtotime($r->fecha_venta)));

    $fv = date('d/m/Y', strtotime($r->fecha_venta));
    $ff = $r->ff ? date('d/m/Y', strtotime($r->ff)) : '--';
    $fp = $r->fp ? date('d/m/Y', strtotime($r->fp)) : '--';

    $total  = (float)($r->total_bs ?? 0);
    $pagado = (float)($r->total_pagado ?? 0);
    $saldo  = $total - $pagado;
    if ($saldo < 0) $saldo = 0;

    $impuestos = $has_factura ? ($total * 0.16) : 0;
    $neto = $total - $impuestos;

    $qQuick = strtolower(trim((string)($r->nombre_legal ?? '') . ' ' . (string)$doc_num));

    echo '<tr data-cmb-dash-quick="' . esc_attr($qQuick) . '">';
    echo '<td><strong>#' . esc_html((int)($r->id ?? 0)) . '</strong></td>';

    echo '<td>';
    echo '<div style="font-weight:900;">' . esc_html((string)($r->nombre_legal ?? '')) . '</div>';
    echo '<div class="cmb-erp-text-muted" style="font-size:12px;margin-top:4px;">NIT: ' . esc_html((string)($r->nit_id ?? '---')) . ' · Cat: ' . esc_html((string)($r->tipo_cliente ?? '---')) . '</div>';
    echo '</td>';

    echo '<td><span class="cmb-erp-badge">' . esc_html($periodo) . '</span></td>';
    echo '<td><span class="' . esc_attr($doc_class) . '">' . esc_html($doc_label) . '</span></td>';
    echo '<td><span class="' . esc_attr($pay_class) . '">' . esc_html($estado_pago) . '</span></td>';

    echo '<td class="cmb-erp-text-muted" style="font-size:12px;">';
    echo '<div><strong>Venta:</strong> ' . esc_html($fv) . '</div>';
    echo '<div><strong>Doc:</strong> ' . esc_html($ff) . '</div>';
    echo '<div><strong>Pago:</strong> ' . esc_html($fp) . '</div>';
    echo '</td>';

    echo '<td style="font-size:12px;">';
    echo '<div><strong>Total:</strong> ' . esc_html(number_format($total,2,'.','')) . ' Bs</div>';
    echo '<div><strong>Pagado:</strong> ' . esc_html(number_format($pagado,2,'.','')) . ' Bs</div>';
    echo '<div><strong>Saldo:</strong> ' . esc_html(number_format($saldo,2,'.','')) . ' Bs</div>';
    echo '</td>';

    echo '<td style="font-size:12px;">';
    echo '<div><strong>Neto:</strong> ' . esc_html(number_format($neto,2,'.','')) . ' Bs</div>';
    echo '<div class="cmb-erp-text-muted">Imp: ' . esc_html(number_format($impuestos,2,'.','')) . ' Bs</div>';
    echo '</td>';

    echo '</tr>';
}
