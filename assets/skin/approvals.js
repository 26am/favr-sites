/**
 * Favr Sites: safer keys on the Approvals screen, for Editors.
 *
 * Each card is one form whose first button is Approve, so the browser pressed Approve when Enter
 * was pressed in the note field: a note written to explain a rejection approved the item. Here
 * Enter in a note moves to Reject instead, and Reject asks once before it sends. Approve stays
 * one click. The markup comes from the shared library, so this script only listens.
 */
( function () {
	'use strict';

	const words = window.favrApprovals || {};
	const reject = 'button[name="decision"][value="reject"]';

	// Enter in a note never sends the form; it moves to Reject, the button the note belongs to.
	document.addEventListener( 'keydown', function ( event ) {
		const field = event.target;
		if ( 'Enter' !== event.key || event.isComposing || ! field.matches || ! field.matches( '.favr-approvals__item input[name="note"]' ) ) {
			return;
		}
		event.preventDefault();
		const button = field.form && field.form.querySelector( reject );
		if ( button ) {
			button.focus();
		}
	} );

	// Reject asks once, naming the item and saying whether a note goes with it.
	document.addEventListener( 'click', function ( event ) {
		const button = event.target.closest ? event.target.closest( '.favr-approvals__item ' + reject ) : null;
		if ( ! button || ! button.form ) {
			return;
		}
		const title = button.form.querySelector( '.favr-approvals__head strong' );
		const note = button.form.querySelector( 'input[name="note"]' );
		const question = ( note && note.value.trim() ? words.withNote : words.noNote ) || '';
		if ( question && ! window.confirm( question.replace( '%s', title ? title.textContent.trim() : '' ) ) ) {
			event.preventDefault();
		}
	} );
}() );
