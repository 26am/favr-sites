<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use FavrSites\Dashboard\Audience;
use FavrSites\Editors\Chrome;

require_once __DIR__ . '/stubs/wp-screen.php';

final class ChromeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Audience::flush();
	}

	protected function tearDown(): void {
		Audience::flush();
		parent::tearDown();
	}

	/** Sign in as an Editor (true) or an Administrator (false), on a screen. */
	private function signIn( bool $editor, ?\WP_Screen $screen = null ): void {
		Functions\when( 'wp_get_current_user' )->justReturn( $editor ? new \WP_User( 456, array( 'editor' ) ) : new \WP_User( 1, array( 'administrator' ) ) );
		Functions\when( 'user_can' )->alias( static fn( $user, string $cap ): bool => 1 === $user->ID );
		Functions\when( 'get_current_screen' )->justReturn( $screen );
	}

	private function editScreen( string $post_type, bool $block_editor = false ): \WP_Screen {
		return new \WP_Screen(
			array(
				'id'           => $post_type,
				'base'         => 'post',
				'post_type'    => $post_type,
				'block_editor' => $block_editor,
			)
		);
	}

	public function test_the_boxes_editors_lose_on_every_edit_screen(): void {
		$this->assertSame(
			array( 'authordiv', 'slugdiv', 'pageparentdiv', 'postcustom', 'commentstatusdiv', 'commentsdiv', 'trackbacksdiv', 'revisionsdiv' ),
			Chrome::BOXES
		);
	}

	public function test_the_summary_box_stays(): void {
		// Favr Events uses WordPress's excerpt box for an event's short summary.
		$this->assertNotContains( 'postexcerpt', Chrome::BOXES );
		$this->assertNotContains( 'postexcerpt', array_column( Chrome::removals(), 0 ) );
	}

	public function test_every_box_is_removed_wherever_it_was_put(): void {
		$removals = Chrome::removals();
		$this->assertCount( count( Chrome::BOXES ) * 3, $removals );
		foreach ( Chrome::BOXES as $box ) {
			foreach ( array( 'normal', 'advanced', 'side' ) as $context ) {
				$this->assertContains( array( $box, $context ), $removals );
			}
		}
	}

	public function test_editors_have_no_screen_options(): void {
		$this->assertFalse( Chrome::showsScreenOptions( true, true ) );
		$this->assertFalse( Chrome::showsScreenOptions( false, true ) );
	}

	public function test_everybody_else_keeps_screen_options(): void {
		$this->assertTrue( Chrome::showsScreenOptions( true, false ) );
		$this->assertFalse( Chrome::showsScreenOptions( false, false ) );
	}

	public function test_nothing_an_editor_once_hid_stays_hidden_without_a_way_back(): void {
		$this->assertSame( array(), Chrome::hidden( array( 'postexcerpt', 'favr_event_details' ), true ) );
		$this->assertSame( array(), Chrome::hidden( false, true ) );
	}

	public function test_everybody_else_keeps_what_they_hid(): void {
		$this->assertSame( array( 'author' ), Chrome::hidden( array( 'author' ), false ) );
		$this->assertSame( array(), Chrome::hidden( 'broken', false ) );
	}

	public function test_the_permalink_can_still_be_edited_without_the_slug_box(): void {
		// WordPress keeps the slug field inside the Slug box; without the box the field is printed on its own.
		$this->assertTrue( Chrome::keepsSlugField( 'publish', true ) );
		$this->assertTrue( Chrome::keepsSlugField( 'draft', false ) );
		$this->assertTrue( Chrome::keepsSlugField( 'pending', true ) );
	}

	public function test_a_pending_item_keeps_its_slug_when_its_author_cannot_publish(): void {
		// The same rule WordPress uses for showing the Slug box.
		$this->assertFalse( Chrome::keepsSlugField( 'pending', false ) );
	}

	public function test_the_slug_box_counts_as_present_only_while_it_is_registered(): void {
		$boxes = array(
			'normal' => array(
				'core' => array(
					'slugdiv'     => array( 'id' => 'slugdiv' ),
					'postexcerpt' => array( 'id' => 'postexcerpt' ),
				),
			),
		);
		$this->assertTrue( Chrome::hasBox( $boxes, 'slugdiv' ) );
		$boxes['normal']['core']['slugdiv'] = false; // What remove_meta_box() leaves behind.
		$this->assertFalse( Chrome::hasBox( $boxes, 'slugdiv' ) );
		$this->assertFalse( Chrome::hasBox( array(), 'slugdiv' ) );
		$this->assertFalse( Chrome::hasBox( null, 'slugdiv' ) );
	}

	public function test_the_edit_script_is_for_editors_on_classic_edit_screens_only(): void {
		$this->assertTrue( Chrome::loadsEditScript( 'post', false, 'edit', true ) );
		$this->assertTrue( Chrome::loadsEditScript( 'post', false, '', true ) ); // post-new.php.
		$this->assertFalse( Chrome::loadsEditScript( 'post', false, 'edit', false ), 'not for Administrators' );
		$this->assertFalse( Chrome::loadsEditScript( 'post', true, 'edit', true ), 'not in the block editor' );
		$this->assertFalse( Chrome::loadsEditScript( 'post', false, 'elementor', true ), 'not in Elementor' );
		$this->assertFalse( Chrome::loadsEditScript( 'edit', false, '', true ), 'not on lists' );
	}

	public function test_the_edit_script_exists_on_disk(): void {
		$this->assertFileExists( dirname( __DIR__, 2 ) . '/' . Chrome::SCRIPT_FILE );
	}

	public function test_a_box_an_editor_once_folded_or_moved_is_back_where_it_belongs(): void {
		// WordPress reads "nothing closed, default order" from false.
		$this->assertFalse( Chrome::savedBoxState( array( 'submitdiv' ), true ) );
		$this->assertFalse( Chrome::savedBoxState( array( 'side' => 'favr_business_catdiv,submitdiv' ), true ) );
		$this->assertSame( array( 'submitdiv' ), Chrome::savedBoxState( array( 'submitdiv' ), false ) );
	}

	public function test_favr_record_types_are_recognised(): void {
		foreach ( array( 'favr_business', 'favr_event', 'favr_member', 'favr_document', 'favr_person', 'favr_sponsor' ) as $type ) {
			$this->assertTrue( Chrome::isRecordType( $type ), $type );
		}
		foreach ( array( 'post', 'page', 'attachment', '', 'unfavr_thing' ) as $type ) {
			$this->assertFalse( Chrome::isRecordType( $type ), $type );
		}
	}

	public function test_a_record_description_has_no_code_tab_or_tag_buttons(): void {
		$settings = array(
			'quicktags' => true,
			'tinymce'   => array( 'resize' => false ),
		);
		$this->assertFalse( Chrome::plainEditor( $settings, 'favr_event' )['quicktags'] );
		$this->assertSame( array( 'resize' => false ), Chrome::plainEditor( $settings, 'favr_event' )['tinymce'] );
	}

	public function test_a_media_description_is_a_plain_text_box(): void {
		$settings = array(
			'tinymce'   => false,
			'quicktags' => array( 'buttons' => 'strong,em,link' ),
		);
		$this->assertFalse( Chrome::plainEditor( $settings, 'attachment' )['quicktags'] );
		$this->assertFalse( Chrome::plainEditor( $settings, 'attachment' )['tinymce'] );
	}

	public function test_news_keeps_its_code_tab(): void {
		$this->assertTrue( Chrome::plainEditor( array( 'quicktags' => true ), 'post' )['quicktags'] );
		$this->assertTrue( Chrome::plainEditor( array( 'quicktags' => true ), 'page' )['quicktags'] );
	}

	public function test_a_record_has_no_read_more_button(): void {
		$buttons = array( 'bold', 'italic', 'wp_more', 'link' );
		$this->assertSame( array( 'bold', 'italic', 'link' ), Chrome::withoutReadMore( $buttons, 'favr_business' ) );
		$this->assertSame( $buttons, Chrome::withoutReadMore( $buttons, 'post' ), 'News can still be cut with Read more' );
	}

	public function test_the_text_in_the_editor_is_favr_type(): void {
		$css = Chrome::contentStyle( 'http://x.test/wp-content/plugins/favr-sites/assets/fonts/source-sans-3.woff2' );
		$this->assertStringContainsString( "font-family:'Favr Sans'", $css );
		$this->assertStringContainsString( "url('http://x.test/wp-content/plugins/favr-sites/assets/fonts/source-sans-3.woff2')", $css );
		$this->assertStringContainsString( 'color:#2e2230', $css );
		$this->assertStringContainsString( 'a{color:#a9472a', $css );
	}

	public function test_the_editor_style_survives_wordpress_wrapping_it_in_double_quotes(): void {
		// WordPress prints the value as key:"value" without escaping it.
		$css = Chrome::contentStyle( 'http://x.test/a"b' . "\n" . 'c.woff2' );
		$this->assertStringNotContainsString( '"', $css );
		$this->assertStringNotContainsString( "\n", $css );
		$this->assertStringNotContainsString( '\\', $css );
	}

	public function test_the_editor_style_is_added_to_what_is_there(): void {
		$this->assertSame( array( 'content_style' => 'p{margin:0}' ), Chrome::withContentStyle( array(), 'p{margin:0}' ) );
		$this->assertSame( array( 'content_style' => 'b{color:red} p{margin:0}' ), Chrome::withContentStyle( array( 'content_style' => 'b{color:red}' ), 'p{margin:0}' ) );
	}

	public function test_visibility_is_hidden_where_it_says_nothing_true(): void {
		$this->assertSame( array( 'favr_document', 'favr_member' ), Chrome::NO_VISIBILITY );
		$this->assertTrue( Chrome::hidesVisibility( 'favr_document', Chrome::NO_VISIBILITY ) );
		$this->assertTrue( Chrome::hidesVisibility( 'favr_member', Chrome::NO_VISIBILITY ) );
		$this->assertFalse( Chrome::hidesVisibility( 'favr_event', Chrome::NO_VISIBILITY ) );
		$this->assertFalse( Chrome::hidesVisibility( 'favr_document', 'broken' ), 'a filter that returns nonsense hides nothing' );
		$this->assertTrue( Chrome::hidesVisibility( 'favr_event', array( 'favr_event' ) ), 'a site can add a type through the filter' );
	}

	public function test_nothing_happens_for_an_administrator(): void {
		$this->signIn( false, $this->editScreen( 'favr_event' ) );
		$chrome = new Chrome();

		Filters\expectAdded( 'get_user_option_screen_layout_favr_event' )->never();
		Filters\expectAdded( 'get_user_option_closedpostboxes_favr_event' )->never();
		Filters\expectAdded( 'get_user_option_meta-box-order_favr_event' )->never();
		Functions\expect( 'remove_meta_box' )->never();
		Functions\expect( 'wp_enqueue_script' )->never();
		Functions\expect( 'wp_add_inline_style' )->never();

		$screen = $this->editScreen( 'favr_event' );
		$chrome->screen( $screen );
		$chrome->help();
		$chrome->boxes();
		$chrome->assets();
		$this->expectOutputString( '' );
		$chrome->slugField( new \WP_Post( array( 'post_type' => 'favr_event' ) ) );

		$this->assertTrue( $chrome->screenOptions( true ) );
		$this->assertSame( array( 'postexcerpt' ), $chrome->hiddenBoxes( array( 'postexcerpt' ), $screen ) );
		$this->assertSame( array( 'author' ), $chrome->hiddenColumns( array( 'author' ) ) );
		$this->assertSame( array( 'quicktags' => true ), $chrome->editorSettings( array( 'quicktags' => true ), 'content' ) );
		$this->assertSame( array( 'bold', 'wp_more' ), $chrome->editorButtons( array( 'bold', 'wp_more' ), 'content' ) );
		$this->assertSame( array( 'selector' => '#content' ), $chrome->editorLook( array( 'selector' => '#content' ), 'content' ) );
	}

	public function test_an_editor_gets_calm_boxes_and_a_plain_description(): void {
		$screen = $this->editScreen( 'favr_event' );
		$this->signIn( true, $screen );
		$chrome = new Chrome();

		Filters\expectAdded( 'get_user_option_screen_layout_favr_event' )->once();
		Filters\expectAdded( 'get_user_option_closedpostboxes_favr_event' )->once();
		Filters\expectAdded( 'get_user_option_meta-box-order_favr_event' )->once();
		$chrome->screen( $screen );

		$this->assertFalse( $chrome->screenOptions( true ) );
		$this->assertFalse( $chrome->editorSettings( array( 'quicktags' => true ), 'content' )['quicktags'] );
		$this->assertSame( array( 'bold' ), $chrome->editorButtons( array( 'bold', 'wp_more' ), 'content' ) );
	}

	public function test_the_block_editor_keeps_its_own_editor_settings_for_an_editor(): void {
		$this->signIn( true, $this->editScreen( 'favr_event', true ) );
		$chrome = new Chrome();
		Functions\expect( 'wp_enqueue_script' )->never();
		$chrome->assets();
		$this->assertTrue( $chrome->editorSettings( array( 'quicktags' => true ), 'content' )['quicktags'] );
	}
}
