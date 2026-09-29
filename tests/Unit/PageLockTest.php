<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Editors\PageLock;

final class PageLockTest extends TestCase {

	public function test_only_existing_non_elementor_pages_are_locked_when_elementor_runs(): void {
		$this->assertTrue( PageLock::isLocked( 'page', 'publish', false, true ) );
		$this->assertTrue( PageLock::isLocked( 'page', 'draft', false, true ) );
		$this->assertFalse( PageLock::isLocked( 'page', 'publish', true, true ) );    // built with Elementor
		$this->assertFalse( PageLock::isLocked( 'page', 'auto-draft', false, true ) ); // being created
		$this->assertFalse( PageLock::isLocked( 'post', 'publish', false, true ) );    // posts never
		$this->assertFalse( PageLock::isLocked( 'page', 'publish', false, false ) );   // Elementor off
	}
}
