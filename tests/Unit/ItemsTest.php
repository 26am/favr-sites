<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Dashboard\Items;

final class ItemsTest extends TestCase {

	private static function can( array $caps ): callable {
		return static fn( string $cap ): bool => in_array( $cap, $caps, true );
	}

	public function test_actions_are_filtered_sorted_and_deduped(): void {
		$raw = array(
			array( 'id' => 'b', 'label' => 'Bravo', 'url' => '/b', 'priority' => 20 ),
			array( 'id' => 'a', 'label' => 'Alpha', 'url' => '/a' ),
			array( 'id' => 'secret', 'label' => 'Secret', 'url' => '/s', 'capability' => 'manage_options' ),
			array( 'id' => 'b', 'label' => 'Bravo 2', 'url' => '/b2', 'priority' => 10 ),
			array( 'label' => 'No id', 'url' => '/x' ),
			'garbage',
		);
		$out = Items::actions( $raw, self::can( array( 'edit_posts' ) ) );
		$this->assertSame( array( 'b', 'a' ), array_column( $out, 'id' ) );
		$this->assertSame( 'Bravo 2', $out[0]['label'] );
		$this->assertSame( 50, $out[1]['priority'] );
		$this->assertSame( '', $out[1]['icon'] );
	}

	public function test_cards_cap_stats_and_items_and_drop_invalid_rows(): void {
		$card = array(
			'id'    => 'events',
			'title' => 'Events',
			'stats' => array(
				array( 'label' => 'upcoming', 'value' => 2 ),
				array( 'label' => 'b', 'value' => '3' ),
				array( 'label' => 'c', 'value' => 4 ),
				array( 'label' => 'd', 'value' => 5 ),
				array( 'label' => '', 'value' => 9 ),
			),
			'items' => array_fill( 0, 7, array( 'title' => 'Fall Happy Hour', 'meta' => 'Thu 15 Oct', 'url' => '/e' ) ),
			'link'  => array( 'label' => 'All events', 'url' => '/events' ),
			'empty' => array( 'text' => 'No events yet.' ),
		);
		$out  = Items::cards( array( $card ), self::can( array() ) );
		$this->assertCount( 1, $out );
		$this->assertCount( 3, $out[0]['stats'] );
		$this->assertSame( '2', $out[0]['stats'][0]['value'] );
		$this->assertCount( 5, $out[0]['items'] );
		$this->assertSame( array( 'label' => 'All events', 'url' => '/events' ), $out[0]['link'] );
		$this->assertSame( array( 'text' => 'No events yet.', 'label' => '', 'url' => '' ), $out[0]['empty'] );
	}

	public function test_card_callables_are_isolated(): void {
		$raw = array(
			static function (): array {
				throw new \RuntimeException( 'boom' );
			},
			static fn() => 'not an array',
			static fn(): array => array( 'id' => 'ok', 'title' => 'Fine' ),
			array( 'id' => 'hidden', 'title' => 'Hidden', 'capability' => 'edit_favr_members' ),
		);
		$out = Items::cards( $raw, self::can( array() ) );
		$this->assertSame( array( 'ok' ), array_column( $out, 'id' ) );
		$this->assertNull( $out[0]['link'] );
		$this->assertSame( array(), $out[0]['items'] );
	}
}
