<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Editors\AdminBar;

final class AdminBarTest extends TestCase {

	public function test_help_items_come_from_the_favr_links_and_skip_empty_ones(): void {
		Functions\when( 'esc_html' )->alias( static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES ) );
		Functions\when( 'esc_html__' )->returnArg();
		$items = AdminBar::helpItems(
			array(
				'help_url'      => '',
				'support_email' => 'care@favr.site',
				'support_phone' => '407-889-9987',
				'booking_url'   => 'https://favr.site/book',
			)
		);
		$this->assertSame( array( 'favr-email', 'favr-phone', 'favr-book' ), array_column( $items, 'id' ) );
		$this->assertSame( 'mailto:care@favr.site', $items[0]['href'] );
		$this->assertSame( 'tel:4078899987', $items[1]['href'] );
		$this->assertStringContainsString( '407-889-9987', $items[1]['title'] );
	}

	public function test_everything_under_the_wordpress_logo_goes(): void {
		$parents = array(
			'wp-logo'          => '',
			'wp-logo-default'  => 'wp-logo',
			'about'            => 'wp-logo-default',
			'contribute'       => 'wp-logo-default',
			'wp-logo-external' => 'wp-logo',
			'wporg'            => 'wp-logo-external',
			'feedback'         => 'wp-logo-external',
			'site-name'        => '',
			'view-site'        => 'site-name-default',
		);
		$this->assertSame( array( 'about', 'contribute', 'wp-logo-external', 'wporg', 'feedback' ), AdminBar::logoNodesToRemove( $parents ) );
	}

	public function test_new_menu_lists_everything_an_editor_can_add_in_the_dashboards_order(): void {
		$labels = array(
			'attachment'    => 'Media',
			'page'          => 'Page',
			'post'          => 'News post',
			'favr_sponsor'  => 'Sponsor',
			'favr_person'   => 'Person',
			'favr_document' => 'Document',
			'favr_business' => 'Directory listing',
			'favr_member'   => 'Member',
			'favr_event'    => 'Event',
		);
		$items  = AdminBar::newItems( $labels );
		$this->assertSame(
			array( 'Event', 'News post', 'Member', 'Directory listing', 'Document', 'Page', 'Person', 'Sponsor', 'Media' ),
			array_column( $items, 'title' )
		);
		$this->assertSame(
			array( 'new-favr_event', 'new-post', 'new-favr_member', 'new-favr_business', 'new-favr_document', 'new-page', 'new-favr_person', 'new-favr_sponsor', 'new-media' ),
			array_column( $items, 'id' )
		);
	}

	public function test_new_menu_follows_the_dashboards_quick_actions(): void {
		// Dashboard\Items::ACTION_ORDER names quick actions; these are the post types they add.
		$types   = array(
			'add-event'    => 'favr_event',
			'add-post'     => 'post',
			'add-member'   => 'favr_member',
			'add-listing'  => 'favr_business',
			'add-document' => 'favr_document',
			'edit-pages'   => 'page',
		);
		$actions = array_values( array_intersect( \FavrSites\Dashboard\Items::ACTION_ORDER, array_keys( $types ) ) );
		$this->assertSame(
			array_map( static fn( string $action ): string => $types[ $action ], $actions ),
			array_slice( AdminBar::NEW_ORDER, 0, count( $types ) )
		);
		$this->assertSame( 'attachment', AdminBar::NEW_ORDER[ count( AdminBar::NEW_ORDER ) - 1 ], 'Media comes last.' );
	}

	public function test_each_new_entry_opens_its_own_add_screen(): void {
		$items = AdminBar::newItems(
			array(
				'post'       => 'News post',
				'page'       => 'Page',
				'favr_event' => 'Event',
				'attachment' => 'Media',
			)
		);
		$this->assertSame(
			array(
				'new-favr_event' => 'post-new.php?post_type=favr_event',
				'new-post'       => 'post-new.php',
				'new-page'       => 'post-new.php?post_type=page',
				'new-media'      => 'media-new.php',
			),
			array_column( $items, 'path', 'id' )
		);
	}

	public function test_new_menu_skips_what_is_not_installed_or_not_allowed(): void {
		// The caller only passes the post types that exist and the user may add to.
		$items = AdminBar::newItems(
			array(
				'post' => 'News post',
				'page' => 'Page',
			)
		);
		$this->assertSame( array( 'News post', 'Page' ), array_column( $items, 'title' ) );
		$this->assertSame( array(), AdminBar::newItems( array() ) );
	}

	public function test_new_menu_ignores_post_types_favr_does_not_know(): void {
		$items = AdminBar::newItems(
			array(
				'elementor_library' => 'Template',
				'post'              => 'News post',
			)
		);
		$this->assertSame( array( 'new-post' ), array_column( $items, 'id' ) );
	}

	public function test_new_entries_read_in_sentence_case(): void {
		$items = AdminBar::newItems(
			array(
				'favr_business' => 'Directory Listing',
				'post'          => 'news post',
				'page'          => '  ',
			)
		);
		$this->assertSame( array( 'News post', 'Directory listing' ), array_column( $items, 'title' ), 'A type without a name is left out.' );
	}

	public function test_everything_wordpress_put_under_new_goes_before_favrs_entries_are_added(): void {
		$parents = array(
			'new-content'           => '',
			'new-content-default'   => 'new-content',
			'new-post'              => 'new-content',
			'new-media'             => 'new-content',
			'new-page'              => 'new-content-default',
			'new-elementor_library' => 'new-content',
			'new-favr_business'     => 'new-content',
			'new-user'              => 'new-content',
			'site-name'             => '',
			'view-site'             => 'site-name',
		);
		$this->assertSame(
			array( 'new-post', 'new-media', 'new-page', 'new-elementor_library', 'new-favr_business', 'new-user' ),
			AdminBar::newNodesToRemove( $parents )
		);
	}

	public function test_the_magnifier_is_named_search_and_says_what_it_finds(): void {
		Functions\when( 'esc_html' )->alias( static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES ) );
		$core = '<span class="ab-icon" aria-hidden="true"></span><span class="ab-label"><kbd>⌘K</kbd><span class="screen-reader-text"> Open command palette</span></span>';
		$this->assertSame(
			'<span class="ab-icon" aria-hidden="true"></span><span class="ab-label">Search <kbd>⌘K</kbd><span class="screen-reader-text"> Find a member, event or page</span></span>',
			AdminBar::searchTitle( $core, 'Search', 'Find a member, event or page' )
		);
	}

	public function test_the_magnifiers_words_are_escaped_and_its_shortcut_is_kept(): void {
		Functions\when( 'esc_html' )->alias( static fn( $v ) => htmlspecialchars( (string) $v, ENT_QUOTES ) );
		$title = AdminBar::searchTitle( '<span class="ab-label"><kbd>Ctrl+K</kbd><span class="screen-reader-text"> Open command palette</span></span>', 'A & B', 'Find <b>this</b> for $1' );
		$this->assertStringContainsString( 'A &amp; B <kbd>Ctrl+K</kbd>', $title );
		$this->assertStringContainsString( 'Find &lt;b&gt;this&lt;/b&gt; for $1', $title );
		$this->assertStringNotContainsString( 'Open command palette', $title );
	}

	public function test_a_magnifier_wordpress_wrote_differently_is_left_alone(): void {
		Functions\when( 'esc_html' )->returnArg();
		$this->assertSame( '<span class="ab-icon"></span>', AdminBar::searchTitle( '<span class="ab-icon"></span>', 'Search', 'Find a member, event or page' ) );
	}

	public function test_the_toolbar_says_view_site_like_the_dashboard_and_opens_a_new_tab(): void {
		Functions\when( 'esc_html__' )->returnArg();
		$node = AdminBar::viewSite();
		$this->assertSame( 'view-site', $node['id'] );
		$this->assertStringStartsWith( 'View site', $node['title'] );
		$this->assertStringContainsString( '<span class="screen-reader-text"> (opens in a new tab)</span>', $node['title'] );
		$this->assertSame( array( 'target' => '_blank' ), $node['meta'] );
	}

	public function test_name_prefers_first_name(): void {
		$this->assertSame( 'Anna', AdminBar::name( 'Anna', 'Anna Lee' ) );
		$this->assertSame( 'editor mcdondal', AdminBar::name( '', 'editor mcdondal' ) );
	}
}
