/**
 * BD Toolkit — settings page (copy the login address).
 *
 * @package bd-toolkit
 */

( function() {
	// Scoped so this file doesn't set a global $ in wp-admin.
	var $ = jQuery;

	$( function() {
		$( '.bdtk-copy' ).on( 'click', function() {
			var $button = $( this );
			var text = $button.attr( 'data-copy' );

			var done = function() {
				$button.text( 'Copied' );
				wp.a11y.speak( 'Login address copied.' );
				setTimeout( function() {
					$button.text( 'Copy' );
				}, 2000 );
			};

			// The clipboard API only exists on HTTPS (and localhost); fall back to a prompt to copy from.
			if ( navigator.clipboard && window.isSecureContext ) {
				navigator.clipboard.writeText( text ).then( done );
			} else {
				window.prompt( 'Copy the login address:', text );
			}
		} );
	} );
} )();
