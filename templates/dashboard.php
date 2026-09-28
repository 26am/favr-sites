<?php
/**
 * The Favr dashboard.
 *
 * @package FavrSites
 *
 * @var array<string, mixed> $view View model from FavrSites\Dashboard\Screen::render().
 */

use FavrSites\Dashboard\Icons;

defined( 'ABSPATH' ) || exit;

$favr_verbs = array(
	'added'   => __( 'added', 'favr-sites' ),
	'updated' => __( 'updated', 'favr-sites' ),
);
$favr_help  = $view['help'];
$favr_link  = static function ( string $url, string $inner, string $css_class = '' ): string {
	$class_attr = '' !== $css_class ? ' class="' . esc_attr( $css_class ) . '"' : '';
	return '' !== $url
		? '<a' . $class_attr . ' href="' . esc_url( $url ) . '">' . $inner . '</a>'
		: '<span' . $class_attr . '>' . $inner . '</span>';
};
?>
<div class="wrap favr-dash">
	<h1 class="screen-reader-text"><?php esc_html_e( 'Dashboard', 'favr-sites' ); ?></h1>

	<header class="favr-dash__head">
		<div class="favr-dash__site">
			<?php if ( '' !== $view['logo'] ) : ?>
				<?php echo wp_kses_post( $view['logo'] ); ?>
			<?php elseif ( '' !== $view['icon'] ) : ?>
				<img class="favr-dash__logo favr-dash__logo--icon" src="<?php echo esc_url( $view['icon'] ); ?>" alt="">
			<?php endif; ?>
			<div>
				<p class="favr-dash__site-name"><?php echo esc_html( $view['site'] ); ?></p>
				<p class="favr-dash__greeting"><?php echo esc_html( $view['greeting'] ); ?>, <span><?php echo esc_html( $view['name'] ); ?></span></p>
			</div>
		</div>
		<div class="favr-dash__head-side">
			<a class="favr-dash__view-site" href="<?php echo esc_url( $view['site_url'] ); ?>"><?php esc_html_e( 'View site', 'favr-sites' ); ?><?php echo Icons::svg( 'external', 14 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
			<span class="favr-dash__brand" aria-label="<?php esc_attr_e( 'Favr', 'favr-sites' ); ?>">favr</span>
		</div>
	</header>

	<?php if ( $view['attention'] ) : ?>
		<section class="favr-dash__attention" aria-labelledby="favr-dash-attention">
			<h2 id="favr-dash-attention"><?php esc_html_e( 'Needs your attention', 'favr-sites' ); ?></h2>
			<ul>
				<?php foreach ( $view['attention'] as $favr_queue ) : ?>
					<li><a href="<?php echo esc_url( $view['review'] ); ?>"><strong><?php echo esc_html( number_format_i18n( $favr_queue['count'] ) ); ?></strong> <?php echo esc_html( $favr_queue['label'] ); ?></a></li>
				<?php endforeach; ?>
			</ul>
			<a class="favr-dash__attention-go" href="<?php echo esc_url( $view['review'] ); ?>"><?php esc_html_e( 'Review now', 'favr-sites' ); ?><?php echo Icons::svg( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
		</section>
	<?php endif; ?>

	<?php if ( $view['actions'] ) : ?>
		<nav class="favr-dash__actions" aria-label="<?php esc_attr_e( 'Quick actions', 'favr-sites' ); ?>">
			<?php foreach ( $view['actions'] as $favr_action ) : ?>
				<a class="favr-dash__action" href="<?php echo esc_url( $favr_action['url'] ); ?>"><?php echo Icons::svg( $favr_action['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><span><?php echo esc_html( $favr_action['label'] ); ?></span></a>
			<?php endforeach; ?>
		</nav>
	<?php endif; ?>

	<?php if ( $view['cards'] ) : ?>
		<div class="favr-dash__cards">
			<?php foreach ( $view['cards'] as $favr_card ) : ?>
				<section class="favr-dash__card" aria-labelledby="favr-dash-card-<?php echo esc_attr( $favr_card['id'] ); ?>">
					<h2 id="favr-dash-card-<?php echo esc_attr( $favr_card['id'] ); ?>"><?php echo esc_html( $favr_card['title'] ); ?></h2>

					<?php if ( $favr_card['stats'] ) : ?>
						<div class="favr-dash__stats">
							<?php foreach ( $favr_card['stats'] as $favr_stat ) : ?>
								<?php echo $favr_link( $favr_stat['url'], '<strong>' . esc_html( $favr_stat['value'] ) . '</strong> <span>' . esc_html( $favr_stat['label'] ) . '</span>', 'favr-dash__stat' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- parts escaped above. ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( $favr_card['items'] ) : ?>
						<ul class="favr-dash__list">
							<?php foreach ( $favr_card['items'] as $favr_item ) : ?>
								<li>
									<?php echo $favr_link( $favr_item['url'], esc_html( $favr_item['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?>
									<?php if ( '' !== $favr_item['meta'] ) : ?>
										<span class="favr-dash__meta"><?php echo esc_html( $favr_item['meta'] ); ?></span>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>

					<?php if ( ! $favr_card['stats'] && ! $favr_card['items'] && $favr_card['empty'] ) : ?>
						<p class="favr-dash__empty">
							<?php echo esc_html( $favr_card['empty']['text'] ); ?>
							<?php if ( '' !== $favr_card['empty']['label'] && '' !== $favr_card['empty']['url'] ) : ?>
								<a href="<?php echo esc_url( $favr_card['empty']['url'] ); ?>"><?php echo esc_html( $favr_card['empty']['label'] ); ?></a>
							<?php endif; ?>
						</p>
					<?php endif; ?>

					<?php if ( $favr_card['link'] ) : ?>
						<a class="favr-dash__card-link" href="<?php echo esc_url( $favr_card['link']['url'] ); ?>"><?php echo esc_html( $favr_card['link']['label'] ); ?><?php echo Icons::svg( 'arrow', 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?></a>
					<?php endif; ?>
				</section>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="favr-dash__bottom">
		<section class="favr-dash__card favr-dash__activity" aria-labelledby="favr-dash-activity">
			<h2 id="favr-dash-activity"><?php esc_html_e( 'Recent activity', 'favr-sites' ); ?></h2>
			<?php if ( $view['activity'] ) : ?>
				<ol class="favr-dash__list">
					<?php foreach ( $view['activity'] as $favr_row ) : ?>
						<li>
							<?php if ( '' !== $favr_row['who'] ) : ?>
								<span><strong><?php echo esc_html( $favr_row['who'] ); ?></strong> <?php echo esc_html( $favr_verbs[ $favr_row['verb'] ] ?? $favr_row['verb'] ); ?> <?php echo $favr_link( $favr_row['url'], esc_html( $favr_row['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?></span>
							<?php else : ?>
								<span><?php echo $favr_link( $favr_row['url'], esc_html( $favr_row['title'] ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped. ?> <span class="favr-dash__meta"><?php echo esc_html( $favr_verbs[ $favr_row['verb'] ] ?? $favr_row['verb'] ); ?></span></span>
							<?php endif; ?>
							<span class="favr-dash__meta"><?php echo esc_html( $favr_row['ago'] ); ?></span>
						</li>
					<?php endforeach; ?>
				</ol>
			<?php else : ?>
				<p class="favr-dash__empty"><?php esc_html_e( 'Nothing has changed recently.', 'favr-sites' ); ?></p>
			<?php endif; ?>
		</section>

		<section class="favr-dash__card favr-dash__help" aria-labelledby="favr-dash-help">
			<h2 id="favr-dash-help"><?php esc_html_e( 'Help', 'favr-sites' ); ?></h2>
			<?php if ( array_filter( $favr_help ) ) : ?>
				<ul class="favr-dash__help-links">
					<?php if ( '' !== $favr_help['help_url'] ) : ?>
						<li><a href="<?php echo esc_url( $favr_help['help_url'] ); ?>"><?php echo Icons::svg( 'help' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Help centre', 'favr-sites' ); ?></a></li>
					<?php endif; ?>
					<?php if ( '' !== $favr_help['support_email'] ) : ?>
						<li><a href="<?php echo esc_url( 'mailto:' . $favr_help['support_email'] ); ?>"><?php echo Icons::svg( 'mail' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $favr_help['support_email'] ); ?></a></li>
					<?php endif; ?>
					<?php if ( '' !== $favr_help['support_phone'] ) : ?>
						<li><a href="<?php echo esc_url( 'tel:' . preg_replace( '/[^0-9+]/', '', $favr_help['support_phone'] ) ); ?>"><?php echo Icons::svg( 'phone' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php echo esc_html( $favr_help['support_phone'] ); ?></a></li>
					<?php endif; ?>
					<?php if ( '' !== $favr_help['booking_url'] ) : ?>
						<li><a href="<?php echo esc_url( $favr_help['booking_url'] ); ?>"><?php echo Icons::svg( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG. ?><?php esc_html_e( 'Book a call', 'favr-sites' ); ?></a></li>
					<?php endif; ?>
				</ul>
			<?php else : ?>
				<p class="favr-dash__empty"><?php esc_html_e( 'Your Favr team is here to help. Ask your site manager for the best way to reach us.', 'favr-sites' ); ?></p>
			<?php endif; ?>
		</section>
	</div>
</div>
