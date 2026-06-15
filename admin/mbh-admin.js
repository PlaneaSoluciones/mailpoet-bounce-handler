/* global mbhAdmin, jQuery */
(function ($) {
	'use strict';

	$(function () {

		// ── Probar conexión ──────────────────────────────────────────────────
		$('#mbh-test-connection').on('click', function () {
			var $btn    = $(this);
			var $result = $('#mbh-connection-result');

			$btn.prop('disabled', true).text(mbhAdmin.testingLabel);
			$result.text('').css('color', '');

			$.post(mbhAdmin.ajaxUrl, {
				action:   'mbh_test_connection',
				nonce:    mbhAdmin.nonce,
				host:     $('#mbh_imap_host').val(),
				port:     $('#mbh_imap_port').val(),
				ssl:      $('input[name="mbh_imap_ssl"]').is(':checked') ? 1 : 0,
				protocol: $('input[name="mbh_imap_protocol"]:checked').val(),
				user:     $('#mbh_imap_user').val(),
				pass:     $('#mbh_imap_pass').val()
			}, function (response) {
				if (response.success) {
					$result.css('color', '#00a32a').text('✓ ' + response.data);
				} else {
					$result.css('color', '#d63638').text('✗ ' + response.data);
				}
			}).fail(function () {
				$result.css('color', '#d63638').text('Error de conexión con el servidor.');
			}).always(function () {
				$btn.prop('disabled', false).text($btn.data('label-original') || 'Probar conexión');
			});
		});

		// ── Procesar ahora ───────────────────────────────────────────────────
		$('#mbh-process-now').on('click', function () {
			var $btn    = $(this);
			var $result = $('#mbh-process-result');

			$btn.prop('disabled', true).text(mbhAdmin.processingLabel);
			$result.text('').css('color', '');

			$.post(mbhAdmin.ajaxUrl, {
				action: 'mbh_process_now',
				nonce:  mbhAdmin.nonce
			}, function (response) {
				if (response.success) {
					$result.css('color', '#00a32a').text('✓ ' + response.data);
				} else {
					$result.css('color', '#d63638').text('✗ ' + response.data);
				}
			}).fail(function () {
				$result.css('color', '#d63638').text('Error de conexión con el servidor.');
			}).always(function () {
				$btn.prop('disabled', false).text('Procesar ahora');
			});
		});

	});
}(jQuery));
