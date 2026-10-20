/* The credit line takes the background of the footer's bottom row, and muted text that still reads on it. */
( function () {
	var credit = document.querySelector( '[data-favr-credit]' );
	if ( ! credit || ! window.getComputedStyle ) {
		return;
	}
	function parse( value ) {
		var m = /rgba?\(([^)]+)\)/.exec( value || '' );
		var parts = m ? m[ 1 ].split( ',' ).map( parseFloat ) : null;
		return parts && ( parts.length < 4 || parts[ 3 ] >= 0.5 ) ? parts.slice( 0, 3 ) : null;
	}
	function solid( el ) {
		return parse( window.getComputedStyle( el ).backgroundColor );
	}
	function isFooter( el ) {
		return !! el && ( 'FOOTER' === el.tagName || ( el.matches && el.matches( '[data-elementor-type="footer"]' ) ) );
	}
	// A row out of sight or out of the flow (hidden at this width, a floating back-to-top button) is not the bottom row.
	function counts( el ) {
		if ( /^(SCRIPT|STYLE|TEMPLATE|LINK|NOSCRIPT)$/.test( el.tagName ) ) {
			return false;
		}
		var s = window.getComputedStyle( el );
		if ( 'none' === s.display || 'hidden' === s.visibility || 'fixed' === s.position || 'absolute' === s.position ) {
			return false;
		}
		var box = el.getBoundingClientRect();
		return box.width > 0 && box.height > 0;
	}
	// Layout rows only: a widget, button, link or form has a colour of its own, not the row's.
	var LEAF = '.elementor-widget, a, button, input, select, textarea, form, nav, ul, ol, table, img, svg, picture, video, iframe';

	var footer = credit.previousElementSibling;
	if ( ! isFooter( footer ) ) {
		var all = document.querySelectorAll( 'footer, [data-elementor-type="footer"]' );
		footer = all.length ? all[ all.length - 1 ] : null;
	}
	var row = null;
	if ( footer ) {
		var width = footer.getBoundingClientRect().width;
		var el = footer;
		row = solid( footer );
		for ( var depth = 0; depth < 12; depth++ ) {
			var child = el.lastElementChild;
			while ( child && ! counts( child ) ) {
				child = child.previousElementSibling;
			}
			if ( ! child || ( child.matches && child.matches( LEAF ) ) || child.getBoundingClientRect().width < width * 0.6 ) {
				break;
			}
			el = child;
			row = solid( el ) || row;
		}
	}
	var under = row || solid( document.body ) || solid( document.documentElement ) || [ 255, 255, 255 ];

	function channel( v ) {
		v = v / 255;
		return v <= 0.03928 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
	}
	function luminance( rgb ) {
		return 0.2126 * channel( rgb[ 0 ] ) + 0.7152 * channel( rgb[ 1 ] ) + 0.0722 * channel( rgb[ 2 ] );
	}
	function ratio( a, b ) {
		var x = luminance( a ) + 0.05;
		var y = luminance( b ) + 0.05;
		return x > y ? x / y : y / x;
	}
	// White or black, whichever reads better (black, not near-black, so some colour always reaches 4.5:1),
	// then toned towards the background as far as WCAG AA for small text (4.5:1) allows.
	var target = ratio( [ 255, 255, 255 ], under ) >= ratio( [ 0, 0, 0 ], under ) ? [ 255, 255, 255 ] : [ 0, 0, 0 ];
	var text = target;
	for ( var t = 0.7; t < 1; t += 0.02 ) {
		var mixed = [ 0, 1, 2 ].map( function ( i ) {
			return Math.round( under[ i ] + ( target[ i ] - under[ i ] ) * t );
		} );
		if ( ratio( mixed, under ) >= 4.5 ) {
			text = mixed;
			break;
		}
	}
	if ( row ) {
		credit.style.backgroundColor = 'rgb(' + row.join( ',' ) + ')';
	}
	credit.style.color = 'rgb(' + text.join( ',' ) + ')';
} )();
