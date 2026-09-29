/**
 * Favr Sites: a calmer Elementor editor for site Editors (loaded only for them).
 *
 * Re-registers Elementor app-bar items by id with `overwrite: true`, which is what Elementor's
 * menu registry is for. Ids and groups come from elementor/assets/js/packages/editor-app-bar.
 * If Elementor renames an item, the old id no longer matches and Elementor's default shows.
 */
( function () {
	'use strict';

	var v2 = window.elementorV2 || {};
	var bar = v2.editorAppBar;
	var icons = v2.icons || {};
	var config = window.favrSitesElementor || {};

	if ( ! bar || ! bar.mainMenu ) {
		return;
	}

	function hidden() {
		return { visible: false, title: '' };
	}

	function replace( menu, register, id, group, useProps ) {
		if ( menu && 'function' === typeof menu[ register ] ) {
			menu[ register ]( { id: id, group: group, priority: 10, overwrite: true, useProps: useProps } );
		}
	}

	// Site-wide design and templates are Favr's job.
	replace( bar.mainMenu, 'registerToggleAction', 'toggle-site-settings', 'default', hidden );
	replace( bar.mainMenu, 'registerAction', 'open-theme-builder', 'default', hidden );
	// Elementor account, feedback and AI assistant.
	replace( bar.mainMenu, 'registerLink', 'app-bar-connect', 'exits', hidden );
	replace( bar.mainMenu, 'registerAction', 'open-send-feedback', 'help', hidden );
	replace( bar.toolsMenu, 'registerToggleAction', 'toggle-angie', 'default', hidden );

	// Help goes to Favr.
	replace( bar.mainMenu, 'registerLink', 'open-help-center', 'help', config.help ? function () {
		return { title: config.help.label, href: config.help.href, target: '_blank', icon: icons.HelpIcon };
	} : hidden );

	// Favr keeps the Header and Footer published and site-wide: no drafts, no display conditions.
	( config.hide || [] ).forEach( function ( id ) {
		replace( bar.documentOptionsMenu, 'registerAction', id, 'document-save-draft' === id ? 'save' : 'default', hidden );
	} );

	// Exit says where it goes.
	if ( config.back ) {
		replace( bar.mainMenu, 'registerLink', 'exit-to-wordpress', 'exits', function () {
			return { title: config.back.label, href: config.back.href, icon: icons.WordpressIcon };
		} );
	}
}() );
