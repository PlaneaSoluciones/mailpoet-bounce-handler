/* global mbhAdmin, jQuery */
(function ($) {
	'use strict';

	$(
		function () {

			// ── Probar conexión ──────────────────────────────────────────────────
			$( '#mbh-test-connection' ).on(
				'click',
				function () {
					var $btn    = $( this );
					var $result = $( '#mbh-connection-result' );

					$btn.prop( 'disabled', true ).text( mbhAdmin.testingLabel );
					$result.text( '' ).css( 'color', '' );

					$.post(
						mbhAdmin.ajaxUrl,
						{
							action:   'mbh_test_connection',
							nonce:    mbhAdmin.nonce,
							host:     $( '#mbh_imap_host' ).val(),
							port:     $( '#mbh_imap_port' ).val(),
							ssl:      $( 'input[name="mbh_imap_ssl"]' ).is( ':checked' ) ? 1 : 0,
							protocol: $( 'input[name="mbh_imap_protocol"]:checked' ).val(),
							user:     $( '#mbh_imap_user' ).val(),
							pass:     $( '#mbh_imap_pass' ).val()
						},
						function (response) {
							if (response.success) {
								$result.css( 'color', '#00a32a' ).text( '✓ ' + response.data );
							} else {
								$result.css( 'color', '#d63638' ).text( '✗ ' + response.data );
							}
						}
					).fail(
						function () {
							$result.css( 'color', '#d63638' ).text( 'Error de conexión con el servidor.' );
						}
					).always(
						function () {
							$btn.prop( 'disabled', false ).text( $btn.data( 'label-original' ) || 'Probar conexión' );
						}
					);
				}
			);

			// ── Procesar ahora ───────────────────────────────────────────────────
			$( '#mbh-process-now' ).on(
				'click',
				function () {
					var $btn    = $( this );
					var $result = $( '#mbh-process-result' );

					$btn.prop( 'disabled', true ).text( mbhAdmin.processingLabel );
					$result.text( '' ).css( 'color', '' );

					$.post(
						mbhAdmin.ajaxUrl,
						{
							action: 'mbh_process_now',
							nonce:  mbhAdmin.nonce
						},
						function (response) {
							if (response.success) {
								$result.css( 'color', '#00a32a' ).text( '✓ ' + response.data );
							} else {
								$result.css( 'color', '#d63638' ).text( '✗ ' + response.data );
							}
						}
					).fail(
						function () {
							$result.css( 'color', '#d63638' ).text( 'Error de conexión con el servidor.' );
						}
					).always(
						function () {
							$btn.prop( 'disabled', false ).text( 'Procesar ahora' );
						}
					);
				}
			);

			// ── Kebab menu — abrir/cerrar ───────────────────────────────────────
			$( document ).on(
				'click',
				'.mbh-actions-toggle',
				function ( e ) {
					e.stopPropagation();
					var $toggle   = $( this );
					var $dropdown = $toggle.next( '.mbh-actions-dropdown' );
					var isOpen    = ! $dropdown.attr( 'hidden' );

					// Cerrar todos los menús abiertos.
					$( '.mbh-actions-dropdown' ).attr( 'hidden', true );
					$( '.mbh-actions-toggle' ).attr( 'aria-expanded', 'false' );

					if ( ! isOpen ) {
						$dropdown.removeAttr( 'hidden' );
						$toggle.attr( 'aria-expanded', 'true' );
					}
				}
			);

			$( document ).on(
				'click',
				function () {
					$( '.mbh-actions-dropdown' ).attr( 'hidden', true );
					$( '.mbh-actions-toggle' ).attr( 'aria-expanded', 'false' );
				}
			);

			// ── Acciones de suscriptor en el log ────────────────────────────────
			$( document ).on(
				'click',
				'.mbh-action-btn',
				function ( e ) {
					e.stopPropagation();
					var $btn       = $( this );
					var email      = $btn.data( 'email' );
					var actionType = $btn.data( 'action-type' );
					var nonce      = $btn.data( 'nonce' );
					var $dropdown  = $btn.closest( '.mbh-actions-dropdown' );
					var $toggle    = $dropdown.prev( '.mbh-actions-toggle' );

					$btn.prop( 'disabled', true );

					$.post(
						mbhAdmin.ajaxUrl,
						{
							action:      'mbh_change_subscriber_status',
							nonce:       nonce,
							email:       email,
							action_type: actionType
						},
						function ( response ) {
							if ( response.success ) {
								var inverseType  = ( 'reactivate' === actionType ) ? 'bounce' : 'reactivate';
								var newLabel     = ( 'reactivate' === inverseType ) ? 'Reactivar' : 'Marcar rebotado';
								var newClass     = 'mbh-dropdown-item mbh-action-btn'
									+ ( 'reactivate' === inverseType ? '' : ' is-destructive' );
								var safeEmail    = $( '<span>' ).text( email ).html();

								$btn
									.removeClass( 'is-destructive' )
									.addClass( 'reactivate' === inverseType ? '' : 'is-destructive' )
									.data( 'action-type', inverseType )
									.attr( 'class', newClass )
									.text( newLabel )
									.attr( 'data-action-type', inverseType )
									.attr( 'data-email', safeEmail )
									.prop( 'disabled', false );

								$dropdown.attr( 'hidden', true );
								$toggle.attr( 'aria-expanded', 'false' );
							} else {
								alert( response.data || 'Error al actualizar el estado.' );
								$btn.prop( 'disabled', false );
							}
						}
					).fail(
						function () {
							alert( 'Error de conexión con el servidor.' );
							$btn.prop( 'disabled', false );
						}
					);
				}
			);

		}
	);
}(jQuery));
