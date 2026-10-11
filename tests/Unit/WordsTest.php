<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use FavrSites\Dashboard\Audience;
use FavrSites\Editors\Words;

final class WordsTest extends TestCase {

	/** Words that keep a capital inside a phrase: a place, names, abbreviations and keys. */
	private const CAPITALS = array( 'Trash', 'WordPress', 'Elementor', 'Favr', 'CSS', 'URL', 'Alt-Shift-H', 'Control-Option-H' );

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
		Functions\when( '_n' )->alias( static fn( string $one, string $many, int $count ): string => 1 === $count ? $one : $many );
		Audience::flush();
	}

	protected function tearDown(): void {
		Audience::flush();
		parent::tearDown();
	}

	public function test_wordpress_title_case_reads_in_sentence_case(): void {
		$this->assertSame( 'Save draft', Words::plain( 'Save Draft', 'Save Draft', 'default' ) );
		$this->assertSame( 'Collapse menu', Words::plain( 'Collapse Menu', 'Collapse Menu', 'default' ) );
		$this->assertSame( 'Preview changes', Words::plain( 'Preview Changes', 'Preview Changes', 'default' ) );
		$this->assertSame( 'Delete permanently', Words::plain( 'Delete Permanently', 'Delete Permanently', 'default' ) );
		$this->assertSame( 'Display settings', Words::plain( 'Display Settings', 'Display Settings', 'default' ) );
		$this->assertSame( 'Quick&nbsp;edit', Words::plain( 'Quick&nbsp;Edit', 'Quick&nbsp;Edit', 'default' ) );
	}

	public function test_a_phrase_with_a_context_is_looked_up_the_same_way(): void {
		$this->assertSame( 'Front page', Words::inContext( 'Front Page', 'Front Page', 'page label', 'default' ) );
		$this->assertSame( 'Add media', Words::inContext( 'Add Media', 'Add Media', 'label for button in the media widget', 'default' ) );
		$this->assertSame( 'Most used', Words::inContext( 'Most Used', 'Most Used', 'categories', 'default' ) );
	}

	public function test_anything_else_is_left_as_it_was(): void {
		$this->assertSame( 'Publish', Words::plain( 'Publish', 'Publish', 'default' ) );
		$this->assertSame( 'Move to Trash', Words::plain( 'Move to Trash', 'Move to Trash', 'default' ) );
		$this->assertSame( 'Veröffentlichen', Words::plain( 'Veröffentlichen', 'Publish', 'default' ) );
		$this->assertSame( 'Schedule', Words::inContext( 'Schedule', 'Schedule', 'post action/button label', 'default' ) );
	}

	public function test_only_wordpress_own_words_are_replaced(): void {
		$this->assertSame( 'Save Draft', Words::plain( 'Save Draft', 'Save Draft', 'some-plugin' ) );
		$this->assertSame( 'Front Page', Words::inContext( 'Front Page', 'Front Page', 'page label', 'elementor' ) );
	}

	public function test_every_entry_only_changes_capitals_unless_it_is_a_rewording(): void {
		foreach ( Words::MAP as $phrase => $words ) {
			if ( in_array( $phrase, Words::REWORDED, true ) ) {
				continue;
			}
			$this->assertNotSame( $phrase, $words, $phrase );
			$this->assertSame( strtolower( $phrase ), strtolower( $words ), $phrase );
		}
	}

	public function test_every_rewording_is_in_the_dictionary(): void {
		foreach ( Words::REWORDED as $phrase ) {
			$this->assertArrayHasKey( $phrase, Words::MAP );
		}
	}

	public function test_what_an_editor_reads_is_sentence_case(): void {
		foreach ( Words::MAP as $phrase => $words ) {
			$this->assertTrue( $this->isSentenceCase( $words ), $phrase . ' => ' . $words );
		}
		foreach ( Words::SCRIPTS as $phrases ) {
			foreach ( $phrases as $phrase => $words ) {
				$this->assertTrue( $this->isSentenceCase( $words ), $phrase . ' => ' . $words );
				$this->assertSame( strtolower( $phrase ), strtolower( $words ), $phrase );
			}
		}
	}

	public function test_the_trash_keeps_its_capital(): void {
		foreach ( Words::MAP as $phrase => $words ) {
			if ( str_contains( $phrase, 'Trash' ) ) {
				$this->assertStringContainsString( 'Trash', $words, $phrase );
			}
		}
		$this->assertArrayNotHasKey( 'Move to Trash', Words::MAP );
		$this->assertArrayNotHasKey( 'Empty Trash', Words::MAP );
	}

	public function test_date_formats_and_single_words_are_not_in_the_dictionary(): void {
		foreach ( array( 'F j, Y', 'M j, Y', 'M j, Y g:i a', 'Publish', 'Update', 'Draft', 'Trash' ) as $phrase ) {
			$this->assertArrayNotHasKey( $phrase, Words::MAP, $phrase );
		}
	}

	public function test_the_media_screens_use_the_menus_words(): void {
		$this->assertSame( 'Media library', Words::plain( 'Media Library', 'Media Library', 'default' ) );
		$this->assertSame( 'Add media', Words::plain( 'Add Media File', 'Add Media File', 'default' ) );
		$this->assertSame( 'Add media', Words::plain( 'Upload Media', 'Upload Media', 'default' ) );
		$this->assertSame( 'Search media', Words::plain( 'Search Media', 'Search Media', 'default' ) );
	}

	public function test_the_dictionary_is_for_english_only(): void {
		$this->assertTrue( Words::forLocale( 'en_US' ) );
		$this->assertTrue( Words::forLocale( 'en_GB' ) );
		$this->assertTrue( Words::forLocale( 'en' ) );
		$this->assertFalse( Words::forLocale( 'de_DE' ), 'A translated site keeps its translators\' capitals.' );
		$this->assertFalse( Words::forLocale( 'eo' ) );
		$this->assertFalse( Words::forLocale( '' ) );
	}

	public function test_phrases_wordpress_translates_in_the_browser_are_handed_to_its_scripts(): void {
		$json = Words::scriptData( 'post' );
		$this->assertIsString( $json );
		$data = json_decode( (string) $json, true );
		$this->assertSame( array( 'Save draft' ), $data['locale_data']['messages']['Save Draft'] );
		$this->assertSame( array( 'Save as pending' ), $data['locale_data']['messages']['Save as Pending'] );
		$this->assertSame( array( 'Password protected' ), $data['locale_data']['messages']['Password Protected'] );
		$this->assertSame( 'messages', $data['locale_data']['messages']['']['domain'] );
	}

	public function test_a_script_without_phrases_keeps_wordpress_own_loading(): void {
		$this->assertNull( Words::scriptData( 'jquery' ) );
		$this->assertNull( Words::scriptData( '' ) );
	}

	public function test_a_script_only_gets_wordpress_own_phrases_and_nothing_overrides_a_real_translation(): void {
		$this->assertSame( 'kept', Words::scriptTranslations( 'kept', 'post', 'default' ), 'Somebody already supplied translations.' );
		$this->assertNull( Words::scriptTranslations( null, 'post', 'some-plugin' ) );
		$this->assertNull( Words::scriptTranslations( null, 'media-views', 'default' ) );
		$this->assertSame( Words::scriptData( 'post' ), Words::scriptTranslations( null, 'post', 'default' ) );
	}

	public function test_a_label_is_put_in_sentence_case(): void {
		$this->assertSame( 'Directory listing', Words::sentence( 'Directory Listing' ) );
		$this->assertSame( 'News post', Words::sentence( 'News post' ) );
		$this->assertSame( 'Event', Words::sentence( 'event' ) );
		$this->assertSame( 'Image CSS class', Words::sentence( 'Image CSS Class' ) );
		$this->assertSame( 'Favr member', Words::sentence( 'Favr Member' ) );
		$this->assertSame( 'Move to Trash', Words::sentence( 'Move To Trash' ) );
		$this->assertSame( '', Words::sentence( '  ' ) );
	}

	public function test_the_file_panel_speaks_of_files_not_attachments(): void {
		$this->assertSame( 'File details', Words::plain( 'Attachment Details', 'Attachment Details', 'default' ) );
		$this->assertSame( 'File details', Words::plain( 'Attachment details', 'Attachment details', 'default' ) );
		$this->assertSame( 'How it appears', Words::plain( 'Attachment Display Settings', 'Attachment Display Settings', 'default' ) );
		$this->assertSame( 'Its own page', Words::plain( 'Attachment Page', 'Attachment Page', 'default' ) );
		$this->assertSame( 'Link to its own page', Words::plain( 'Link to Attachment Page', 'Link to Attachment Page', 'default' ) );
		$this->assertSame( 'File URL', Words::plain( 'File URL:', 'File URL:', 'default' ), 'The only label with a colon loses it.' );
		foreach ( Words::MAP as $phrase => $words ) {
			$this->assertStringNotContainsStringIgnoringCase( 'attachment', $words, $phrase );
		}
	}

	public function test_wordpress_words_for_a_category_do_not_reach_the_editor(): void {
		$this->assertSame( 'Enter a name first.', Words::plain( 'x', 'A name is required for this term.', 'default' ) );
		$note = Words::plain( 'x', Words::CATEGORY_DELETE_NOTE, 'default' );
		$this->assertSame( 'Deleting a category does not delete its news posts: they move to %s, which cannot be deleted.', $note );
		$this->assertStringContainsString( 'Association News', sprintf( $note, '<strong>Association News</strong>' ) );
	}

	public function test_the_publish_box_uses_the_twelve_hour_clock(): void {
		$this->assertSame( 'g:i a', Words::inContext( 'H:i', 'H:i', 'publish box time format', 'default' ) );
		$this->assertSame( 'H:i', Words::inContext( 'H:i', 'H:i', 'some other clock', 'default' ), 'Only the Publish box.' );
		$this->assertSame( 'H:i', Words::inContext( 'H:i', 'H:i', 'publish box time format', 'some-plugin' ) );
		$this->assertSame( 'H:i', Words::plain( 'H:i', 'H:i', 'default' ) );
	}

	public function test_the_month_menu_of_the_publish_box_shows_the_month_without_its_number(): void {
		$this->assertSame( '%2$s', Words::plain( '%1$s-%2$s', '%1$s-%2$s', 'default' ) );
		$this->assertSame( 'Oct', sprintf( Words::plain( '%1$s-%2$s', '%1$s-%2$s', 'default' ), '10', 'Oct' ) );
		$this->assertSame( '%1$s-%2$s', Words::plain( '%1$s-%2$s', '%1$s-%2$s', 'some-plugin' ) );
	}

	public function test_the_picker_windows_of_the_news_editor_and_elementor_are_titled_in_sentence_case(): void {
		$news = json_decode( (string) Words::scriptData( 'wp-media-utils' ), true );
		$this->assertSame( array( 'Select or upload media' ), $news['locale_data']['messages']['Select or Upload Media'] );
		$elementor = json_decode( (string) Words::scriptData( 'elementor-editor', 'elementor' ), true );
		$this->assertSame( array( 'Insert media' ), $elementor['locale_data']['messages']['Insert Media'] );
		$this->assertNull( Words::scriptData( 'elementor-editor' ), 'Not one of WordPress\'s own scripts.' );
		$this->assertNull( Words::scriptData( 'post', 'elementor' ) );
		$this->assertSame( Words::scriptData( 'elementor-editor', 'elementor' ), Words::scriptTranslations( null, 'elementor-editor', 'elementor' ) );
		$this->assertSame( 'kept', Words::scriptTranslations( 'kept', 'elementor-editor', 'elementor' ) );
		foreach ( Words::PLUGIN_SCRIPTS as $handles ) {
			foreach ( $handles as $phrases ) {
				foreach ( $phrases as $phrase => $words ) {
					$this->assertTrue( $this->isSentenceCase( $words ), $phrase );
					$this->assertSame( strtolower( $phrase ), strtolower( $words ), $phrase );
				}
			}
		}
	}

	public function test_news_posts_are_called_news_posts_after_a_bulk_action(): void {
		$one = Words::newsMessages( array( 'trashed' => 1 ) );
		$this->assertSame( '%s news post moved to the Trash.', $one['trashed'] );
		$many = Words::newsMessages(
			array(
				'trashed'   => 3,
				'deleted'   => 2,
				'untrashed' => 1,
			)
		);
		$this->assertSame( '%s news posts moved to the Trash.', $many['trashed'] );
		$this->assertSame( '%s news posts permanently deleted.', $many['deleted'] );
		$this->assertSame( '%s news post restored from the Trash.', $many['untrashed'] );
		$this->assertSame( array( 'updated', 'locked', 'deleted', 'trashed', 'untrashed' ), array_keys( $many ) );
	}

	public function test_only_the_news_list_gets_the_news_words(): void {
		$stock = array( 'post' => array( 'trashed' => '%s post moved to the Trash.' ) );
		$this->assertSame( '%s news post moved to the Trash.', Words::bulkMessages( $stock, array( 'trashed' => 1 ), 'post' )['post']['trashed'] );
		$this->assertSame( $stock, Words::bulkMessages( $stock, array( 'trashed' => 1 ), 'favr_sponsor' ), 'A type without words of its own falls back on these.' );
		$this->assertSame( 'broken', Words::bulkMessages( 'broken', array(), 'post' ) );
	}

	public function test_a_file_details_date_is_the_short_one(): void {
		Functions\when( 'mysql2date' )->alias( static fn( string $format, string $date ): string => gmdate( $format, (int) strtotime( $date ) ) );
		$file = new class( array( 'ID' => 143, 'post_type' => 'attachment' ) ) extends \WP_Post {
			/** @var string */
			public $post_date = '2026-10-08 23:02:00';
		};
		$out  = Words::attachmentDate( array( 'dateFormatted' => 'October 8, 2026' ), $file );
		$this->assertSame( 'Oct 8, 2026', $out['dateFormatted'] );
		$this->assertSame( 'broken', Words::attachmentDate( 'broken', $file ) );
	}

	public function test_nothing_is_reworded_for_somebody_who_is_not_an_editor(): void {
		Functions\when( 'wp_get_current_user' )->justReturn( new \WP_User( 1, array( 'administrator' ) ) );
		Functions\when( 'user_can' )->justReturn( true );
		Functions\when( 'determine_locale' )->justReturn( 'en_US' );
		Filters\expectAdded( 'gettext' )->never();
		Filters\expectAdded( 'gettext_with_context' )->never();
		Filters\expectAdded( 'pre_load_script_translations' )->never();
		Filters\expectAdded( 'bulk_post_updated_messages' )->never();
		Filters\expectAdded( 'media_library_show_audio_playlist' )->never();
		( new Words() )->start();
		$this->assertFalse( has_filter( 'gettext' ) );
		$this->assertFalse( has_filter( 'wp_prepare_attachment_for_js' ) );
	}

	public function test_an_editor_reading_english_gets_the_dictionary(): void {
		Functions\when( 'wp_get_current_user' )->justReturn( new \WP_User( 456, array( 'editor' ) ) );
		Functions\when( 'user_can' )->justReturn( false );
		Functions\when( 'determine_locale' )->justReturn( 'en_US' );
		Filters\expectAdded( 'gettext' )->once();
		Filters\expectAdded( 'gettext_with_context' )->once();
		Filters\expectAdded( 'media_library_show_audio_playlist' )->once()->with( '__return_null' );
		Filters\expectAdded( 'media_library_show_video_playlist' )->once()->with( '__return_null' );
		( new Words() )->start();
		$this->assertSame( 10, has_filter( 'gettext', array( Words::class, 'plain' ) ) );
		$this->assertTrue( has_filter( 'bulk_post_updated_messages' ) );
		$this->assertSame( 10, has_filter( 'wp_prepare_attachment_for_js', array( Words::class, 'attachmentDate' ) ) );
	}

	public function test_an_editor_reading_german_keeps_the_translators_words(): void {
		Functions\when( 'wp_get_current_user' )->justReturn( new \WP_User( 456, array( 'editor' ) ) );
		Functions\when( 'user_can' )->justReturn( false );
		Functions\when( 'determine_locale' )->justReturn( 'de_DE' );
		Filters\expectAdded( 'gettext' )->never();
		// The playlist entries are not words: they go whatever the language.
		Filters\expectAdded( 'media_library_show_audio_playlist' )->once();
		( new Words() )->start();
		$this->assertFalse( has_filter( 'gettext' ) );
		$this->assertTrue( has_filter( 'media_library_show_video_playlist' ) );
	}

	public function test_a_file_details_type_is_the_short_one_of_the_edit_screen(): void {
		$stock = '<div class="filename"><strong>File name:</strong> {{ data.filename }}</div>' . "\n"
			. '				<div class="file-type"><strong>File type:</strong> {{ data.mime }}</div>' . "\n"
			. '<source type="{{ data.mime }}" src="{{ data.url }}" />';
		$mine  = Words::shortFileType( $stock );
		$this->assertStringContainsString( '<div class="file-type"><strong>File type:</strong> {{ data.filename && data.filename.indexOf(\'.\') > 0 ? data.filename.split(\'.\').pop().toUpperCase() : data.mime }}</div>', $mine );
		$this->assertStringContainsString( '<source type="{{ data.mime }}" src="{{ data.url }}" />', $mine, 'The player still gets the real type.' );
		$this->assertStringContainsString( '{{ data.filename }}</div>', $mine );
		$this->assertSame( 'nothing to change', Words::shortFileType( 'nothing to change' ) );
	}

	public function test_the_publish_box_keeps_the_twelve_hour_clock_after_its_date_is_edited(): void {
		// WordPress's script rewrites the line with the hour as typed ("at 15:02") until the item is saved.
		$js = Words::publishClockScript();
		$this->assertStringContainsString( "getElementById('timestamp')", $js );
		$this->assertStringContainsString( 'MutationObserver', $js );
		$this->assertStringContainsString( "' am'", $js );
		$this->assertStringContainsString( "' pm'", $js );
		$this->assertStringNotContainsString( '<', $js, 'Nothing that could close the script element.' );
	}

	public function test_an_event_or_a_member_somebody_else_has_open_is_not_called_a_post(): void {
		$this->assertSame( '%s is editing this right now. Do you want to take over?', Words::plain( 'x', '%s is currently editing this post. Do you want to take over?', 'default' ) );
		$this->assertSame( '%s is editing this right now.', Words::plain( 'x', '%s is currently editing this post.', 'default' ) );
		$this->assertSame( 'Saving is off: %s is editing this right now.', Words::plain( 'x', 'Saving is disabled: %s is currently editing this post.', 'default' ) );
		$this->assertSame( 'Sorry, you are not allowed to edit this item.', Words::plain( 'x', 'Sorry, you are not allowed to edit this post.', 'default' ) );
		foreach ( Words::MAP as $phrase => $words ) {
			$this->assertSame( substr_count( $phrase, '%s' ), substr_count( $words, '%s' ), $phrase );
		}
	}

	/** No word after the first starts with a capital, unless it is a name or an abbreviation. */
	private function isSentenceCase( string $text ): bool {
		$plain = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES | ENT_HTML5 );
		$words = preg_split( '/[\s\x{00a0}\/]+/u', trim( $plain ), -1, PREG_SPLIT_NO_EMPTY ) ?: array();
		$start = true; // The next word starts a sentence.
		foreach ( $words as $word ) {
			$core = trim( $word, " \t.,:;!?()[]\"'—–-…" );
			if ( '' === $core || ! preg_match( '/^\p{L}/u', $core ) ) {
				continue; // A dash or a number is not a word.
			}
			if ( ! $start && ! in_array( $core, self::CAPITALS, true ) && preg_match( '/^\p{Lu}/u', $core ) ) {
				return false;
			}
			$start = (bool) preg_match( '/[.!?]$/', $word );
		}
		return true;
	}
}
