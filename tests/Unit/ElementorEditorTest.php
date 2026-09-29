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
		$this->assertSame( array( 'label' => 'Back to Header & Footer', 'href' => 'https://x.test/wp-admin/admin.php?page=favr-menus' ), ElementorEditor::back( 'header' ) );
		$this->assertSame( 'Back to Pages', ElementorEditor::back( 'home' )['label'] );
	}

	public function test_only_display_conditions_are_hidden_so_editors_can_still_save_drafts(): void {
		// Elementor's "Save Draft" on a published document only autosaves; it never unpublishes.
		$this->assertSame( array( 'document-display-conditions' ), ElementorEditor::HIDE );
	}
}
