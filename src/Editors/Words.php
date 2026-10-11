<?php
/**
 * WordPress's own words in Favr's sentence case.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Editors;

use FavrSites\Dashboard\Audience;

/**
 * For Editors, in wp-admin: the Title Case words WordPress prints around Favr's sentence case
 * ("Save Draft", "Add Media", "Attachment Details") read "Save draft", "Add media", "Attachment
 * details". A dictionary of the phrases an Editor meets, applied through WordPress's translation
 * filters from the start of `init`, so the phrases WordPress hands to its scripts (the media
 * window) are covered too; the few phrases its scripts translate in the browser get the same
 * words as script translations.
 *
 * Rules: "Trash" keeps its capital (it is a place), so do names and abbreviations; a phrase that
 * is not Title Case is left alone; only WordPress's own text domain is touched; nothing on the
 * public site changes. English only: on a translated site the capitals are the translators'.
 * The filters run for every phrase WordPress prints, so each is one comparison and one lookup.
 */
final class Words {

	/**
	 * WordPress's phrase, exactly as written in its code => what an Editor reads. The same
	 * phrase reads the same whatever its translation context.
	 */
	public const MAP = self::PHRASES + self::SENTENCES;

	/** The short phrases: buttons, links, tabs, headings and labels. */
	private const PHRASES = array(
		// The frame around every screen.
		'Collapse Menu'                 => 'Collapse menu',
		'Collapse Main Menu'            => 'Collapse main menu',
		'Visit Site'                    => 'Visit site',

		// Lists.
		'Select All'                    => 'Select all',
		'Current Page'                  => 'Current page',
		'Quick Edit'                    => 'Quick edit',
		'Quick&nbsp;Edit'               => 'Quick&nbsp;edit',
		'Bulk Edit'                     => 'Bulk edit',
		'&mdash; No Change &mdash;'     => '&mdash; No change &mdash;',
		'Main Page (no parent)'         => 'Main page (no parent)',
		'Not Sticky'                    => 'Not sticky',
		'Delete Permanently'            => 'Delete permanently',
		'Last Modified'                 => 'Last modified',
		'Front Page'                    => 'Front page',
		'Posts Page'                    => 'Posts page',
		'Privacy Policy Page'           => 'Privacy policy page',
		'Most Used'                     => 'Most used',

		// The Publish box.
		'Save Draft'                    => 'Save draft',
		'Save as Pending'               => 'Save as pending',
		'Submit for Review'             => 'Submit for review',
		'Preview Changes'               => 'Preview changes',
		'Pending Review'                => 'Pending review',
		'Privately Published'           => 'Privately published',
		'Public, Sticky'                => 'Public, sticky',

		// The editor's buttons (WordPress hands these to the visual editor as its translations).
		'Add Media'                     => 'Add media',
		'Add Link'                      => 'Add link',
		'Insert Read More tag'          => 'Insert read more tag',
		'Insert Page Break tag'         => 'Insert page break tag',
		'Extended Toolbar'              => 'Extended toolbar',
		'Keyboard Shortcuts'            => 'Keyboard shortcuts',
		'Font Sizes'                    => 'Font sizes',
		'Table of Contents'             => 'Table of contents',
		'Check Spelling'                => 'Check spelling',
		'Add to Dictionary'             => 'Add to dictionary',
		'Switch Editor Text Direction'  => 'Switch editor text direction',

		// Media: the library, the uploader, the media window and attachment details.
		'Media Library'                 => 'Media library',
		'Add Media File'                => 'Add media',
		'Upload Media'                  => 'Add media',
		'Upload New Media'              => 'Add media', // The same heading in older WordPress.
		'Search Media'                  => 'Search media',
		'Select Files'                  => 'Select files',
		'Upload Limit Exceeded'         => 'Upload limit exceeded',
		'Attachment Details'            => 'Attachment details',
		'Attachment Preview'            => 'Attachment preview',
		'Attachment Display Settings'   => 'Attachment display settings',
		'Attachment Page'               => 'Attachment page',
		'Alternative Text'              => 'Alternative text',
		'Alt Text'                      => 'Alt text',
		'Image Caption'                 => 'Image caption',
		'Short Description'             => 'Short description',
		'Document Preview'              => 'Document preview',
		'Edit Image'                    => 'Edit image',
		'Edit Media'                    => 'Edit media',
		'Edit Original'                 => 'Edit original',
		'Edit Selection'                => 'Edit selection',
		'Display Settings'              => 'Display settings',
		'Advanced Options'              => 'Advanced options',
		'Custom Size'                   => 'Custom size',
		'Full Size'                     => 'Full size',
		'Link To'                       => 'Link to',
		'Link Text'                     => 'Link text',
		'Link Rel'                      => 'Link rel',
		'Link CSS Class'                => 'Link CSS class',
		'Link to Attachment Page'       => 'Link to attachment page',
		'Link to Media File'            => 'Link to media file',
		'Media File'                    => 'Media file',
		'Image CSS Class'               => 'Image CSS class',
		'Image Title Attribute'         => 'Image title attribute',
		'Embed or Link'                 => 'Embed or link',
		'Embed Media Player'            => 'Embed media player',
		'Gallery Settings'              => 'Gallery settings',
		'Playlist Settings'             => 'Playlist settings',
		'Random Order'                  => 'Random order',
		'Poster Image'                  => 'Poster image',
		'Add to Audio Playlist'         => 'Add to audio playlist',
		'Add to video Playlist'         => 'Add to video playlist',
		'Show Artist Name in Tracklist' => 'Show artist name in tracklist',
		'Show Images'                   => 'Show images',
		'Show Tracklist'                => 'Show tracklist',
		'Show Video List'               => 'Show video list',

		// "Edit image" on an attachment.
		'Scale Image'                   => 'Scale image',
		'Scale Image Help'              => 'Scale image help',
		'Crop Image'                    => 'Crop image',
		'Image Crop Help'               => 'Image crop help',
		'Crop Aspect Ratio'             => 'Crop aspect ratio',
		'Crop Selection'                => 'Crop selection',
		'Starting Coordinates:'         => 'Starting coordinates:',
		'Apply Crop'                    => 'Apply crop',
		'Clear Crop'                    => 'Clear crop',
		'Image Rotation'                => 'Image rotation',
		'Thumbnail Settings'            => 'Thumbnail settings',
		'Thumbnail Settings Help'       => 'Thumbnail settings help',
		'Cancel Editing'                => 'Cancel editing',
		'Save Edits'                    => 'Save edits',

		// The audio and video player on an attachment.
		'Audio Player'                  => 'Audio player',
		'Video Player'                  => 'Video player',
		'Time Slider'                   => 'Time slider',
		'Volume Slider'                 => 'Volume slider',
		'Download File'                 => 'Download file',
		'Live Broadcast'                => 'Live broadcast',
		'Captions/Subtitles'            => 'Captions/subtitles',
	);

	/** Whole sentences. */
	private const SENTENCES = array(
		// What the visual editor is called when it is pointed at or read aloud.
		'Rich Text Area. Press Alt-Shift-H for help.'      => 'Rich text area. Press Alt-Shift-H for help.',
		'Rich Text Area. Press Control-Option-H for help.' => 'Rich text area. Press Control-Option-H for help.',
		// Under "Parent category" WordPress prints this instead of the taxonomy's own help (Terms::help()).
		self::CATEGORY_PARENT_HELP                         => 'Optional. Choose one to file this under it.',
	);

	/** Phrases that get other words, not just other capitals. */
	public const REWORDED = array( 'Add Media File', 'Upload Media', 'Upload New Media', self::CATEGORY_PARENT_HELP );

	/** WordPress's help under a news category's parent, which is not one of the taxonomy's labels. */
	public const CATEGORY_PARENT_HELP = 'Categories, unlike tags, can have a hierarchy. You might have a Jazz category, and under that have children categories for Bebop and Big Band. Totally optional.';

	/**
	 * Phrases WordPress's scripts translate in the browser, where no PHP filter reaches them:
	 * script handle => phrase => what an Editor reads. `post` rewrites the Publish box after its
	 * "Edit" links are used; `common` names the menu's collapse button; `wp-color-picker` is the
	 * button of a colour field (a membership level's badge colour).
	 */
	public const SCRIPTS = array(
		'post'            => array(
			'Save Draft'          => 'Save draft',
			'Save as Pending'     => 'Save as pending',
			'Saving Draft…'       => 'Saving draft…',
			'Privately Published' => 'Privately published',
			'Password Protected'  => 'Password protected',
			'Public, Sticky'      => 'Public, sticky',
			'URL Slug'            => 'URL slug',
		),
		'common'          => array(
			'Collapse Main menu' => 'Collapse main menu',
			'Expand Main menu'   => 'Expand main menu',
		),
		'wp-color-picker' => array(
			'Select Color' => 'Select color',
		),
	);

	/** Words that keep their capital inside a label (sentence()). */
	private const NAMES = array( 'Trash', 'Favr', 'Elementor' );

	/**
	 * A phrase without a translation context (`gettext`).
	 *
	 * @param mixed $translation What would be shown.
	 * @param mixed $text        The phrase in the code.
	 * @param mixed $domain      Whose phrase it is ('default' is WordPress).
	 * @return mixed
	 */
	public static function plain( $translation, $text, $domain ) {
		return 'default' === $domain && is_string( $text ) && isset( self::MAP[ $text ] ) ? self::MAP[ $text ] : $translation;
	}

	/**
	 * A phrase with a translation context (`gettext_with_context`).
	 *
	 * @param mixed $translation What would be shown.
	 * @param mixed $text        The phrase in the code.
	 * @param mixed $context     The context (the words are the same in every one).
	 * @param mixed $domain      Whose phrase it is.
	 * @return mixed
	 */
	public static function inContext( $translation, $text, $context, $domain ) {
		return 'default' === $domain && is_string( $text ) && isset( self::MAP[ $text ] ) ? self::MAP[ $text ] : $translation;
	}

	/**
	 * Is this a language the dictionary is written for?
	 *
	 * @param string $locale Locale of the screen, e.g. "en_US".
	 */
	public static function forLocale( string $locale ): bool {
		return 'en' === $locale || str_starts_with( $locale, 'en_' );
	}

	/**
	 * A script's phrases as the translation data WordPress hands to `wp.i18n` (Jed format), or
	 * null for a script that has none.
	 *
	 * @param string $handle Script handle.
	 */
	public static function scriptData( string $handle ): ?string {
		if ( ! isset( self::SCRIPTS[ $handle ] ) ) {
			return null;
		}
		$messages = array(
			'' => array(
				'domain' => 'messages',
				'lang'   => 'en',
			),
		);
		foreach ( self::SCRIPTS[ $handle ] as $phrase => $words ) {
			$messages[ $phrase ] = array( $words );
		}
		$json = wp_json_encode( array( 'locale_data' => array( 'messages' => $messages ) ) );
		return is_string( $json ) ? $json : null;
	}

	/**
	 * What a script gets for translations: Favr's phrases for WordPress's own scripts, unless
	 * something already supplied translations.
	 *
	 * @param mixed  $translations What an earlier filter supplied (null for nothing).
	 * @param string $handle       Script handle.
	 * @param string $domain       Text domain of the script.
	 * @return mixed
	 */
	public static function scriptTranslations( $translations, string $handle, string $domain ) {
		return null === $translations && 'default' === $domain ? self::scriptData( $handle ) : $translations;
	}

	/**
	 * A label in sentence case: "Directory Listing" reads "Directory listing". Abbreviations
	 * ("CSS") and names ("Trash", "Favr") keep their capitals.
	 *
	 * @param string $label Label.
	 */
	public static function sentence( string $label ): string {
		$parts = preg_split( '/(\s+)/', trim( $label ), -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return trim( $label );
		}
		foreach ( $parts as $i => $part ) {
			if ( 0 === $i ) {
				$parts[ $i ] = ucfirst( $part );
			} elseif ( preg_match( '/^[A-Z][a-z]+$/', $part ) && ! in_array( $part, self::NAMES, true ) ) {
				$parts[ $i ] = lcfirst( $part );
			}
		}
		return implode( '', $parts );
	}

	/** Hooks. Before anything on `init`: post types, taxonomies and scripts take their words there. */
	public function hook(): void {
		add_action( 'init', array( $this, 'start' ), PHP_INT_MIN );
	}

	/** Turn the dictionary on for an Editor reading English. */
	public function start(): void {
		if ( ! Audience::current() || ! self::forLocale( (string) determine_locale() ) ) {
			return;
		}
		add_filter( 'gettext', array( self::class, 'plain' ), 10, 3 );
		add_filter( 'gettext_with_context', array( self::class, 'inContext' ), 10, 4 );
		add_filter( 'pre_load_script_translations', array( $this, 'scripts' ), 10, 4 );
	}

	/**
	 * Script translations for WordPress's own scripts.
	 *
	 * @param mixed $translations JSON, false or null.
	 * @param mixed $file         Translation file WordPress would read.
	 * @param mixed $handle       Script handle.
	 * @param mixed $domain       Text domain.
	 * @return mixed
	 */
	public function scripts( $translations, $file, $handle, $domain ) {
		return self::scriptTranslations( $translations, (string) $handle, (string) $domain );
	}
}
