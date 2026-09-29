<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Profile\ProfilePage;

final class ProfilePageTest extends TestCase {

	public function test_display_name_and_nickname_follow_first_and_last_name(): void {
		$this->assertSame(
			array( 'display_name' => 'Anna Lee', 'nickname' => 'Anna' ),
			ProfilePage::names( 'Anna', 'Lee', 'old display', 'old nick' )
		);
		$this->assertSame(
			array( 'display_name' => 'Lee', 'nickname' => 'Lee' ),
			ProfilePage::names( '', 'Lee', 'old display', '' )
		);
	}

	public function test_blank_names_keep_what_is_there(): void {
		$this->assertSame(
			array( 'display_name' => 'editor mcdondal', 'nickname' => 'editor' ),
			ProfilePage::names( '  ', '', 'editor mcdondal', 'editor' )
		);
	}

	public function test_schemes_put_favr_first_and_mark_the_current_one(): void {
		$colors = array(
			'fresh'  => (object) array( 'name' => 'Default', 'colors' => array( '#1d2327', '#2c3338', '#2271b1', '#72aee6' ) ),
			'light'  => (object) array( 'name' => 'Light', 'colors' => array( '#e5e5e5', '#999', '#d64e07', '#04a4cc' ) ),
			'favr'   => (object) array( 'name' => 'Favr', 'colors' => array( '#3a2738', '#a9472a', '#e3a044', '#fbf6ef' ) ),
			'broken' => 'not an object',
		);
		$out    = ProfilePage::schemes( $colors, 'light' );
		$this->assertSame( array( 'favr', 'fresh', 'light' ), array_column( $out, 'slug' ) );
		$this->assertSame( array( false, false, true ), array_column( $out, 'current' ) );
		$this->assertCount( 4, $out[0]['colors'] );
	}

	public function test_an_unknown_current_scheme_selects_favr(): void {
		$colors = array(
			'favr'  => (object) array( 'name' => 'Favr', 'colors' => array( '#3a2738', '#a9472a', '#e3a044', '#fbf6ef' ) ),
			'fresh' => (object) array( 'name' => 'Default', 'colors' => array( '#1d2327', '#2c3338', '#2271b1', '#72aee6' ) ),
		);
		$this->assertSame( array( true, false ), array_column( ProfilePage::schemes( $colors, 'gone' ), 'current' ) );
	}
}
