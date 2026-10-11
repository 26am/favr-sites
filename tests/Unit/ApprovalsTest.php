<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Editors\Approvals;

final class ApprovalsTest extends TestCase {

	public function test_the_script_is_for_editors_on_the_approvals_screen_only(): void {
		$this->assertTrue( Approvals::wanted( true, 'favr-approvals' ) );
		$this->assertFalse( Approvals::wanted( false, 'favr-approvals' ), 'An Administrator keeps the stock screen.' );
		$this->assertFalse( Approvals::wanted( true, 'favr-menus' ) );
		$this->assertFalse( Approvals::wanted( true, '' ) );
	}

	public function test_it_waits_for_the_screen_to_load_its_scripts(): void {
		( new Approvals() )->hook();
		$this->assertNotFalse( has_action( 'admin_enqueue_scripts', 'FavrSites\Editors\Approvals->assets()' ) );
	}

	public function test_reject_asks_with_and_without_a_note(): void {
		$words = Approvals::words();
		$this->assertStringContainsString( '%s', $words['withNote'] );
		$this->assertStringContainsString( 'note is sent to the person', $words['withNote'] );
		$this->assertStringContainsString( '%s', $words['noNote'] );
		$this->assertStringContainsString( 'without a note', $words['noNote'] );
	}
}
