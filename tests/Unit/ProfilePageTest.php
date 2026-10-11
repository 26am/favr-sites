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

	public function test_saving_the_profile_keeps_the_stored_colour_scheme(): void {
		// The page has no picker, and WordPress would write its default when the field is missing.
		$this->assertSame( 'midnight', ProfilePage::keptScheme( 'midnight' ) );
		$this->assertSame( 'favr', ProfilePage::keptScheme( 'favr' ) );
	}

	public function test_a_user_without_a_stored_scheme_gets_what_wordpress_would_store(): void {
		$this->assertSame( 'fresh', ProfilePage::keptScheme( '' ) );
		$this->assertSame( 'fresh', ProfilePage::keptScheme( false ) );
		$this->assertSame( 'fresh', ProfilePage::keptScheme( array( 'midnight' ) ) );
	}

	public function test_the_profile_page_has_no_scheme_picker(): void {
		$template = (string) file_get_contents( dirname( __DIR__, 2 ) . '/templates/profile.php' );
		$this->assertStringNotContainsString( 'admin_color', $template );
		$this->assertStringNotContainsString( 'favr-swatch', $template );
	}

	public function test_elementor_ai_is_off_for_editors_whatever_is_stored(): void {
		// Elementor reads the user option `elementor_enable_ai` and treats "never set" as on.
		$this->assertSame( '0', ProfilePage::elementorAi( false, true ), 'Never set.' );
		$this->assertSame( '0', ProfilePage::elementorAi( '1', true ), 'Turned on.' );
		$this->assertSame( '0', ProfilePage::elementorAi( '0', true ) );
		$this->assertFalse( (bool) ProfilePage::elementorAi( false, true ), 'Elementor casts the value to a boolean.' );
	}

	public function test_everybody_else_keeps_their_elementor_ai_choice(): void {
		$this->assertFalse( ProfilePage::elementorAi( false, false ), 'Never set stays never set: Elementor then uses its default.' );
		$this->assertSame( '1', ProfilePage::elementorAi( '1', false ) );
		$this->assertSame( '0', ProfilePage::elementorAi( '0', false ) );
	}

	public function test_editors_always_get_the_toolbar_on_the_public_site(): void {
		$this->assertTrue( ProfilePage::showsToolbar( false, true, false ) );
		$this->assertTrue( ProfilePage::showsToolbar( true, true, false ) );
	}

	public function test_the_toolbar_stays_out_of_the_elementor_editor_preview(): void {
		// Elementor turns the toolbar off inside its preview frame; forcing it back covers the page header.
		$this->assertFalse( ProfilePage::showsToolbar( false, true, true ) );
	}

	public function test_everybody_else_keeps_their_own_toolbar_choice(): void {
		$this->assertFalse( ProfilePage::showsToolbar( false, false, false ) );
		$this->assertTrue( ProfilePage::showsToolbar( true, false, false ) );
		$this->assertTrue( ProfilePage::showsToolbar( true, false, true ) );
	}
}
