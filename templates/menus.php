<?php
/**
 * Favr Menus: the Header and Footer menus.
 *
 * @package FavrSites
 *
 * @var array<string, mixed> $view View model from FavrSites\Menus\Screen::render().
 */

use FavrSites\Dashboard\Icons;
use FavrSites\Menus\Slots;

defined( 'ABSPATH' ) || exit;

$favr_tools = array(
	'up'     => __( 'Move up', 'favr-sites' ),
	'down'   => __( 'Move down', 'favr-sites' ),
	'in'     => __( 'Make it a dropdown item', 'favr-sites' ),
	'out'    => __( 'Move out of the dropdown', 'favr-sites' ),
	'remove' => __( 'Remove from menu', 'favr-sites' ),
);
$favr_row   = static function ( array $row, bool $invalid ) use ( $favr_tools ): void {
	$level = (int) $row['level'];
	$kind  = 'custom' === $row['type'] ? $row['url'] : ( 'page' === $row['type'] ? __( 'Page', 'favr-sites' ) : __( 'Other', 'favr-sites' ) );
	?>
	<li class="favr-menu-row" data-id="<?php echo esc_attr( (string) $row['id'] ); ?>" data-level="<?php echo esc_attr( (string) $level ); ?>" data-deep="<?php echo esc_attr( (string) ( $level > 1 ? $level : 0 ) ); ?>" data-type="<?php echo esc_attr( $row['type'] ); ?>" data-object-id="<?php echo esc_attr( (string) $row['object_id'] ); ?>" data-url="<?php echo esc_attr( $row['url'] ); ?>" style="--level: <?php echo esc_attr( (string) $level ); ?>">
		<div class="favr-menu-row__main">
			<span class="favr-menu-row__grip" data-js title="<?php esc_attr_e( 'Drag to reorder', 'favr-sites' ); ?>"><?php echo Icons::svg( 'grip', 18 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></span>
			<input class="favr-menu-row__title" type="text" value="<?php echo esc_attr( $row['title'] ); ?>" aria-label="<?php esc_attr_e( 'Label', 'favr-sites' ); ?>">
			<span class="favr-menu-row__kind"><?php echo esc_html( $kind ); ?></span>
			<span class="favr-menu-row__tools" data-js>
				<?php foreach ( $favr_tools as $favr_act => $favr_label ) : ?>
					<button type="button" class="favr-menu-row__tool<?php echo 'remove' === $favr_act ? ' is-remove' : ''; ?>" data-act="<?php echo esc_attr( $favr_act ); ?>" data-label="<?php echo esc_attr( $favr_label ); ?>" aria-label="<?php echo esc_attr( $favr_label ); ?>" title="<?php echo esc_attr( $favr_label ); ?>"><?php echo Icons::svg( 'remove' === $favr_act ? 'trash' : $favr_act, 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></button>
				<?php endforeach; ?>
			</span>
		</div>
		<p class="favr-menu-row__note" data-note="deep"<?php echo $level > 1 ? '' : ' hidden'; ?>><?php esc_html_e( 'Too deep for the menu; move it out a level.', 'favr-sites' ); ?></p>
		<?php if ( $invalid ) : ?>
			<p class="favr-menu-row__note"><?php esc_html_e( 'This page is in the trash or was deleted. Remove it, or restore the page.', 'favr-sites' ); ?></p>
		<?php endif; ?>
		<ol class="favr-menu-row__carry"></ol>
	</li>
	<?php
};
?>
<div class="wrap favr-dash favr-menus">
	<header class="favr-menus__head">
		<h1 class="favr-menus__title"><?php esc_html_e( 'Header & Footer', 'favr-sites' ); ?></h1>
		<p class="favr-menus__sub"><?php esc_html_e( 'Your site’s header and footer appear on every page. Edit their design in Elementor; manage their menu links here (drag to reorder, or use the arrows). Changes go live when you save.', 'favr-sites' ); ?></p>
	</header>

	<?php if ( '' !== $view['saved'] && in_array( $view['saved'], Slots::SLOTS, true ) ) : ?>
		<div class="favr-menus__notice is-success" role="status">
			<?php
			/* translators: %s: "Header menu" or "Footer menu". */
			echo esc_html( sprintf( __( '%s saved. It’s live on the site.', 'favr-sites' ), Slots::label( $view['saved'] ) ) );
			?>
		</div>
	<?php endif; ?>
	<?php if ( $view['errors'] ) : ?>
		<div class="favr-menus__notice is-error" role="alert">
			<?php foreach ( $view['errors'] as $favr_error ) : ?>
				<p><?php echo esc_html( (string) $favr_error ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<p class="favr-menus__nojs"><?php esc_html_e( 'Editing menus needs JavaScript. Turn it on (or try another browser) to make changes.', 'favr-sites' ); ?></p>

	<?php foreach ( $view['slots'] as $favr_slot => $favr_data ) : ?>
		<?php $favr_title_id = 'favr-menu-' . $favr_slot . '-title'; ?>
		<section class="favr-dash__card favr-menu" id="favr-menu-<?php echo esc_attr( $favr_slot ); ?>" aria-labelledby="<?php echo esc_attr( $favr_title_id ); ?>">
			<div class="favr-menu__top">
				<div>
					<h2 id="<?php echo esc_attr( $favr_title_id ); ?>"><?php echo esc_html( 'footer' === $favr_slot ? __( 'Footer', 'favr-sites' ) : __( 'Header', 'favr-sites' ) ); ?></h2>
					<p class="favr-menu__where"><?php esc_html_e( 'Shown on every page', 'favr-sites' ); ?></p>
				</div>
				<?php if ( '' !== $favr_data['design'] ) : ?>
					<a class="favr-menu__design" href="<?php echo esc_url( $favr_data['design'] ); ?>"><?php echo esc_html( 'footer' === $favr_slot ? __( 'Edit footer design', 'favr-sites' ) : __( 'Edit header design', 'favr-sites' ) ); ?></a>
				<?php endif; ?>
			</div>
			<div class="favr-menu__head">
				<h3><?php esc_html_e( 'Menu links', 'favr-sites' ); ?></h3>
				<?php if ( $favr_data['menu'] ) : ?>
					<p class="favr-menu__usage">
						<?php
						echo $favr_data['usage']
							/* translators: %s: list of places, e.g. "Header, Home". */
							? esc_html( sprintf( __( 'Shown in: %s', 'favr-sites' ), implode( ', ', $favr_data['usage'] ) ) )
							: esc_html__( 'Not shown on the site yet', 'favr-sites' );
						?>
					</p>
				<?php endif; ?>
			</div>

			<?php if ( ! $favr_data['menu'] ) : ?>
				<p class="favr-menu__unset">
					<?php
					/* translators: %s: "header" or "footer". */
					echo esc_html( sprintf( __( 'Your %s menu isn’t set up yet. Favr will connect it.', 'favr-sites' ), 'footer' === $favr_slot ? __( 'footer', 'favr-sites' ) : __( 'header', 'favr-sites' ) ) );
					?>
				</p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="favr-menu__form" data-slot="<?php echo esc_attr( $favr_slot ); ?>">
					<input type="hidden" name="action" value="<?php echo esc_attr( $view['action'] ); ?>">
					<input type="hidden" name="menu_id" value="<?php echo esc_attr( (string) $favr_data['menu']->term_id ); ?>">
					<input type="hidden" name="rows" value="">
					<?php wp_nonce_field( $view['nonce'] . $favr_data['menu']->term_id ); ?>

					<ol class="favr-menu__list">
						<?php
						foreach ( $favr_data['rows'] as $favr_item ) {
							$favr_row( $favr_item, isset( $favr_data['invalid'][ $favr_item['id'] ] ) );
						}
						?>
					</ol>
					<p class="favr-menu__empty"<?php echo $favr_data['rows'] ? ' hidden' : ''; ?>><?php esc_html_e( 'This menu is empty. Add a page or a link.', 'favr-sites' ); ?></p>

					<div class="favr-menu__add" data-js>
						<div class="favr-menu__add-group">
							<label class="favr-menu__add-label" for="favr-menu-<?php echo esc_attr( $favr_slot ); ?>-page"><?php esc_html_e( 'Add a page', 'favr-sites' ); ?></label>
							<div class="favr-menu__inline">
								<select id="favr-menu-<?php echo esc_attr( $favr_slot ); ?>-page" data-add="page">
									<option value=""><?php esc_html_e( 'Choose a page…', 'favr-sites' ); ?></option>
									<?php foreach ( $view['pages'] as $favr_page ) : ?>
										<option value="<?php echo esc_attr( (string) $favr_page['id'] ); ?>"><?php echo esc_html( $favr_page['title'] ); ?></option>
									<?php endforeach; ?>
								</select>
								<button type="button" class="favr-menu__add-btn" data-act="add-page"><?php echo Icons::svg( 'plus', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Add', 'favr-sites' ); ?></button>
							</div>
						</div>
						<div class="favr-menu__add-group">
							<span class="favr-menu__add-label" id="favr-menu-<?php echo esc_attr( $favr_slot ); ?>-link"><?php esc_html_e( 'Add a link', 'favr-sites' ); ?></span>
							<div class="favr-menu__inline" role="group" aria-labelledby="favr-menu-<?php echo esc_attr( $favr_slot ); ?>-link">
								<input type="text" data-add="label" placeholder="<?php esc_attr_e( 'Label', 'favr-sites' ); ?>" aria-label="<?php esc_attr_e( 'Link label', 'favr-sites' ); ?>">
								<input type="text" data-add="url" inputmode="url" spellcheck="false" placeholder="<?php esc_attr_e( 'https://… or /page', 'favr-sites' ); ?>" aria-label="<?php esc_attr_e( 'Link address', 'favr-sites' ); ?>">
								<button type="button" class="favr-menu__add-btn" data-act="add-link"><?php echo Icons::svg( 'plus', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Add', 'favr-sites' ); ?></button>
							</div>
						</div>
						<p class="favr-menu__add-error" role="alert" hidden></p>
					</div>

					<p class="favr-menu__actions" data-js>
						<button type="submit" class="favr-menu__save">
							<?php
							/* translators: %s: "Header menu" or "Footer menu". */
							echo esc_html( sprintf( __( 'Save %s', 'favr-sites' ), strtolower( Slots::label( $favr_slot ) ) ) );
							?>
						</button>
						<span class="favr-menu__dirty" hidden><?php esc_html_e( 'Unsaved changes', 'favr-sites' ); ?></span>
					</p>
				</form>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>

	<?php
	$favr_targets = array_keys( array_filter( $view['slots'], static fn( array $data ): bool => null !== $data['menu'] ) );
	if ( $view['unplaced'] && $favr_targets ) :
		?>
		<section class="favr-dash__card favr-menus__tray" aria-labelledby="favr-menus-tray" data-js>
			<h2 id="favr-menus-tray"><?php esc_html_e( 'Pages not in a menu yet', 'favr-sites' ); ?></h2>
			<p class="favr-menus__tray-hint"><?php esc_html_e( 'New pages don’t join a menu by themselves. Add the ones visitors should find.', 'favr-sites' ); ?></p>
			<ul class="favr-menus__tray-list">
				<?php foreach ( $view['unplaced'] as $favr_page ) : ?>
					<li data-id="<?php echo esc_attr( (string) $favr_page['id'] ); ?>" data-title="<?php echo esc_attr( $favr_page['title'] ); ?>">
						<span class="favr-menus__tray-title"><?php echo esc_html( $favr_page['title'] ); ?></span>
						<span class="favr-menus__tray-actions">
							<?php foreach ( $favr_targets as $favr_slot ) : ?>
								<button type="button" data-act="tray-add" data-slot="<?php echo esc_attr( $favr_slot ); ?>">
									<?php
									/* translators: %s: "header" or "footer". */
									echo esc_html( sprintf( __( 'Add to %s', 'favr-sites' ), 'footer' === $favr_slot ? __( 'footer', 'favr-sites' ) : __( 'header', 'favr-sites' ) ) );
									?>
								</button>
							<?php endforeach; ?>
						</span>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif; ?>

	<template id="favr-menu-row">
		<?php
		$favr_row(
			array(
				'id'        => 0,
				'level'     => 0,
				'title'     => '',
				'url'       => '',
				'type'      => 'custom',
				'object_id' => 0,
			),
			false
		);
		?>
	</template>
</div>
