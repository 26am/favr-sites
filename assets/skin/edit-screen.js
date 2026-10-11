/* Favr Sites — an Editor's classic edit screen (post.php, post-new.php). Three jobs:
   1. Ask before leaving with changes that are not saved. WordPress only watches the title and the
      big text editor; nearly everything else on these screens lives in the field panel.
   2. Box titles are titles: a click does not fold the box away and a drag does not move it.
   3. A dot on every field-panel tab that has something filled in (Directory draws its own). */
( function ( $, window, document ) {
	'use strict';

	var l10n = window.favrEditScreenL10n || {};
	var form = document.getElementById( 'post' );

	/* ------------------------------------------------- 1. Unsaved changes */

	// The form as it was when the Editor first touched the screen; null until then.
	var baseline = null;
	// Saving or moving to the trash: no question asked.
	var leaving = false;

	// WordPress asks about these itself, and saves them on its own while a draft is being written.
	var CORE = { post_title: true, content: true, excerpt: true };
	// Bookkeeping that WordPress rewrites behind the Editor's back.
	var VOLATILE = /nonce|^_wp_http_referer$|^_wp_original_http_referer$|^referredby$|^wp-preview$|^active_post_lock$|^auto_draft$|^favr_back$/;

	function coreGuards() {
		return !! ( window.wp && window.wp.autosave && window.wp.autosave.server );
	}

	function snapshot() {
		var core = coreGuards();
		var $fields = $( form ).find( ':input[name]' ).filter( function () {
			return ! VOLATILE.test( this.name ) && ! ( core && CORE[ this.name ] );
		} );
		var files = $( form ).find( 'input[type=file]' ).map( function () {
			return this.value;
		} ).get().join( '|' );
		return $fields.serialize() + '#' + files;
	}

	// A text editor the Editor typed in (its box is only written back to the form on save).
	function editorsChanged() {
		var core = coreGuards();
		var changed = false;
		if ( ! window.tinymce || ! window.tinymce.editors ) {
			return false;
		}
		$.each( window.tinymce.editors, function ( i, editor ) {
			var el = editor && editor.getElement ? editor.getElement() : null;
			if ( ! el || ! form.contains( el ) || ( core && CORE[ el.name ] ) ) {
				return;
			}
			if ( ! editor.isHidden() && editor.isDirty() ) {
				changed = true;
				return false;
			}
		} );
		return changed;
	}

	function isChanged() {
		if ( ! form || leaving ) {
			return false;
		}
		return ( null !== baseline && snapshot() !== baseline ) || editorsChanged();
	}

	// Only once the screen has finished setting itself up: fields a script fills in or switches
	// off while the page loads are not the Editor's changes.
	var settled = false;

	function arm() {
		if ( form && settled && null === baseline ) {
			baseline = snapshot();
		}
	}

	function initGuard() {
		if ( ! form ) {
			return;
		}

		// Every one of these comes before the change it leads to.
		$.each( [ 'pointerdown', 'mousedown', 'touchstart', 'keydown', 'paste', 'drop' ], function ( i, type ) {
			document.addEventListener( type, arm, true );
		} );
		$( function () {
			window.setTimeout( function () {
				settled = true;
			}, 0 );
		} );
		// Typing inside a text editor happens in its own frame.
		$( document ).on( 'tinymce-editor-init.favr-edit', function ( event, editor ) {
			if ( editor && editor.on ) {
				editor.on( 'focus keydown mousedown', arm );
			}
		} );

		// Update, Publish, Save draft. Preview submits to another tab and this screen stays open.
		$( form ).on( 'submit.favr-edit', function ( event ) {
			var target = form.getAttribute( 'target' );
			if ( target && '_self' !== target ) {
				return;
			}
			leaving = true;
			window.setTimeout( function () {
				// A required field stopped the save: the changes are still only here.
				if ( event.isDefaultPrevented() ) {
					leaving = false;
				}
			}, 0 );
		} );

		// Move to Trash (and "Delete permanently" on media, unless its own question was answered no).
		$( document ).on( 'click.favr-edit', '#delete-action a, #poststuff a.submitdelete', function ( event ) {
			if ( event.isDefaultPrevented() || event.metaKey || event.ctrlKey || event.shiftKey || event.which > 1 ) {
				return;
			}
			leaving = true;
			$( window ).off( 'beforeunload.edit-post' );
		} );

		window.addEventListener( 'beforeunload', function ( event ) {
			if ( ! isChanged() ) {
				return undefined;
			}
			// Browsers show their own wording; some only ask when given a sentence at all.
			var message = l10n.leave || 'You have changes that are not saved yet.';
			event.preventDefault();
			event.returnValue = message;
			return message;
		} );

		// Back from a failed save with the screen as it was: the changes are unsaved again.
		window.addEventListener( 'pageshow', function ( event ) {
			if ( event.persisted ) {
				leaving = false;
			}
		} );
	}

	/* --------------------------------------------------- 2. Calm box titles */

	function calmBoxes() {
		var $boxes = $( '.postbox' );
		$boxes.find( '.hndle, .handlediv, .handle-order-higher, .handle-order-lower' ).off( 'click.postboxes' );
		$boxes.filter( '.closed' ).removeClass( 'closed' ).find( '.handlediv' ).attr( 'aria-expanded', 'true' );
		if ( $.fn.sortable ) {
			// WordPress switches dragging back on whenever the window is wide enough, so "off" alone
			// does not last: a handle that matches nothing leaves nothing to drag by.
			$( '.meta-box-sortables.ui-sortable' ).sortable( 'option', 'handle', '.favr-no-drag-handle' ).sortable( 'disable' );
		}
	}

	/* ------------------------------------------------------- 3. Tab dots */

	// What a box holds; a text editor only writes its text back to its box on save.
	function textOf( el ) {
		var editor = 'TEXTAREA' === el.nodeName && el.id && window.tinymce ? window.tinymce.get( el.id ) : null;
		var text = editor && editor.initialized && ! editor.isHidden() ? editor.getContent( { format: 'text' } ) : el.value;
		return $.trim( text || '' );
	}

	function fieldFilled( $wrap ) {
		// A switch or a single choice always has an answer; it says nothing about content.
		if ( $wrap.hasClass( 'favr-field--toggle' ) || $wrap.hasClass( 'favr-field--radio' ) ) {
			return false;
		}
		if ( $wrap.hasClass( 'favr-field--hours' ) ) {
			return ! $wrap.find( '.favr-hours' ).hasClass( 'is-empty' );
		}
		if ( $wrap.hasClass( 'favr-field--checkboxes' ) ) {
			return $wrap.find( 'input:checked' ).length > 0;
		}
		var filled = false;
		$wrap.find( 'input, textarea, select' ).not( '[type=checkbox], [type=radio], [type=button], [type=submit]' ).each( function () {
			var value = textOf( this );
			if ( $( this ).closest( '.favr-repeater__template' ).length || '' === value ) {
				return;
			}
			// A list's first entry is its "nothing chosen"; an image field holds 0 when it is empty.
			if ( ( 'SELECT' === this.nodeName && this.selectedIndex < 1 ) || ( 'hidden' === this.type && '0' === value ) ) {
				return;
			}
			filled = true;
			return false;
		} );
		return filled;
	}

	function paneFilled( pane ) {
		var filled = false;
		$( pane ).find( '.favr-field' ).each( function () {
			if ( fieldFilled( $( this ) ) ) {
				filled = true;
				return false;
			}
		} );
		if ( filled ) {
			return true;
		}
		// WordPress's own boxes shown inside a pane (an event's description and short summary).
		$( pane ).find( 'textarea' ).each( function () {
			if ( ! $( this ).closest( '.favr-field' ).length && '' !== textOf( this ) ) {
				filled = true;
				return false;
			}
		} );
		return filled;
	}

	function updateDots() {
		$( '.favr-panel .favr-tab' ).each( function () {
			var pane = document.getElementById( this.getAttribute( 'aria-controls' ) || '' );
			if ( ! pane ) {
				return;
			}
			// A panel that draws its own tabs (Members) has no place for the dot yet.
			if ( ! $( this ).children( '.favr-tab__dot' ).length ) {
				$( this ).append( '<span class="favr-tab__dot" aria-hidden="true"></span>' );
			}
			$( this ).toggleClass( 'has-content', paneFilled( pane ) );
		} );
	}

	function initDots() {
		// Directory's own script sets the dots together with its "listing health" meter.
		if ( ! $( '.favr-panel .favr-tab' ).length || $( '.favr-health' ).length ) {
			return;
		}
		var timer;
		var later = function () {
			window.clearTimeout( timer );
			timer = window.setTimeout( updateDots, 250 );
		};
		var watch = function ( editor ) {
			if ( editor && editor.on ) {
				editor.on( 'input change keyup', later );
			}
		};
		updateDots();
		$( '.favr-panel' ).on( 'input.favr-edit change.favr-edit click.favr-edit', later );
		// Text editors: those already there, and those still starting up.
		if ( window.tinymce && window.tinymce.editors ) {
			$.each( window.tinymce.editors, function ( i, editor ) {
				watch( editor );
			} );
		}
		$( document ).on( 'tinymce-editor-init.favr-dots', function ( event, editor ) {
			later();
			watch( editor );
		} );
	}

	/* ------------------------------------------------------------- Boot */

	initGuard();

	$( function () {
		calmBoxes();
		initDots();
	} );
	// WordPress wires its boxes when the page is ready; whatever is added later is calmed here.
	$( window ).on( 'load', calmBoxes );

	/** For other scripts: has the Editor changed something that is not saved? */
	window.favrEditScreen = {
		isChanged: isChanged
	};
} )( jQuery, window, document );
