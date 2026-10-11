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

	public function test_quick_actions_follow_the_menu_whatever_their_priority(): void {
		$raw = array();
		foreach ( array(
			'zebra'        => 1,
			'banner'       => 22,
			'edit-footer'  => 21,
			'edit-header'  => 20,
			'edit-pages'   => 90,
			'add-document' => 40,
			'add-listing'  => 40,
			'add-member'   => 50,
			'add-post'     => 10,
			'add-event'    => 30,
			'apple'        => 99,
		) as $id => $priority ) {
			$raw[] = array( 'id' => $id, 'label' => ucfirst( $id ), 'url' => '/' . $id, 'priority' => $priority );
		}
		$this->assertSame(
			array( 'add-event', 'add-post', 'add-member', 'add-listing', 'add-document', 'edit-pages', 'edit-header', 'edit-footer', 'banner', 'zebra', 'apple' ),
			array_column( Items::actions( $raw, self::can( array() ) ), 'id' )
		);
	}

	public function test_cards_follow_the_menu_whatever_their_priority(): void {
		$raw = array();
		foreach ( array(
			'content'   => 40,
			'events'    => 30,
			'directory' => 20,
			'dues'      => 45,
			'members'   => 40,
			'weather'   => 5,
		) as $id => $priority ) {
			$raw[] = array( 'id' => $id, 'title' => ucfirst( $id ), 'priority' => $priority );
		}
		$this->assertSame(
			array( 'members', 'dues', 'directory', 'events', 'content', 'weather' ),
			array_column( Items::cards( $raw, self::can( array() ) ), 'id' )
		);
	}

	public function test_known_ids_come_first_in_the_given_order_and_the_rest_keep_theirs(): void {
		$rows = array(
			array( 'id' => 'x' ),
			array( 'id' => 'c' ),
			array( 'id' => 'y' ),
			array( 'id' => 'a' ),
			array( 'id' => 'z' ),
		);
		$this->assertSame( array( 'a', 'c', 'x', 'y', 'z' ), array_column( Items::inOrder( $rows, array( 'a', 'b', 'c' ) ), 'id' ) );
		$this->assertSame( array( 'x', 'c', 'y', 'a', 'z' ), array_column( Items::inOrder( $rows, array() ), 'id' ) );
		$this->assertSame( array(), Items::inOrder( array(), array( 'a' ) ) );
		$this->assertSame( array( 'a', 'a', 'x' ), array_column( Items::inOrder( array( array( 'id' => 'a' ), array( 'id' => 'x' ), array( 'id' => 'a' ) ), array( 'a' ) ), 'id' ), 'Nothing is dropped.' );
	}

	public function test_the_known_orders_list_each_id_once(): void {
		$this->assertSame( Items::ACTION_ORDER, array_values( array_unique( Items::ACTION_ORDER ) ) );
		$this->assertSame( Items::CARD_ORDER, array_values( array_unique( Items::CARD_ORDER ) ) );
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
