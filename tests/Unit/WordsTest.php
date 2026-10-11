<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Editors\Words;

final class WordsTest extends TestCase {

	/** Words that keep a capital inside a phrase: a place, names, abbreviations and keys. */
	private const CAPITALS = array( 'Trash', 'WordPress', 'Elementor', 'Favr', 'CSS', 'URL', 'Alt-Shift-H', 'Control-Option-H' );

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_json_encode' )->alias( 'json_encode' );
	}

	public function test_wordpress_title_case_reads_in_sentence_case(): void {
		$this->assertSame( 'Save draft', Words::plain( 'Save Draft', 'Save Draft', 'default' ) );
		$this->assertSame( 'Collapse menu', Words::plain( 'Collapse Menu', 'Collapse Menu', 'default' ) );
		$this->assertSame( 'Preview changes', Words::plain( 'Preview Changes', 'Preview Changes', 'default' ) );
		$this->assertSame( 'Delete permanently', Words::plain( 'Delete Permanently', 'Delete Permanently', 'default' ) );
		$this->assertSame( 'Attachment details', Words::plain( 'Attachment Details', 'Attachment Details', 'default' ) );
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
