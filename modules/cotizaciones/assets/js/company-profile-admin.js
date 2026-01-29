/* global jQuery, wp */
(function ($) {
  'use strict';

  function initMediaPicker(inputId, pickBtnId, clearBtnId) {
    var $input = $(inputId);
    var $pick = $(pickBtnId);
    var $clear = $(clearBtnId);

    if (!$input.length || !$pick.length) return;

    var frame = null;

    $pick.on('click', function (e) {
      e.preventDefault();

      if (frame) {
        frame.open();
        return;
      }

      frame = wp.media({
        title: 'Seleccionar imagen',
        button: { text: 'Usar esta imagen' },
        library: { type: 'image' },
        multiple: false
      });

      frame.on('select', function () {
        var att = frame.state().get('selection').first().toJSON();
        if (att && att.url) {
          $input.val(att.url).trigger('change');
        }
      });

      frame.open();
    });

    if ($clear.length) {
      $clear.on('click', function (e) {
        e.preventDefault();
        $input.val('').trigger('change');
      });
    }
  }

  $(document).ready(function () {
    if (typeof wp === 'undefined' || !wp.media) return;

    initMediaPicker('#cmb_erp_logo_url', '#cmb_erp_pick_logo', '#cmb_erp_clear_logo');
    initMediaPicker('#cmb_erp_footer_image_url', '#cmb_erp_pick_footer_image', '#cmb_erp_clear_footer_image');
  });

})(jQuery);
