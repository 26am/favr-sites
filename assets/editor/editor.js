/**
 * Favr Sites: the News post editor for site Editors (loaded only for them).
 *
 * Starts the editor without WordPress's "Welcome to the editor" tour and with one press to
 * publish. These are defaults, not saved choices: an Editor who switches either back on in the
 * editor's own Options menu keeps it.
 */
( function ( wp ) {
	'use strict';

	var defaults = ( window.favrSitesBlockEditor || {} ).defaults || {};

	if ( ! wp || ! wp.data || 'function' !== typeof wp.data.dispatch ) {
		return;
	}

	function apply() {
		var preferences = wp.data.dispatch( 'core/preferences' );
		if ( ! preferences || 'function' !== typeof preferences.setDefaults ) {
			return;
		}
		Object.keys( defaults ).forEach( function ( scope ) {
			preferences.setDefaults( scope, defaults[ scope ] );
		} );
	}

	// Now, and again once the editor has set its own defaults (they would replace ours).
	apply();
	if ( 'function' === typeof wp.domReady ) {
		wp.domReady( function () {
			Promise.resolve( window._wpLoadBlockEditor ).then( apply, apply );
		} );
	}
}( window.wp ) );
