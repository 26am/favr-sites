/**
 * Favr Menus: reorder (drag or arrows), one dropdown level, rename, add, remove.
 *
 * Rows are a flat list with levels (like WordPress's own menu editor). A row's "block" is the row
 * plus the rows nested under it; blocks move together. On save the rows go to the server as JSON,
 * where FavrSites\Menus\Tree::plan() applies the same rules again.
 */
( function ( $ ) {
	'use strict';

	const i18n = ( window.favrMenus && window.favrMenus.i18n ) || {};
	const template = document.getElementById( 'favr-menu-row' );
	const dirty = new Set();

	const listOf = ( form ) => form.querySelector( '.favr-menu__list' );
	const rowsOf = ( list ) => Array.from( list.children ).filter( ( el ) => el.classList.contains( 'favr-menu-row' ) );
	const level = ( row ) => parseInt( row.dataset.level, 10 ) || 0;
	const titleOf = ( row ) => row.querySelector( '.favr-menu-row__title' ).value.trim();

	// Editors get one dropdown level; rows that were already deeper may stay where they are.
	const allowed = ( row ) => Math.max( 1, parseInt( row.dataset.deep, 10 ) || 0 );

	function setLevel( row, value ) {
		row.dataset.level = String( value );
		row.style.setProperty( '--level', String( value ) );
	}

	function block( row ) {
		const out = [ row ];
		let next = row.nextElementSibling;
		while ( next && level( next ) > level( row ) ) {
			out.push( next );
			next = next.nextElementSibling;
		}
		return out;
	}

	// The previous/next row at the same level under the same parent, or null.
	function sibling( row, dir ) {
		const own = level( row );
		let el = dir < 0 ? row.previousElementSibling : block( row ).pop().nextElementSibling;
		while ( el ) {
			if ( level( el ) < own ) {
				return null;
			}
			if ( level( el ) === own ) {
				return el;
			}
			el = dir < 0 ? el.previousElementSibling : el.nextElementSibling;
		}
		return null;
	}

	function placeAfter( anchor, rows ) {
		rows.forEach( ( el ) => {
			anchor.after( el );
			anchor = el;
		} );
	}

	function normalize( list ) {
		let prev = -1;
		rowsOf( list ).forEach( ( row ) => {
			const value = Math.max( 0, Math.min( level( row ), prev + 1, allowed( row ) ) );
			setLevel( row, value );
			prev = value;
		} );
	}

	function refresh( form ) {
		const list = listOf( form );
		normalize( list );
		const rows = rowsOf( list );
		rows.forEach( ( row, index ) => {
			const own = level( row );
			const hasChildren = !! row.nextElementSibling && level( row.nextElementSibling ) > own;
			const tool = ( act ) => row.querySelector( '[data-act="' + act + '"]' );
			tool( 'up' ).disabled = ! sibling( row, -1 );
			tool( 'down' ).disabled = ! sibling( row, 1 );
			tool( 'in' ).hidden = own > 0;
			tool( 'in' ).disabled = 0 === index || hasChildren;
			tool( 'out' ).hidden = 0 === own;
			row.querySelector( '[data-note="deep"]' ).hidden = own < 2;
			const name = titleOf( row );
			row.querySelectorAll( '[data-act]' ).forEach( ( btn ) => {
				const label = btn.dataset.label + ( name ? ': ' + name : '' );
				btn.setAttribute( 'aria-label', label );
				btn.title = btn.dataset.label;
			} );
		} );
		form.querySelector( '.favr-menu__empty' ).hidden = rows.length > 0;
	}

	function changed( form ) {
		dirty.add( form );
		form.querySelector( '.favr-menu__dirty' ).hidden = false;
		refresh( form );
	}

	function act( form, row, action ) {
		const list = listOf( form );
		const own = level( row );
		const rows = block( row );
		if ( 'up' === action ) {
			const prev = sibling( row, -1 );
			if ( prev ) {
				rows.forEach( ( el ) => list.insertBefore( el, prev ) );
			}
		} else if ( 'down' === action ) {
			const next = sibling( row, 1 );
			if ( next ) {
				placeAfter( block( next ).pop(), rows );
			}
		} else if ( 'in' === action ) {
			setLevel( row, own + 1 );
		} else if ( 'out' === action ) {
			// Leave the dropdown: go after the parent's last child, one level up, with any children.
			let parent = row.previousElementSibling;
			while ( parent && level( parent ) >= own ) {
				parent = parent.previousElementSibling;
			}
			if ( parent ) {
				const end = block( parent ).pop();
				if ( ! rows.includes( end ) ) {
					placeAfter( end, rows );
				}
			}
			rows.forEach( ( el ) => setLevel( el, level( el ) - 1 ) );
		} else if ( 'remove' === action ) {
			// Children move up a level instead of disappearing with their parent.
			const focusNext = rows[ 1 ] || row.nextElementSibling || row.previousElementSibling;
			rows.slice( 1 ).forEach( ( el ) => setLevel( el, level( el ) - 1 ) );
			row.remove();
			changed( form );
			( focusNext ? focusNext.querySelector( '.favr-menu-row__title' ) : form.querySelector( '[data-add="page"]' ) ).focus();
			return;
		}
		changed( form );
		const button = row.querySelector( '[data-act="' + action + '"]' );
		if ( button && ! button.disabled && ! button.hidden ) {
			button.focus();
		} else {
			row.querySelector( '.favr-menu-row__title' ).focus();
		}
	}

	function append( form, data ) {
		const row = template.content.firstElementChild.cloneNode( true );
		row.dataset.id = '0';
		row.dataset.type = data.type;
		row.dataset.objectId = String( data.objectId || 0 );
		row.dataset.url = data.url || '';
		const title = row.querySelector( '.favr-menu-row__title' );
		title.value = data.title;
		title.defaultValue = data.title;
		row.querySelector( '.favr-menu-row__kind' ).textContent = 'page' === data.type ? i18n.page : data.url;
		setLevel( row, 0 );
		listOf( form ).appendChild( row );
		changed( form );
	}

	// Mirrors FavrSites\Menus\Tree::cleanUrl(), plus two conveniences: "name@site.org" becomes a
	// mailto: link and "site.org/page" gets https://.
	function cleanUrl( raw ) {
		let url = raw.trim();
		if ( ! url ) {
			return '';
		}
		if ( url.startsWith( '/' ) ) {
			return ! url.startsWith( '//' ) && /^\/[^\s"'<>\\]*$/.test( url ) ? url : '';
		}
		if ( /^[^\s@/:]+@[^\s@/:]+\.[^\s@/:]+$/.test( url ) ) {
			url = 'mailto:' + url;
		} else if ( ! /^[a-z][a-z0-9+.-]*:/i.test( url ) && /^[^\s/:]+\.[^\s/:]+(\/\S*)?$/.test( url ) ) {
			url = 'https://' + url;
		}
		return /^(https?:\/\/\S+|mailto:\S+|tel:\S+)$/i.test( url ) ? url : '';
	}

	function showError( form, message ) {
		const el = form.querySelector( '.favr-menu__add-error' );
		el.textContent = message || '';
		el.hidden = ! message;
	}

	function addPage( form ) {
		const select = form.querySelector( '[data-add="page"]' );
		if ( ! select.value ) {
			showError( form, i18n.choosePage );
			return false;
		}
		append( form, { type: 'page', objectId: select.value, title: select.selectedOptions[ 0 ].textContent.trim() } );
		select.value = '';
		showError( form, '' );
		return true;
	}

	function addLink( form ) {
		const label = form.querySelector( '[data-add="label"]' );
		const address = form.querySelector( '[data-add="url"]' );
		const url = cleanUrl( address.value );
		if ( ! label.value.trim() ) {
			showError( form, i18n.needsLabel );
			label.focus();
			return false;
		}
		if ( ! url ) {
			showError( form, i18n.badUrl );
			address.focus();
			return false;
		}
		append( form, { type: 'custom', title: label.value.trim(), url } );
		label.value = '';
		address.value = '';
		showError( form, '' );
		return true;
	}

	function serialize( form ) {
		return rowsOf( listOf( form ) ).map( ( row ) => ( {
			id: parseInt( row.dataset.id, 10 ) || 0,
			level: level( row ),
			title: titleOf( row ),
			type: row.dataset.type,
			object_id: parseInt( row.dataset.objectId, 10 ) || 0,
			url: row.dataset.url || '',
		} ) );
	}

	function setup( form ) {
		const list = listOf( form );

		$( list ).sortable( {
			items: '> .favr-menu-row',
			handle: '.favr-menu-row__grip',
			axis: 'y',
			tolerance: 'pointer',
			placeholder: 'favr-menu-placeholder',
			forcePlaceholderSize: true,
			start( event, ui ) {
				// Children travel inside the dragged row, then land right after it.
				const row = ui.item[ 0 ];
				const carry = row.querySelector( '.favr-menu-row__carry' );
				block( row ).slice( 1 ).forEach( ( el ) => carry.appendChild( el ) );
				row.dataset.from = String( level( row ) );
				$( list ).sortable( 'refreshPositions' );
			},
			stop( event, ui ) {
				const row = ui.item[ 0 ];
				const children = Array.from( row.querySelector( '.favr-menu-row__carry' ).children );
				placeAfter( row, children );
				const from = parseInt( row.dataset.from, 10 ) || 0;
				const prev = row.previousElementSibling;
				const to = Math.min( from, prev ? level( prev ) + 1 : 0 );
				setLevel( row, to );
				children.forEach( ( el ) => setLevel( el, level( el ) + to - from ) );
				changed( form );
			},
		} );

		form.addEventListener( 'click', ( event ) => {
			const button = event.target.closest( '[data-act]' );
			if ( ! button || ! form.contains( button ) ) {
				return;
			}
			const action = button.dataset.act;
			if ( 'add-page' === action ) {
				addPage( form );
			} else if ( 'add-link' === action ) {
				addLink( form );
			} else {
				act( form, button.closest( '.favr-menu-row' ), action );
			}
		} );

		form.addEventListener( 'input', ( event ) => {
			if ( event.target.classList.contains( 'favr-menu-row__title' ) ) {
				changed( form );
			}
		} );

		// A link keeps its label: clearing it restores the last one.
		form.addEventListener( 'focusout', ( event ) => {
			const input = event.target;
			if ( input.classList.contains( 'favr-menu-row__title' ) && ! input.value.trim() && 'custom' === input.closest( '.favr-menu-row' ).dataset.type ) {
				input.value = input.defaultValue;
				refresh( form );
			}
		} );

		// Enter in the "Add a link" fields adds the link instead of saving the menu.
		form.addEventListener( 'keydown', ( event ) => {
			if ( 'Enter' === event.key && event.target.matches( '[data-add="label"], [data-add="url"]' ) ) {
				event.preventDefault();
				addLink( form );
			}
		} );

		form.addEventListener( 'submit', ( event ) => {
			// Something typed or chosen but not added yet is added now (or stops the save if it's wrong).
			const pending = [ 'label', 'url' ].some( ( key ) => form.querySelector( '[data-add="' + key + '"]' ).value.trim() );
			if ( ( pending && ! addLink( form ) ) || ( form.querySelector( '[data-add="page"]' ).value && ! addPage( form ) ) ) {
				event.preventDefault();
				return;
			}
			form.querySelector( '[name="rows"]' ).value = JSON.stringify( serialize( form ) );
			dirty.delete( form );
		} );

		refresh( form );
	}

	const forms = Array.from( document.querySelectorAll( '.favr-menu__form' ) );
	forms.forEach( setup );

	document.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '[data-act="tray-add"]' );
		if ( ! button ) {
			return;
		}
		const form = forms.find( ( el ) => el.dataset.slot === button.dataset.slot );
		const item = button.closest( 'li' );
		if ( ! form || ! item ) {
			return;
		}
		append( form, { type: 'page', objectId: item.dataset.id, title: item.dataset.title } );
		const tray = item.closest( '.favr-menus__tray' );
		item.remove();
		if ( ! tray.querySelector( 'li' ) ) {
			tray.hidden = true;
		}
	} );

	window.addEventListener( 'beforeunload', ( event ) => {
		if ( dirty.size ) {
			event.preventDefault();
			event.returnValue = i18n.unsaved || '';
		}
	} );
}( window.jQuery ) );
