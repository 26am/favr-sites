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
 *
 * A few phrases get other words, not only other capitals (REWORDED): a file is a "file", not an
 * "attachment". The Publish box tells the time as the rest of Favr does ("11:02 pm") and its
 * month menu shows "Oct", not "10-Oct". News posts are "news posts" after a bulk action. The
 * media window offers no audio or video playlist unless the library holds such files.
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
		'Attachment Details'            => 'File details',
		'Attachment details'            => 'File details', // The title of the same panel in the library's window.
		'Attachment Preview'            => 'File preview',
		'Attachment Display Settings'   => 'How it appears',
		'Attachment Page'               => 'Its own page',
		'View attachment page'          => 'View its page',
		'File URL:'                     => 'File URL',
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
		'Link to Attachment Page'       => 'Link to its own page',
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
		// Saving a category, group or level with the name left empty.
		'A name is required for this term.'                => 'Enter a name first.',
		// Somebody else has the item open. It may be an event or a member, never "this post".
		'%s is currently editing this post. Do you want to take over?' => '%s is editing this right now. Do you want to take over?',
		'%s is currently editing this post.'               => '%s is editing this right now.',
		'Saving is disabled: %s is currently editing this post.' => 'Saving is off: %s is editing this right now.',
		'Sorry, you are not allowed to edit this post.'    => 'Sorry, you are not allowed to edit this item.',
		// Under the table of News categories ("posts" are news posts here).
		self::CATEGORY_DELETE_NOTE                         => 'Deleting a category does not delete its news posts: they move to %s, which cannot be deleted.',
	);

	/** Phrases that get other words, not just other capitals. */
	public const REWORDED = array(
		'Add Media File',
		'Upload Media',
		'Upload New Media',
		'Attachment Details',
		'Attachment details',
		'Attachment Preview',
		'Attachment Display Settings',
		'Attachment Page',
		'Link to Attachment Page',
		'View attachment page',
		'File URL:',
		'A name is required for this term.',
		'%s is currently editing this post. Do you want to take over?',
		'%s is currently editing this post.',
		'Saving is disabled: %s is currently editing this post.',
		'Sorry, you are not allowed to edit this post.',
		self::CATEGORY_PARENT_HELP,
		self::CATEGORY_DELETE_NOTE,
	);

	/**
	 * Patterns WordPress fills in, which are not words: the month menu of the Publish box
	 * ("10-Oct") shows the month alone.
	 */
	public const PATTERNS = array(
		'%1$s-%2$s' => '%2$s',
	);

	/**
	 * Phrases that only change in one translation context: context => phrase => what is used.
	 * The Publish box prints the time with 'H:i' whatever the site's time format ("23:02").
	 */
	public const IN_CONTEXT = array(
		'publish box time format' => array(
			'H:i' => 'g:i a',
		),
	);

	/** WordPress's note under the table of news categories. */
	public const CATEGORY_DELETE_NOTE = 'Deleting a category does not delete the posts in that category. Instead, posts that were only assigned to the deleted category are set to the default category %s. The default category cannot be deleted.';

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
		'wp-media-utils'  => array(
			'Select or Upload Media' => 'Select or upload media', // The picker window of the News editor.
		),
		'wp-color-picker' => array(
			'Select Color' => 'Select color',
		),
	);

	/**
	 * The same for another plugin's scripts: text domain => script handle => phrase => what an
	 * Editor reads. Elementor titles its picker window "Insert Media".
	 */
	public const PLUGIN_SCRIPTS = array(
		'elementor' => array(
			'elementor-editor' => array(
				'Insert Media' => 'Insert media',
			),
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
		if ( 'default' !== $domain || ! is_string( $text ) ) {
			return $translation;
		}
		return self::MAP[ $text ] ?? self::PATTERNS[ $text ] ?? $translation;
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
		if ( 'default' !== $domain || ! is_string( $text ) ) {
			return $translation;
		}
		if ( is_string( $context ) && isset( self::IN_CONTEXT[ $context ][ $text ] ) ) {
			return self::IN_CONTEXT[ $context ][ $text ];
		}
		return self::MAP[ $text ] ?? $translation;
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
	 * @param string $domain Text domain of the script ('default' is WordPress).
	 */
	public static function scriptData( string $handle, string $domain = 'default' ): ?string {
		$phrases = 'default' === $domain ? ( self::SCRIPTS[ $handle ] ?? null ) : ( self::PLUGIN_SCRIPTS[ $domain ][ $handle ] ?? null );
		if ( null === $phrases ) {
			return null;
		}
		$messages = array(
			'' => array(
				'domain' => 'messages',
				'lang'   => 'en',
			),
		);
		foreach ( $phrases as $phrase => $words ) {
			$messages[ $phrase ] = array( $words );
		}
		$json = wp_json_encode( array( 'locale_data' => array( 'messages' => $messages ) ) );
		return is_string( $json ) ? $json : null;
	}

	/**
	 * What a script gets for translations: Favr's phrases for WordPress's own scripts (and the
	 * one of Elementor's), unless something already supplied translations.
	 *
	 * @param mixed  $translations What an earlier filter supplied (null for nothing).
	 * @param string $handle       Script handle.
	 * @param string $domain       Text domain of the script.
	 * @return mixed
	 */
	public static function scriptTranslations( $translations, string $handle, string $domain ) {
		return null === $translations ? self::scriptData( $handle, $domain ) : $translations;
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

	/**
	 * What WordPress says after a bulk action on news posts ("1 news post moved to the Trash."),
	 * as patterns for the number: message key => pattern.
	 *
	 * @param array<string, mixed> $counts How many items each message is about.
	 * @return array<string, string>
	 */
	public static function newsMessages( array $counts ): array {
		$n = static fn( string $key ): int => (int) ( $counts[ $key ] ?? 0 );
		return array(
			/* translators: %s: number of news posts. */
			'updated'   => _n( '%s news post updated.', '%s news posts updated.', $n( 'updated' ), 'favr-sites' ),
			/* translators: %s: number of news posts. */
			'locked'    => _n( '%s news post not updated, somebody is editing it.', '%s news posts not updated, somebody is editing them.', $n( 'locked' ), 'favr-sites' ),
			/* translators: %s: number of news posts. */
			'deleted'   => _n( '%s news post permanently deleted.', '%s news posts permanently deleted.', $n( 'deleted' ), 'favr-sites' ),
			/* translators: %s: number of news posts. */
			'trashed'   => _n( '%s news post moved to the Trash.', '%s news posts moved to the Trash.', $n( 'trashed' ), 'favr-sites' ),
			/* translators: %s: number of news posts. */
			'untrashed' => _n( '%s news post restored from the Trash.', '%s news posts restored from the Trash.', $n( 'untrashed' ), 'favr-sites' ),
		);
	}

	/**
	 * The bulk-action messages with the news words, on the News list only: WordPress also falls
	 * back on the `post` messages for any type that brings none, which must not read "news post".
	 *
	 * @param mixed  $messages  Post type => message key => pattern.
	 * @param mixed  $counts    How many items each message is about.
	 * @param string $post_type Post type of the list on screen.
	 * @return mixed
	 */
	public static function bulkMessages( $messages, $counts, string $post_type ) {
		if ( ! is_array( $messages ) || 'post' !== $post_type ) {
			return $messages;
		}
		$messages['post'] = self::newsMessages( (array) $counts );
		return $messages;
	}

	/**
	 * A file's upload date in its details as in the lists: "Oct 8, 2026".
	 *
	 * @param mixed $response   What the media window is told about the file.
	 * @param mixed $attachment The file.
	 * @return mixed
	 */
	public static function attachmentDate( $response, $attachment ) {
		if ( is_array( $response ) && $attachment instanceof \WP_Post && ! empty( $attachment->post_date ) ) {
			/* translators: Date in a file's details, e.g. "Oct 8, 2026". See https://www.php.net/manual/datetime.format.php */
			$response['dateFormatted'] = mysql2date( __( 'M j, Y', 'favr-sites' ), (string) $attachment->post_date );
		}
		return $response;
	}

	/**
	 * WordPress's media templates with the file type of a file's details written as on its edit
	 * screen ("PNG", not "image/png"). Only the line an Editor reads changes; the players keep
	 * the real type.
	 *
	 * @param string $templates What wp_print_media_templates() prints.
	 */
	public static function shortFileType( string $templates ): string {
		$short = "{{ data.filename && data.filename.indexOf('.') > 0 ? data.filename.split('.').pop().toUpperCase() : data.mime }}";
		return (string) preg_replace_callback(
			'#(<div class="file-type">.*?</strong>)\s*\{\{ data\.mime \}\}#s',
			static fn( array $found ): string => $found[1] . ' ' . $short,
			$templates
		);
	}

	/**
	 * A few lines for WordPress's `post` script. After the date in the Publish box is edited, that
	 * script rewrites the line with the hour as it was typed ("Oct 8, 2026 at 15:02") until the
	 * item is saved; this puts it back on the twelve-hour clock ("at 3:02 pm"). A line that already
	 * ends in am or pm is left alone, which is also what stops it from running twice.
	 */
	public static function publishClockScript(): string {
		return "(function(){var box=document.getElementById('timestamp');"
			. 'if(!box||!window.MutationObserver){return;}'
			. 'new MutationObserver(function(){'
			. "var b=box.querySelector('b'),m=b?/^(.* at )(\\d{1,2}):(\\d{2})$/.exec(b.textContent):null,h;"
			. 'if(!m){return;}'
			. 'h=parseInt(m[2],10);'
			. "b.textContent=m[1]+((h%12)||12)+':'+m[3]+(h>11?' pm':' am');"
			. '}).observe(box,{childList:true,subtree:true,characterData:true});'
			. '})();';
	}

	/** Hooks. Before anything on `init`: post types, taxonomies and scripts take their words there. */
	public function hook(): void {
		add_action( 'init', array( $this, 'start' ), PHP_INT_MIN );
	}

	/** Turn the dictionary on for an Editor reading English; the parts that are not words, for any Editor. */
	public function start(): void {
		if ( ! Audience::current() ) {
			return;
		}
		// Null makes WordPress look for itself: the two "Create … playlist" entries only show when the library has audio or video.
		add_filter( 'media_library_show_audio_playlist', '__return_null' );
		add_filter( 'media_library_show_video_playlist', '__return_null' );
		add_filter( 'wp_prepare_attachment_for_js', array( self::class, 'attachmentDate' ), 10, 2 );
		add_action( 'wp_enqueue_media', array( $this, 'mediaTemplates' ) );
		if ( ! self::forLocale( (string) determine_locale() ) ) {
			return;
		}
		add_filter( 'gettext', array( self::class, 'plain' ), 10, 3 );
		add_filter( 'gettext_with_context', array( self::class, 'inContext' ), 10, 4 );
		add_filter( 'pre_load_script_translations', array( $this, 'scripts' ), 10, 4 );
		add_filter( 'bulk_post_updated_messages', array( $this, 'bulk' ), PHP_INT_MAX, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'publishClock' ) );
	}

	/** Hand publishClockScript() to WordPress's `post` script: it is only printed where that script is. */
	public function publishClock(): void {
		wp_add_inline_script( 'post', self::publishClockScript() );
	}

	/** Once the media window is wanted on a screen: print its templates through shortFileType(). */
	public function mediaTemplates(): void {
		if ( remove_action( 'admin_footer', 'wp_print_media_templates' ) ) {
			add_action( 'admin_footer', array( $this, 'printMediaTemplates' ) );
		}
	}

	/**
	 * WordPress's media templates, with the short file type. With them, one rule: WordPress hides
	 * a menu entry of the media window (the playlists, when the library has no audio or video) with
	 * its `hidden` class, which its own `display: block` on those entries otherwise undoes.
	 */
	public function printMediaTemplates(): void {
		ob_start();
		wp_print_media_templates();
		echo self::shortFileType( (string) ob_get_clean() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress's own templates, one expression swapped.
		echo '<style>body.favr-skin .media-modal .media-menu .media-menu-item.hidden{display:none}</style>';
	}

	/**
	 * Bulk-action messages of the list on screen.
	 *
	 * @param mixed $messages Post type => message key => pattern.
	 * @param mixed $counts   How many items each message is about.
	 * @return mixed
	 */
	public function bulk( $messages, $counts ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return self::bulkMessages( $messages, $counts, $screen instanceof \WP_Screen ? (string) $screen->post_type : '' );
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
