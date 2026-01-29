<?php
/** @var array $view */
if (!defined('ABSPATH')) { exit; }
?>
<div class="cmb-erp-root" id="cmb_sales_root">
  <div class="cmb-erp-card">
    <?php include __DIR__ . '/partials/header.php'; ?>

    <input type="hidden" id="s_id" value="0" />
    <input type="hidden" id="s_quote_id" value="0" />
    <input type="hidden" id="s_quote_code" value="" />

    <?php include __DIR__ . '/partials/sale-form.php'; ?>

    <div id="cmb_sales_msg" class="cmb-erp-text-muted" style="margin-top:12px;font-weight:900;"></div>

    <?php include __DIR__ . '/partials/items-table.php'; ?>

    <?php include __DIR__ . '/partials/history.php'; ?>
  </div>

  <?php include __DIR__ . '/modals/clients-modal.php'; ?>
  <?php include __DIR__ . '/modals/services-modal.php'; ?>
  <?php include __DIR__ . '/modals/quotes-modal.php'; ?>
  <?php include __DIR__ . '/modals/clone-modal.php'; ?>
  <?php include __DIR__ . '/modals/recurrence-modal.php'; ?>
</div>
