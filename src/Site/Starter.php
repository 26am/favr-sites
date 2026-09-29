<?php
/**
 * Starter Header, Footer and Home content (pure).
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Site;

/**
 * Just enough to work the moment it's created; Favr designs each site from here. No colours or
 * fonts: widgets inherit the Site Kit's globals.
 */
final class Starter {

	/**
	 * Site logo (or title) on the left, the Header menu on the right.
	 *
	 * @param bool   $has_logo  A custom logo is set.
	 * @param string $menu      Header menu slug ('' = Elementor's first menu).
	 * @param string $title_tag Site-title tag text, so the editor shows the name too ('' = none).
	 * @param string $link_tag  Site-URL tag text for the title's link ('' = none).
	 * @return list<array<string, mixed>>
	 */
	public static function header( bool $has_logo, string $menu, string $title_tag = '', string $link_tag = '' ): array {
		$title = array( 'header_size' => 'p' );
		$tags  = array_filter(
			array(
				'title' => $title_tag,
				'link'  => $link_tag,
			)
		);
		if ( $tags ) {
			$title['__dynamic__'] = $tags;
		}
		$brand = $has_logo ? self::widget( 'fvh0002', 'theme-site-logo', array( 'align' => 'left' ) ) : self::widget( 'fvh0002', 'theme-site-title', $title );
		return array(
			self::container(
				'fvh0001',
				array(
					'flex_direction'       => 'row',
					'flex_justify_content' => 'space-between',
					'flex_align_items'     => 'center',
					'flex_wrap'            => 'wrap',
				),
				array( $brand, self::widget( 'fvh0003', 'nav-menu', self::menu( $menu, 'end' ) ) )
			),
		);
	}

	/**
	 * The Footer menu, then "© {year} {site}" (year from Elementor's current-date tag).
	 *
	 * @param string $menu      Footer menu slug.
	 * @param string $site_name Site name.
	 * @param string $year_tag  Dynamic tag text for the year line ('' = static year).
	 * @param int    $year      Current year (static fallback).
	 * @return list<array<string, mixed>>
	 */
	public static function footer( string $menu, string $site_name, string $year_tag, int $year ): array {
		$line = array(
			'title'       => '© ' . $year . ' ' . $site_name,
			'header_size' => 'p',
			'align'       => 'center',
		);
		if ( '' !== $year_tag ) {
			$line['__dynamic__'] = array( 'title' => $year_tag );
		}
		return array(
			self::container(
				'fvf0001',
				array(
					'flex_direction'   => 'column',
					'flex_align_items' => 'center',
				),
				array(
					self::widget( 'fvf0002', 'nav-menu', self::menu( $menu, 'center' ) + array( 'toggle' => '' ) ),
					self::widget( 'fvf0003', 'heading', $line ),
				)
			),
		);
	}

	/**
	 * Site name and tagline.
	 *
	 * @param string $site_name Site name.
	 * @param string $tagline   Tagline as WordPress stores it (already HTML-escaped; '' = none).
	 * @return list<array<string, mixed>>
	 */
	public static function home( string $site_name, string $tagline ): array {
		$widgets = array(
			self::widget(
				'fvp0002',
				'heading',
				array(
					'title'       => $site_name,
					'header_size' => 'h1',
					'align'       => 'center',
				)
			),
		);
		if ( '' !== $tagline ) {
			$widgets[] = self::widget( 'fvp0003', 'text-editor', array( 'editor' => '<p style="text-align:center">' . htmlspecialchars( $tagline, ENT_QUOTES, 'UTF-8', false ) . '</p>' ) );
		}
		return array(
			self::container(
				'fvp0001',
				array(
					'flex_direction'       => 'column',
					'flex_align_items'     => 'center',
					'min_height'           => array(
						'unit' => 'vh',
						'size' => 50,
					),
					'flex_justify_content' => 'center',
				),
				$widgets
			),
		);
	}

	/**
	 * Nav Menu settings.
	 *
	 * @param string $menu  Slug.
	 * @param string $align start|center|end.
	 * @return array<string, string>
	 */
	private static function menu( string $menu, string $align ): array {
		$settings = array(
			'layout'      => 'horizontal',
			'align_items' => $align,
		);
		if ( '' !== $menu ) {
			$settings['menu'] = $menu;
		}
		return $settings;
	}

	/**
	 * A container.
	 *
	 * @param string                     $id       Element id.
	 * @param array<string, mixed>       $settings Settings.
	 * @param list<array<string, mixed>> $elements Children.
	 * @return array<string, mixed>
	 */
	private static function container( string $id, array $settings, array $elements ): array {
		return array(
			'id'       => $id,
			'elType'   => 'container',
			'isInner'  => false,
			'settings' => $settings,
			'elements' => $elements,
		);
	}

	/**
	 * A widget.
	 *
	 * @param string               $id       Element id.
	 * @param string               $type     Widget type.
	 * @param array<string, mixed> $settings Settings.
	 * @return array<string, mixed>
	 */
	private static function widget( string $id, string $type, array $settings ): array {
		return array(
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => $type,
			'settings'   => $settings,
			'elements'   => array(),
		);
	}
}
