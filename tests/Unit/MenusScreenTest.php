<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Menus\Screen;

final class MenusScreenTest extends TestCase {

	private const SLOTS = array(
		'header' => 19,
		'footer' => 20,
	);

	protected function setUp(): void {
		parent::setUp();
		// A nonce is good when it reads "ok-" plus its action.
		Functions\when( 'wp_verify_nonce' )->alias( static fn( $nonce, $action ) => 'ok-' . $action === $nonce ? 1 : false );
	}

	public function test_one_save_carries_the_other_menus_unsaved_rows(): void {
		$post = array(
			'rows' => '[{"id":1}]',
			'also' => array(
				19 => array(
					'rows'  => '[{"id":2}]',
					'nonce' => 'ok-favr_sites_save_menu_19',
				),
			),
		);
		// Footer was pressed, so it comes first; the header's unsaved rows ride along.
		$this->assertSame(
			array(
				'footer' => '[{"id":1}]',
				'header' => '[{"id":2}]',
			),
			Screen::batch( 20, $post, self::SLOTS )
		);
	}

	public function test_a_save_without_extras_is_the_pressed_menu_alone(): void {
		$this->assertSame( array( 'header' => '[]' ), Screen::batch( 19, array( 'rows' => '[]' ), self::SLOTS ) );
		$this->assertSame( array( 'header' => '' ), Screen::batch( 19, array( 'also' => 'nonsense' ), self::SLOTS ) );
	}

	public function test_extras_need_their_own_nonce_and_a_favr_menu(): void {
		$post = array(
			'rows' => '[]',
			'also' => array(
				20 => array(
					'rows'  => '[{"id":2}]',
					'nonce' => 'ok-favr_sites_save_menu_19', // Another menu's nonce.
				),
				77 => array(
					'rows'  => '[{"id":3}]',
					'nonce' => 'ok-favr_sites_save_menu_77', // Not the header or the footer.
				),
				19 => array(
					'rows'  => '[{"id":4}]',
					'nonce' => 'ok-favr_sites_save_menu_19', // The pressed menu itself: its own rows win.
				),
			),
		);
		$this->assertSame( array( 'header' => '[]' ), Screen::batch( 19, $post, self::SLOTS ) );
	}

	public function test_the_saved_flag_names_every_saved_menu(): void {
		$this->assertSame( 'header-footer', Screen::savedFlag( array( 'footer', 'header' ) ) );
		$this->assertSame( 'footer', Screen::savedFlag( array( 'footer' ) ) );
		$this->assertSame( '', Screen::savedFlag( array() ) );
		$this->assertSame( array( 'header', 'footer' ), Screen::savedSlots( 'header-footer' ) );
		$this->assertSame( array( 'footer' ), Screen::savedSlots( 'footer' ) );
		$this->assertSame( array(), Screen::savedSlots( 'both' ) );
		$this->assertSame( array(), Screen::savedSlots( '' ) );
	}

	public function test_the_notice_says_which_menus_went_live(): void {
		$this->assertSame( 'Header menu saved. It’s live on the site.', Screen::savedNotice( array( 'header' ) ) );
		$this->assertSame( 'Footer menu saved. It’s live on the site.', Screen::savedNotice( array( 'footer' ) ) );
		$this->assertSame( 'Header and footer menus saved. They’re live on the site.', Screen::savedNotice( array( 'header', 'footer' ) ) );
		$this->assertSame( '', Screen::savedNotice( array() ) );
	}

	public function test_errors_are_kept_by_menu(): void {
		$this->assertSame(
			array( 'footer' => array( 'Too deep.' ) ),
			Screen::errorsBySlot(
				array(
					'footer' => array( 'Too deep.' ),
					'side'   => array( 'Not a Favr menu.' ),
					0        => 'An error stored before menus saved together.',
				)
			)
		);
		$this->assertSame( array(), Screen::errorsBySlot( false ) );
	}
}
