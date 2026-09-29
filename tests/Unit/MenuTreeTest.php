<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Menus\Tree;

final class MenuTreeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Functions\when( 'wp_parse_url' )->alias( static fn( $url, $component = -1 ) => parse_url( (string) $url, $component ) );
		Functions\when( 'esc_url_raw' )->alias(
			static function ( $url, $protocols = null ) {
				$scheme = strtolower( (string) parse_url( (string) $url, PHP_URL_SCHEME ) );
				return ( $protocols && ! in_array( $scheme, $protocols, true ) ) ? '' : (string) $url;
			}
		);
	}

	private static function item( int $id, int $parent, int $order, string $title, string $type = 'post_type', string $object = 'page', int $object_id = 0, string $url = '' ): object {
		return (object) array(
			'ID'               => $id,
			'menu_item_parent' => (string) $parent,
			'menu_order'       => $order,
			'title'            => $title,
			'url'              => $url,
			'type'             => $type,
			'object'           => $object,
			'object_id'        => $object_id,
		);
	}

	public function test_rows_are_ordered_with_levels_from_parents(): void {
		$rows = Tree::rows(
			array(
				self::item( 3, 1, 2, 'Board' ),
				self::item( 1, 0, 1, 'About', 'post_type', 'page', 10 ),
				self::item( 4, 3, 3, 'Past boards' ),
				self::item( 5, 0, 4, 'Directory', 'custom', 'custom', 0, '/directory/' ),
				self::item( 6, 99, 5, 'Orphan', 'taxonomy', 'category', 7 ),
			)
		);
		$this->assertSame( array( 1, 3, 4, 5, 6 ), array_column( $rows, 'id' ) );
		$this->assertSame( array( 0, 1, 2, 0, 0 ), array_column( $rows, 'level' ) );
		$this->assertSame( array( 'page', 'page', 'page', 'custom', 'other' ), array_column( $rows, 'type' ) );
	}

	public function test_plan_reorders_renames_nests_adds_and_deletes(): void {
		$existing = Tree::rows( array( self::item( 1, 0, 1, 'About', 'post_type', 'page', 10 ), self::item( 2, 0, 2, 'Events', 'post_type', 'page', 11 ), self::item( 3, 0, 3, 'Old', 'custom', 'custom', 0, '/old/' ) ) );
		$plan     = Tree::plan(
			$existing,
			array(
				array( 'id' => 2, 'level' => 0, 'title' => 'Calendar' ),
				array( 'id' => 1, 'level' => 1, 'title' => 'About' ),
				array( 'id' => 0, 'level' => 0, 'title' => 'Directory', 'type' => 'custom', 'url' => '/directory/' ),
				array( 'id' => 0, 'level' => 1, 'title' => '', 'type' => 'page', 'object_id' => 12 ),
			)
		);
		$this->assertSame( array(), $plan['errors'] );
		$this->assertSame( array( 3 ), $plan['delete'] );
		$this->assertSame( array( '2', '1', 'new:1', 'new:2' ), array_column( $plan['rows'], 'ref' ) );
		$this->assertSame( array( '', '2', '', 'new:1' ), array_column( $plan['rows'], 'parent_ref' ) );
		$this->assertSame( array( 1, 2, 3, 4 ), array_column( $plan['rows'], 'position' ) );
		$this->assertSame( 'Calendar', $plan['rows'][0]['title'] );
		$this->assertSame( 12, $plan['rows'][3]['object_id'] );
	}

	public function test_depth_is_one_dropdown_level_but_existing_deeper_items_are_kept(): void {
		$existing = Tree::rows( array( self::item( 1, 0, 1, 'A' ), self::item( 2, 1, 2, 'B' ), self::item( 3, 2, 3, 'C' ) ) );
		$plan     = Tree::plan(
			$existing,
			array(
				array( 'id' => 0, 'level' => 3, 'title' => 'First', 'type' => 'custom', 'url' => 'https://example.com' ),
				array( 'id' => 1, 'level' => 2, 'title' => 'A' ),
				array( 'id' => 2, 'level' => 1, 'title' => 'B' ),
				array( 'id' => 3, 'level' => 2, 'title' => 'C' ),
			)
		);
		$this->assertSame( array( 0, 1, 1, 2 ), array_column( $plan['rows'], 'level' ) );
		$this->assertSame( array( '', 'new:1', 'new:1', '2' ), array_column( $plan['rows'], 'parent_ref' ) );
	}

	public function test_foreign_ids_are_ignored_and_bad_links_are_errors(): void {
		$existing = Tree::rows( array( self::item( 1, 0, 1, 'A' ) ) );
		$plan     = Tree::plan(
			$existing,
			array(
				array( 'id' => 1, 'level' => 0, 'title' => '<b>A</b>' ),
				array( 'id' => 999, 'level' => 0, 'title' => 'Not ours' ),
				array( 'id' => 0, 'level' => 0, 'title' => 'Evil', 'type' => 'custom', 'url' => 'javascript:alert(1)' ),
				array( 'id' => 0, 'level' => 0, 'title' => '', 'type' => 'custom', 'url' => 'https://example.com' ),
				'garbage',
			)
		);
		$this->assertSame( array( '1' ), array_column( $plan['rows'], 'ref' ) );
		$this->assertSame( 'A', $plan['rows'][0]['title'] );
		$this->assertCount( 2, $plan['errors'] );
	}

	public function test_clean_url(): void {
		$this->assertSame( 'https://example.com/a', Tree::cleanUrl( 'https://example.com/a' ) );
		$this->assertSame( '/directory/', Tree::cleanUrl( '/directory/' ) );
		$this->assertSame( 'mailto:care@favr.site', Tree::cleanUrl( 'mailto:care@favr.site' ) );
		$this->assertSame( '', Tree::cleanUrl( 'javascript:alert(1)' ) );
		$this->assertSame( '', Tree::cleanUrl( '//evil.example' ) );
		$this->assertSame( '', Tree::cleanUrl( '/path with "quote"' ) );
	}

	public function test_unplaced_pages(): void {
		$pages = array(
			array( 'id' => 10, 'title' => 'About' ),
			array( 'id' => 12, 'title' => 'Join' ),
		);
		$this->assertSame( array( array( 'id' => 12, 'title' => 'Join' ) ), Tree::unplacedPages( $pages, array( 10 ) ) );
	}
}
