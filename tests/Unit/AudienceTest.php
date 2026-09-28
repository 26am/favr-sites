<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Dashboard\Audience;

final class AudienceTest extends TestCase {

	private function caps( array $caps ): void {
		Functions\when( 'user_can' )->alias( static fn( $user, string $cap ): bool => in_array( $cap, $caps, true ) );
	}

	public function test_editor_gets_the_favr_dashboard(): void {
		$this->caps( array( 'read', 'edit_pages' ) );
		$this->assertTrue( Audience::includes( new \WP_User( 5 ) ) );
	}

	public function test_administrator_keeps_the_stock_dashboard(): void {
		$this->caps( array( 'read', 'manage_options' ) );
		$this->assertFalse( Audience::includes( new \WP_User( 1 ) ) );
	}

	public function test_logged_out_or_null_user_is_excluded(): void {
		$this->caps( array( 'read' ) );
		$this->assertFalse( Audience::includes( null ) );
		$this->assertFalse( Audience::includes( new \WP_User( 0 ) ) );
	}

	public function test_filter_can_override(): void {
		$this->caps( array( 'read', 'manage_options' ) );
		Functions\when( 'apply_filters' )->alias( static fn( $hook, $value ) => 'favr_sites_dashboard_enabled' === $hook ? true : $value );
		$this->assertTrue( Audience::includes( new \WP_User( 1 ) ) );
	}
}
