<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Site\Setup;

final class SetupTest extends TestCase {

	public function test_a_failure_while_repairing_never_breaks_wp_admin(): void {
		Functions\when( 'wp_doing_ajax' )->justReturn( false );
		Functions\when( 'current_user_can' )->justReturn( true );
		Functions\when( 'get_option' )->alias(
			static function () {
				throw new \RuntimeException( 'Elementor changed an API' );
			}
		);
		( new Setup() )->maybeRun();
		$this->addToAssertionCount( 1 ); // Reaching here means nothing escaped to wp-admin.
	}
}
