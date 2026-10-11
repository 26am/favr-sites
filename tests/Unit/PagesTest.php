<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Editors\Pages;

final class PagesTest extends TestCase {

	public function test_a_name_is_plain_text_on_one_line(): void {
		$this->assertSame( 'Board of directors', Pages::cleanName( "  Board   of\n directors " ) );
		$this->assertSame( 'Join us', Pages::cleanName( '<b>Join</b> us' ) );
		$this->assertSame( '', Pages::cleanName( "  \t " ) );
	}

	public function test_a_name_is_cut_at_a_sensible_length(): void {
		$this->assertSame( Pages::NAME_MAX, strlen( Pages::cleanName( str_repeat( 'a', 500 ) ) ) );
	}

	public function test_the_address_comes_from_what_was_typed_or_else_from_the_name(): void {
		$slug = static fn( string $text ): string => strtolower( trim( (string) preg_replace( '/[^a-z0-9]+/i', '-', $text ), '-' ) );
		$this->assertSame( 'about-us', Pages::cleanAddress( ' About Us ', 'Who we are', $slug ) );
		$this->assertSame( 'who-we-are', Pages::cleanAddress( '', 'Who we are', $slug ) );
		$this->assertSame( 'who-we-are', Pages::cleanAddress( ' /// ', 'Who we are', $slug ) );
		$this->assertSame( 'board', Pages::cleanAddress( 'https://x.test/about/board/', 'Board', $slug ), 'A pasted address keeps its last part.' );
	}

	public function test_rename_is_offered_on_pages_the_editor_may_edit_and_never_on_locked_ones(): void {
		$this->assertTrue( Pages::offersRename( 'page', true, false ) );
		$this->assertFalse( Pages::offersRename( 'page', false, false ) );
		$this->assertFalse( Pages::offersRename( 'page', true, true ) );
		$this->assertFalse( Pages::offersRename( 'post', true, false ) );
	}

	public function test_rename_comes_first_under_a_page(): void {
		$actions = Pages::withRename(
			array(
				'view'  => '<a>View</a>',
				'trash' => '<a>Trash</a>',
			),
			'<a>Rename</a>'
		);
		$this->assertSame( array( Pages::RENAME, 'view', 'trash' ), array_keys( $actions ) );
		$this->assertSame( 'favr-rename', Pages::RENAME );
	}

	public function test_what_went_wrong_is_said_in_plain_words(): void {
		$this->assertSame( '', Pages::problem( '' ) );
		$this->assertSame( '', Pages::problem( 'nonsense' ) );
		$this->assertNotSame( '', Pages::problem( 'name' ) );
		$this->assertNotSame( '', Pages::problem( 'failed' ) );
	}

	public function test_a_name_the_builder_made_up_is_recognised(): void {
		$this->assertTrue( Pages::isBuilderName( 'Elementor #4413' ) );
		$this->assertTrue( Pages::isBuilderName( 'Elementor Page #4449' ) );
		$this->assertFalse( Pages::isBuilderName( 'About Elementor #1' ) );
		$this->assertFalse( Pages::isBuilderName( 'Board of directors' ) );
		$this->assertFalse( Pages::isBuilderName( '' ) );
	}

	// ---------- Who may ----------

	public function test_an_administrator_gets_no_rename_under_a_page(): void {
		\Brain\Monkey\Functions\when( 'wp_get_current_user' )->justReturn( new \WP_User( 1, array( 'administrator' ) ) );
		\Brain\Monkey\Functions\when( 'user_can' )->justReturn( true );
		\Brain\Monkey\Functions\when( 'did_action' )->justReturn( 1 );
		\FavrSites\Dashboard\Audience::flush();
		$actions = array( 'view' => '<a href="/about/">View</a>' );

		$out = ( new Pages() )->rowAction(
			$actions,
			new \WP_Post(
				array(
					'ID'        => 7,
					'post_type' => 'page',
				)
			)
		);

		\FavrSites\Dashboard\Audience::flush();
		$this->assertSame( $actions, $out );
	}

	public function test_adding_a_page_checks_where_the_request_came_from_before_anything_else(): void {
		\Brain\Monkey\Functions\expect( 'check_admin_referer' )->once()->andThrow( new \RuntimeException( 'no nonce' ) );
		\Brain\Monkey\Functions\expect( 'current_user_can' )->never();
		\Brain\Monkey\Functions\expect( 'wp_insert_post' )->never();

		$this->expectExceptionMessage( 'no nonce' );
		( new Pages() )->add();
	}

	public function test_somebody_who_may_not_edit_pages_cannot_add_one(): void {
		\Brain\Monkey\Functions\when( 'check_admin_referer' )->justReturn( 1 );
		\Brain\Monkey\Functions\when( 'current_user_can' )->justReturn( false );
		\Brain\Monkey\Functions\when( 'esc_html__' )->returnArg();
		\Brain\Monkey\Functions\when( 'wp_die' )->alias(
			static function (): void {
				throw new \RuntimeException( 'refused' );
			}
		);
		\Brain\Monkey\Functions\expect( 'wp_insert_post' )->never();
		$_POST['page_name'] = 'Board';

		try {
			( new Pages() )->add();
			$this->fail( 'The page was added.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'refused', $e->getMessage() );
		} finally {
			unset( $_POST['page_name'] );
		}
	}

	public function test_a_page_is_renamed_only_after_the_request_and_the_right_to_edit_it_are_checked(): void {
		$_POST['post'] = '7';
		\Brain\Monkey\Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
		\Brain\Monkey\Functions\when( 'check_admin_referer' )->justReturn( 1 );
		\Brain\Monkey\Functions\when( 'get_post' )->justReturn(
			new \WP_Post(
				array(
					'ID'        => 7,
					'post_type' => 'page',
				)
			)
		);
		\Brain\Monkey\Functions\when( 'current_user_can' )->justReturn( false );
		\Brain\Monkey\Functions\when( 'get_post_meta' )->justReturn( 'builder' );
		\Brain\Monkey\Functions\when( 'get_option' )->justReturn( 0 );
		\Brain\Monkey\Functions\when( 'esc_html__' )->returnArg();
		\Brain\Monkey\Functions\when( 'wp_die' )->alias(
			static function (): void {
				throw new \RuntimeException( 'refused' );
			}
		);
		\Brain\Monkey\Functions\expect( 'wp_update_post' )->never();

		try {
			( new Pages() )->rename();
			$this->fail( 'The page was renamed.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'refused', $e->getMessage() );
		} finally {
			unset( $_POST['post'] );
		}
	}
}
