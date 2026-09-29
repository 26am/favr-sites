<?php
declare(strict_types=1);

namespace FavrSites\Tests\Unit;

use FavrSites\Editors\BlockList;

final class BlockListTest extends TestCase {

	public function test_posts_get_the_short_list_and_other_types_are_untouched(): void {
		$this->assertContains( 'core/paragraph', BlockList::blocksFor( 'post' ) );
		$this->assertContains( 'core/embed', BlockList::blocksFor( 'post' ) );
		$this->assertNotContains( 'core/html', BlockList::blocksFor( 'post' ) );
		$this->assertNull( BlockList::blocksFor( 'page' ) );
		$this->assertNull( BlockList::blocksFor( 'favr_event' ) );
	}

	public function test_editor_settings_drop_patterns_openverse_and_code_editing(): void {
		$out = BlockList::trimSettings(
			array(
				'codeEditingEnabled'           => true,
				'enableOpenverseMediaCategory' => true,
				'__experimentalBlockPatterns'  => array( 1 ),
				'keep'                         => 'me',
			)
		);
		$this->assertFalse( $out['codeEditingEnabled'] );
		$this->assertFalse( $out['enableOpenverseMediaCategory'] );
		$this->assertSame( array(), $out['__experimentalBlockPatterns'] );
		$this->assertSame( array(), $out['__experimentalBlockPatternCategories'] );
		$this->assertSame( 'me', $out['keep'] );
	}
}
