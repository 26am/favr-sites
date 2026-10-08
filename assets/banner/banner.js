( function () {
	var el = document.querySelector( '[data-favr-banner]' );
	if ( ! el ) {
		return;
	}
	if ( el.hasAttribute( 'data-late' ) && document.body ) {
		document.body.insertBefore( el, document.body.firstChild );
	}
	var key = 'favrBanner';
	var version = el.getAttribute( 'data-version' );
	var from = parseInt( el.getAttribute( 'data-from' ), 10 );
	var until = parseInt( el.getAttribute( 'data-until' ), 10 );
	var now = Date.now() / 1000;
	var closed = false;
	try {
		closed = window.localStorage.getItem( key ) === version;
	} catch ( e ) {}
	el.hidden = closed || ( ! isNaN( from ) && now < from ) || ( ! isNaN( until ) && now >= until );
	var button = el.querySelector( '.favr-banner__close' );
	if ( button ) {
		button.hidden = false;
		button.addEventListener( 'click', function () {
			el.hidden = true;
			try {
				window.localStorage.setItem( key, version );
			} catch ( e ) {}
		} );
	}
} )();
