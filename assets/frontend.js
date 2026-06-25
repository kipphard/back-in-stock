/* Wieder verfügbar – Frontend JS */
( function () {
	'use strict';

	var data = window.wvbData || {};

	/**
	 * Zeigt eine Status-Meldung im Formular-Container an.
	 *
	 * @param {HTMLElement} wrap    Formular-Wrapper.
	 * @param {string}      message Anzuzeigender Text.
	 * @param {boolean}     success True = Erfolg, false = Fehler.
	 */
	function showMessage( wrap, message, success ) {
		var msg = wrap.querySelector( '.wvb-message' );
		if ( ! msg ) {
			return;
		}
		msg.textContent  = message;
		msg.className    = 'wvb-message ' + ( success ? 'wvb-success' : 'wvb-error' );
		msg.style.display = 'block';
	}

	/**
	 * Verarbeitet den Formular-Submit via AJAX.
	 *
	 * @param {Event} e Submit-Event.
	 */
	function handleSubmit( e ) {
		e.preventDefault();

		var form      = e.target;
		var wrap      = form.closest( '.wvb-notify-wrap' );
		var submitBtn = form.querySelector( '.wvb-submit' );

		var productId = form.querySelector( '[name="product_id"]' );
		var emailEl   = form.querySelector( '[name="email"]' );
		var consentEl = form.querySelector( '[name="consent"]' );

		if ( ! productId || ! emailEl ) {
			return;
		}

		if ( submitBtn ) {
			submitBtn.disabled = true;
		}

		var params = 'action=wvb_subscribe'
			+ '&nonce=' + encodeURIComponent( data.nonce || '' )
			+ '&product_id=' + encodeURIComponent( productId.value )
			+ '&email=' + encodeURIComponent( emailEl.value )
			+ '&consent=' + ( consentEl && consentEl.checked ? '1' : '' );

		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', data.ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded' );
		xhr.onreadystatechange = function () {
			if ( xhr.readyState !== 4 ) {
				return;
			}

			if ( submitBtn ) {
				submitBtn.disabled = false;
			}

			try {
				var result = JSON.parse( xhr.responseText );
				if ( result.success ) {
					showMessage( wrap, result.data.message || ( data.i18n && data.i18n.success ) || '', true );
					form.style.display = 'none';
				} else {
					var errMsg = ( result.data && result.data.message )
						? result.data.message
						: ( data.i18n && data.i18n.error ) || 'Fehler';
					showMessage( wrap, errMsg, false );
				}
			} catch ( ex ) {
				showMessage( wrap, ( data.i18n && data.i18n.error ) || 'Fehler', false );
			}
		};
		xhr.send( params );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		var forms = document.querySelectorAll( '.wvb-form' );
		forms.forEach( function ( form ) {
			form.addEventListener( 'submit', handleSubmit );
		} );
	} );
}() );
