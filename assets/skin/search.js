/*
 * Favr search: the toolbar's magnifier (⌘K / Ctrl+K) finds an Editor's records.
 *
 * WordPress's command palette lists menu screens. This adds a loader that asks
 * favr-sites/v1/search for members, listings, events, documents, people, sponsors, pages, news
 * posts and invoices, and lists each match with its type; choosing one opens its edit screen.
 * It also takes away the loaders an Editor has no use for (Editors\Search::LOADERS_OFF).
 *
 * Plain JavaScript, no build step. Settings come from Editors\Search::settings().
 */
( function ( wp, settings ) {
	'use strict';

	if ( ! wp || ! wp.data || ! wp.element || ! wp.apiFetch || ! settings || ! wp.data.dispatch( 'core/commands' ) ) {
		return;
	}

	var el = wp.element;
	var STORE = 'core/commands';
	var LOADER = 'favr-sites/records';
	var WAIT = 250; // Milliseconds without typing before the site is asked.
	var PALETTE = '.commands-command-menu__container';
	var NOTHING = { term: '', results: [], loading: false, first: false };

	/* The type of a record, shown where WordPress shows "Edit" (search.css puts it there). */
	function chip( type ) {
		return el.createElement( 'span', { className: 'favr-search-type' }, type );
	}

	/* A record as a palette command. The palette keeps a row only while its words hold what is
	   typed, so a record the site matched some other way (a company, an e-mail, the words in
	   another order) carries the term it was found for as a keyword. */
	function command( result, index, term ) {
		var id = ( /[?&](?:post|invoice)=(\d+)/.exec( result.url ) || [] )[ 1 ] || String( index + 1 );
		var named = result.title.toLowerCase().indexOf( term.toLowerCase() ) > -1;
		return {
			name: 'favr-sites/record/' + result.url,
			label: result.title,
			searchLabel: result.title + ' ' + result.type + ' ' + id,
			keywords: named ? [] : [ term ],
			icon: chip( result.type ),
			category: 'edit',
			callback: function ( palette ) {
				document.location = result.url;
				palette.close();
			},
		};
	}

	/* The palette marks its first row as rows appear while typing, but leaves the mark where it was
	   when an answer arrives later: a better match would sit above the marked row, and Enter would
	   open the wrong one. Home is the palette's own key for "mark the first row". */
	function markFirst() {
		var root = document.querySelector( PALETTE + ' [cmdk-root]' );
		if ( root ) {
			root.dispatchEvent( new window.KeyboardEvent( 'keydown', { key: 'Home', bubbles: true, cancelable: true } ) );
		}
	}

	/* Is the mark still where the palette put it, on the first row? Once the Editor has moved it
	   (arrow keys, the pointer), it is theirs and stays where it is. */
	function markUntouched() {
		var marked = document.querySelector( PALETTE + ' [cmdk-item][aria-selected="true"]' );
		return ! marked || marked === document.querySelector( PALETTE + ' [cmdk-item]:not([aria-disabled="true"])' );
	}

	/* The site's answer, with only the rows that can be listed. */
	function rows( answer ) {
		var list = answer && Array.isArray( answer.results ) ? answer.results : [];
		return list.filter( function ( result ) {
			return result && 'string' === typeof result.title && 'string' === typeof result.type && 'string' === typeof result.url && '' !== result.url;
		} );
	}

	/* The loader: a hook the palette calls with what is typed. */
	function useRecords( props ) {
		var search = String( ( props && props.search ) || '' ).trim();
		var state = el.useState( NOTHING );
		var found = state[ 0 ];
		var setFound = state[ 1 ];

		el.useEffect(
			function () {
				if ( search.length < settings.min ) {
					setFound( NOTHING );
					return undefined;
				}
				var current = true;
				var abort = 'function' === typeof window.AbortController ? new window.AbortController() : null;
				// Keep the last answer on screen while the next one is fetched: no flicker.
				setFound( function ( before ) {
					return { term: before.term, results: before.results, loading: true, first: false };
				} );
				var timer = window.setTimeout( function () {
					wp.apiFetch( {
						path: settings.path + '?q=' + encodeURIComponent( search ),
						signal: abort ? abort.signal : undefined,
					} )
						.then( function ( answer ) {
							if ( current ) {
								setFound( { term: search, results: rows( answer ), loading: false, first: markUntouched() } );
							}
						} )
						.catch( function () {
							if ( current ) {
								setFound( { term: search, results: [], loading: false, first: false } );
							}
						} );
				}, WAIT );
				return function () {
					current = false;
					window.clearTimeout( timer );
					if ( abort ) {
						abort.abort();
					}
				};
			},
			[ search ]
		);

		// Once the palette has put a new answer in order, its best row is the marked one.
		el.useEffect(
			function () {
				if ( found.loading || ! found.first || ! found.results.length ) {
					return undefined;
				}
				var timer = window.setTimeout( markFirst, 30 );
				return function () {
					window.clearTimeout( timer );
				};
			},
			[ found.results, found.loading, found.first ]
		);

		var commands = el.useMemo(
			function () {
				return found.results.map( function ( result, index ) {
					return command( result, index, found.term );
				} );
			},
			[ found.results, found.term ]
		);

		return { isLoading: found.loading, commands: commands };
	}

	wp.data.dispatch( STORE ).registerCommandLoader( { name: LOADER, hook: useRecords } );

	/* WordPress registers its loaders once its palette has drawn, so the unwanted ones are taken
	   away as they appear. */
	var off = Array.isArray( settings.off ) ? settings.off : [];

	function prune() {
		var commands = wp.data.select( STORE );
		var loaders = commands.getCommandLoaders( false ).concat( commands.getCommandLoaders( true ) );
		loaders.forEach( function ( loader ) {
			if ( off.indexOf( loader.name ) > -1 ) {
				wp.data.dispatch( STORE ).unregisterCommandLoader( loader.name );
			}
		} );
	}

	if ( off.length ) {
		wp.data.subscribe( prune, STORE );
		prune();
	}
} )( window.wp, window.favrSitesSearch );
