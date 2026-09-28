<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Dashboard\Attention;

final class AttentionTest extends TestCase {

	public function test_counts_only_what_the_user_can_review(): void {
		$none      = static fn(): array => array();
		$providers = array(
			array( 'id' => 'listings', 'label' => 'Listing updates', 'capability' => 'edit_others_favr_businesses', 'count' => static fn(): int => 3, 'items' => $none ),
			array( 'id' => 'claims', 'label' => 'Claims', 'capability' => 'edit_others_favr_businesses', 'items' => static fn(): array => array( 1 ) ),
			array( 'id' => 'zero', 'label' => 'Nothing', 'capability' => 'edit_others_favr_businesses', 'count' => static fn(): int => 0, 'items' => $none ),
			array( 'id' => 'members', 'label' => 'Applications', 'capability' => 'edit_others_favr_members', 'count' => static fn(): int => 9, 'items' => $none ),
			array( 'id' => 'nocap', 'label' => 'Admin only', 'count' => static fn(): int => 4, 'items' => $none ),
			array(
				'id'         => 'broken',
				'label'      => 'Broken',
				'capability' => 'edit_others_favr_businesses',
				'count'      => static function (): int {
					throw new \RuntimeException( 'db down' );
				},
				'items'      => $none,
			),
			'garbage',
		);
		$can = static fn( string $cap ): bool => 'edit_others_favr_businesses' === $cap;
		$this->assertSame(
			array(
				array( 'id' => 'listings', 'label' => 'Listing updates', 'count' => 3 ),
				array( 'id' => 'claims', 'label' => 'Claims', 'count' => 1 ),
			),
			Attention::queues( $providers, $can )
		);
	}
}
