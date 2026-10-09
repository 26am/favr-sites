<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Editors\AdminFooter;

final class AdminFooterTest extends TestCase {

	public function test_editors_see_no_wordpress_credit_or_version_under_their_screens(): void {
		$this->assertSame( '', AdminFooter::text( 'Thank you for creating with WordPress.', true ) );
		$this->assertSame( '', AdminFooter::text( 'Version 7.1.3', true ) );
	}

	public function test_administrators_keep_them(): void {
		$this->assertSame( 'Version 7.1.3', AdminFooter::text( 'Version 7.1.3', false ) );
	}
}
