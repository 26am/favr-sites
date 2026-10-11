<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use Brain\Monkey\Functions;
use FavrSites\Editors\ElementorEditor;

final class ElementorEditorTest extends TestCase {

	public function test_help_goes_to_the_help_centre_when_there_is_one(): void {
		$this->assertSame(
			array(
				'label' => 'Favr help',
				'href'  => 'https://help.favr.site/',
			),
			ElementorEditor::helpLink( array( 'help_url' => 'https://help.favr.site/', 'support_email' => 'care@favr.site' ) )
		);
	}

	public function test_help_falls_back_to_email_then_nothing(): void {
		$this->assertSame(
			array(
				'label' => 'Email Favr',
				'href'  => 'mailto:care@favr.site',
			),
			ElementorEditor::helpLink( array( 'help_url' => '', 'support_email' => 'care@favr.site' ) )
		);
		$this->assertNull( ElementorEditor::helpLink( array( 'help_url' => '', 'support_email' => '' ) ) );
	}

	public function test_exit_returns_to_where_the_editor_came_from(): void {
		Functions\when( 'admin_url' )->alias( static fn( string $path = '' ): string => 'https://x.test/wp-admin/' . $path );
		$this->assertSame( array( 'label' => 'Back to Pages', 'href' => 'https://x.test/wp-admin/edit.php?post_type=page' ), ElementorEditor::back( null ) );
		$this->assertSame( array( 'label' => 'Back to Header & footer', 'href' => 'https://x.test/wp-admin/admin.php?page=favr-menus' ), ElementorEditor::back( 'header' ) );
		$this->assertSame( 'Back to Pages', ElementorEditor::back( 'home' )['label'] );
	}

	public function test_only_display_conditions_are_hidden_so_editors_can_still_save_drafts(): void {
		// Elementor's "Save Draft" on a published document only autosaves; it never unpublishes.
		$this->assertSame( array( 'document-display-conditions' ), ElementorEditor::HIDE );
	}

	public function test_editors_do_not_get_the_design_system_page_settings_or_finder_buttons(): void {
		$this->assertContains( 'open-design-system-toolbar', ElementorEditor::HIDE_TOOLS );
		$this->assertContains( 'document-settings-button', ElementorEditor::HIDE_TOOLS );
		$this->assertContains( 'toggle-finder', ElementorEditor::HIDE_UTILITIES );
	}

	public function test_a_widget_is_offered_unless_every_group_it_is_in_is_hidden(): void {
		$hidden = ElementorEditor::HIDE_CATEGORIES;
		$this->assertFalse( ElementorEditor::showsWidget( array( 'v4-elements' ), $hidden ) );
		$this->assertFalse( ElementorEditor::showsWidget( array( 'link-in-bio' ), $hidden ) );
		$this->assertTrue( ElementorEditor::showsWidget( array( 'basic' ), $hidden ) );
		$this->assertTrue( ElementorEditor::showsWidget( array( 'favr' ), $hidden ) );
		$this->assertTrue( ElementorEditor::showsWidget( array( 'general', 'link-in-bio' ), $hidden ) );
		$this->assertTrue( ElementorEditor::showsWidget( array(), $hidden ) );
	}

	public function test_the_favr_widgets_come_first_and_the_everyday_ones_stay(): void {
		$this->assertSame( 'favr', ElementorEditor::FIRST_CATEGORY );
		foreach ( array( 'favr', 'layout', 'basic', 'general', 'pro-elements', 'theme-elements' ) as $kept ) {
			$this->assertNotContains( $kept, ElementorEditor::HIDE_CATEGORIES );
		}
	}

	public function test_editors_are_not_offered_elementors_ai_widget_builder_or_its_install_prompt(): void {
		$packages = array( 'editor', 'editor-app-bar', 'editor-widget-creation', 'editor-panels' );
		$this->assertSame( array( 'editor', 'editor-app-bar', 'editor-panels' ), ElementorEditor::packagesFor( $packages, true ) );
		$this->assertSame( $packages, ElementorEditor::packagesFor( $packages, false ) );

		$settings = array( 'angie' => array( 'autoShow' => true, 'other' => 1 ), 'kept' => 'yes' );
		$this->assertSame( array( 'angie' => array( 'other' => 1 ), 'kept' => 'yes' ), ElementorEditor::settingsFor( $settings, true ) );
		$this->assertSame( $settings, ElementorEditor::settingsFor( $settings, false ) );
	}
}
