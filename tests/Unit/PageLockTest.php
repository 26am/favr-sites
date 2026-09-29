<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Editors\PageLock;

final class PageLockTest extends TestCase {

	public function test_only_existing_non_elementor_pages_are_locked_when_elementor_runs(): void {
		$this->assertTrue( PageLock::isLocked( 'page', 'publish', false, true ) );
		$this->assertTrue( PageLock::isLocked( 'page', 'draft', false, true ) );
		$this->assertFalse( PageLock::isLocked( 'page', 'publish', true, true ) );    // built with Elementor
		$this->assertFalse( PageLock::isLocked( 'page', 'auto-draft', false, true ) ); // being created
		$this->assertFalse( PageLock::isLocked( 'post', 'publish', false, true ) );    // posts never
		$this->assertFalse( PageLock::isLocked( 'page', 'publish', false, false ) );   // Elementor off
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
}
