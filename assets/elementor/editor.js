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

	// Display conditions are Favr's (the Header and Footer stay site-wide).
	( config.hide || [] ).forEach( function ( id ) {
		replace( bar.documentOptionsMenu, 'registerAction', id, 'default', hidden );
	} );

	// Tools and utilities Editors never need (the design system, page settings, the Finder).
	( config.hideTools || [] ).forEach( function ( id ) {
		replace( bar.toolsMenu, 'registerToggleAction', id, 'default', hidden );
	} );
	( config.hideUtilities || [] ).forEach( function ( id ) {
		replace( bar.utilitiesMenu, 'registerAction', id, 'default', hidden );
	} );

	// Widgets from hidden groups stay out of the panel and its search (editor.css hides the groups).
	function calmPanel() {
		var editor = window.elementor;
		var hide = config.hideCategories || [];
		if ( ! editor || ! editor.widgetsCache || ! hide.length ) {
			return;
		}
		Object.keys( editor.widgetsCache ).forEach( function ( name ) {
			var groups = editor.widgetsCache[ name ].categories || [];
			var all = groups.length && groups.every( function ( group ) {
				return -1 !== hide.indexOf( group );
			} );
			if ( all ) {
				editor.widgetsCache[ name ].show_in_panel = false;
			}
		} );
	}
	function watchPanel() {
		calmPanel();
		if ( window.elementor && 'function' === typeof window.elementor.on ) {
			window.elementor.on( 'document:loaded', calmPanel );
		}
	}
	if ( window.elementor && window.elementor.widgetsCache ) {
		watchPanel();
	} else if ( window.jQuery ) {
		window.jQuery( window ).on( 'elementor:init', watchPanel );
	}

	// Exit says where it goes.
	if ( config.back ) {
		replace( bar.mainMenu, 'registerLink', 'exit-to-wordpress', 'exits', function () {
			return { title: config.back.label, href: config.back.href, icon: icons.WordpressIcon };
		} );
	}
}() );
