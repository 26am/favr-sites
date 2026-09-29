<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

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
}
