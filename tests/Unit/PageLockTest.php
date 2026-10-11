<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Dashboard\Audience;
use FavrSites\Editors\PageLock;

final class PageLockTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'get_option' )->justReturn( 0 ); // No posts page.
		Functions\when( 'esc_html__' )->returnArg();
		Functions\when( 'is_admin' )->justReturn( true );
		Functions\when( 'esc_attr__' )->returnArg();
		Audience::flush();
	}

	protected function tearDown(): void {
		Audience::flush();
		parent::tearDown();
	}

	/** A page not built with Elementor, while Elementor runs, seen by an Editor or by an Administrator. */
	private function lockedPageSeenBy( string $role ): \WP_Post {
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'did_action' )->justReturn( 1 );
		Functions\when( 'wp_get_current_user' )->justReturn( new \WP_User( 'editor' === $role ? 456 : 1, array( $role ) ) );
		Functions\when( 'user_can' )->justReturn( 'editor' !== $role );
		return new \WP_Post( array( 'ID' => 10, 'post_type' => 'page', 'post_status' => 'publish' ) );
	}

	public function test_only_existing_non_elementor_pages_are_locked_when_elementor_runs(): void {
		$this->assertTrue( PageLock::isLocked( 'page', 'publish', false, true ) );
		$this->assertTrue( PageLock::isLocked( 'page', 'draft', false, true ) );
		$this->assertFalse( PageLock::isLocked( 'page', 'publish', true, true ) );    // built with Elementor
		$this->assertFalse( PageLock::isLocked( 'page', 'auto-draft', false, true ) ); // being created
		$this->assertFalse( PageLock::isLocked( 'post', 'publish', false, true ) );    // block-editor News
		$this->assertTrue( PageLock::isLocked( 'post', 'publish', true, true ) );      // News built in Elementor
		$this->assertFalse( PageLock::isLocked( 'post', 'publish', true, false ) );    // …unless Elementor is off
		$this->assertFalse( PageLock::isLocked( 'favr_event', 'publish', false, true ) );
		$this->assertFalse( PageLock::isLocked( 'page', 'publish', false, false ) );   // Elementor off
		$this->assertTrue( PageLock::isLocked( 'page', 'publish', true, true, true ) );    // The News (posts) page, even if built with Elementor.
		$this->assertFalse( PageLock::isLocked( 'page', 'publish', true, false, true ) );  // …unless Elementor is off.
	}

	public function test_editor_is_denied_every_meta_cap_elementor_and_core_use_on_a_locked_page(): void {
		Functions\when( 'get_post' )->justReturn( new \WP_Post( array( 'ID' => 24695, 'post_type' => 'page', 'post_status' => 'publish' ) ) );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'did_action' )->justReturn( 1 );
		Functions\when( 'get_userdata' )->justReturn( new \WP_User( 19, array( 'editor' ) ) );
		Functions\when( 'user_can' )->alias( static fn( $user, string $cap ): bool => 'read' === $cap );
		$lock = new PageLock();
		foreach ( array( 'edit_post', 'edit_page', 'delete_post', 'delete_page', 'publish_post' ) as $cap ) {
			$this->assertSame( array( 'do_not_allow' ), $lock->caps( array( 'edit_pages' ), $cap, 19, array( 24695 ) ), $cap );
		}
		$this->assertSame( array( 'read' ), $lock->caps( array( 'read' ), 'read_post', 19, array( 24695 ) ) );
	}

	public function test_a_post_object_argument_is_checked_as_that_post(): void {
		$locked = new \WP_Post( array( 'ID' => 24695, 'post_type' => 'page', 'post_status' => 'publish' ) );
		$other  = new \WP_Post( array( 'ID' => 1, 'post_type' => 'post', 'post_status' => 'publish' ) );
		Functions\when( 'get_post' )->alias( static fn( $p ) => $p instanceof \WP_Post ? $p : ( 24695 === $p ? $locked : $other ) );
		Functions\when( 'get_post_meta' )->justReturn( '' );
		Functions\when( 'did_action' )->justReturn( 1 );
		Functions\when( 'get_userdata' )->justReturn( new \WP_User( 19, array( 'editor' ) ) );
		Functions\when( 'user_can' )->justReturn( false );
		$this->assertSame( array( 'do_not_allow' ), ( new PageLock() )->caps( array( 'edit_pages' ), 'edit_post', 19, array( $locked ) ) );
	}

	public function test_the_pill_of_a_locked_page_says_why_when_pointed_at(): void {
		$page   = $this->lockedPageSeenBy( 'editor' );
		$states = ( new PageLock() )->states( array(), $page );
		$this->assertSame( array( 'favr_managed' ), array_keys( $states ) );
		$this->assertStringContainsString( '>Managed by Favr<', $states['favr_managed'] );
		$this->assertStringContainsString( 'title="This page is built by Favr. Ask Favr to change it."', $states['favr_managed'] );
		$this->assertSame( PageLock::WHY, 'This page is built by Favr. Ask Favr to change it.' );
	}

	public function test_a_locked_row_is_marked_so_its_title_can_be_drawn_quieter(): void {
		$page = $this->lockedPageSeenBy( 'editor' );
		Functions\when( 'get_post' )->justReturn( $page );
		$this->assertSame( array( 'type-page', 'favr-locked' ), ( new PageLock() )->rowClass( array( 'type-page' ), array(), 10 ) );
	}

	public function test_a_page_the_editor_can_open_is_not_marked(): void {
		$this->lockedPageSeenBy( 'editor' );
		Functions\when( 'get_post_meta' )->justReturn( 'builder' );
		$open = new \WP_Post( array( 'ID' => 159, 'post_type' => 'page', 'post_status' => 'publish' ) );
		Functions\when( 'get_post' )->justReturn( $open );
		$lock = new PageLock();
		$this->assertSame( array( 'type-page' ), $lock->rowClass( array( 'type-page' ), array(), 159 ) );
		$this->assertSame( array(), $lock->states( array(), $open ) );
	}

	public function test_an_administrator_sees_no_pill_and_no_mark(): void {
		$page = $this->lockedPageSeenBy( 'administrator' );
		Functions\when( 'get_post' )->justReturn( $page );
		$lock = new PageLock();
		$this->assertSame( array(), $lock->states( array(), $page ) );
		$this->assertSame( array( 'type-page' ), $lock->rowClass( array( 'type-page' ), array(), 10 ) );
	}
}
