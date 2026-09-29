<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Site\Protect;

final class ProtectTest extends TestCase {

	private const IDS = array( 'home' => 111, 'news' => 182, 'header' => 53, 'footer' => 55 );

	public function test_the_four_can_not_be_deleted(): void {
		foreach ( self::IDS as $id ) {
			$this->assertTrue( Protect::refusesCap( 'delete_post', $id, self::IDS ) );
			$this->assertTrue( Protect::refusesCap( 'delete_page', $id, self::IDS ) );
			$this->assertFalse( Protect::refusesCap( 'edit_post', $id, self::IDS ) );
		}
		$this->assertFalse( Protect::refusesCap( 'delete_post', 999, self::IDS ) );
	}

	public function test_the_four_stay_published(): void {
		foreach ( array( 'draft', 'pending', 'private', 'trash', 'future' ) as $status ) {
			$this->assertSame( 'publish', Protect::status( $status, 53, self::IDS ) );
		}
		$this->assertSame( 'draft', Protect::status( 'draft', 999, self::IDS ) );
	}

	public function test_roles_and_conditions(): void {
		$this->assertSame( 'header', Protect::role( 53, self::IDS ) );
		$this->assertNull( Protect::role( 7, self::IDS ) );
		$this->assertTrue( Protect::refusesMeta( '_elementor_conditions' ) );
		$this->assertFalse( Protect::refusesMeta( '_elementor_data' ) );
	}
}
