<?php
/**
 * Settings → Favr.
 *
 * @package FavrSites
 */

declare(strict_types=1);

namespace FavrSites\Admin;

use FavrSites\Help\Links;
use FavrSites\Menus\Slots;

/**
 * Administrators set the help links shown on the Favr dashboard and connect the Header and Footer menus.
 */
final class SettingsPage {

	private const PAGE = 'favr-sites';

	/** Hooks. */
	public function hook(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	/** Menu entry. */
	public function menu(): void {
		add_options_page( __( 'Favr', 'favr-sites' ), __( 'Favr', 'favr-sites' ), 'manage_options', self::PAGE, array( $this, 'render' ) );
	}

	/** Setting. */
	public function register(): void {
		register_setting(
			self::PAGE,
			Links::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => array(),
			)
		);
		register_setting(
			self::PAGE,
			Slots::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => static fn( $input ): array => Slots::sanitize( $input, Slots::menus() ),
				'default'           => array(),
			)
		);
	}

	/**
	 * Sanitize the submitted array.
	 *
	 * @param mixed $input Raw.
	 * @return array<string, string>
	 */
	public function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$out   = array();
		foreach ( array_keys( Links::DEFAULTS ) as $key ) {
			$out[ $key ] = Links::clean( $key, (string) ( $input[ $key ] ?? '' ) );
		}
		return $out;
	}

	/** Page. */
	public function render(): void {
		$saved  = get_option( Links::OPTION, array() );
		$saved  = is_array( $saved ) ? $saved : array();
		$menus  = wp_get_nav_menus();
		$slots  = Slots::current();
		$fields = array(
			'help_url'      => array( __( 'Help centre URL', 'favr-sites' ), 'url' ),
			'support_email' => array( __( 'Support email', 'favr-sites' ), 'email' ),
			'support_phone' => array( __( 'Support phone', 'favr-sites' ), 'text' ),
			'booking_url'   => array( __( 'Book a call URL', 'favr-sites' ), 'url' ),
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Favr', 'favr-sites' ); ?></h1>
			<p><?php esc_html_e( 'Help links shown to site editors on the Favr dashboard. Leave a field empty to use the Favr default; empty links are hidden.', 'favr-sites' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE ); ?>
				<table class="form-table" role="presentation">
					<?php foreach ( $fields as $key => $field ) : ?>
						<tr>
							<th scope="row"><label for="favr-sites-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $field[0] ); ?></label></th>
							<td>
								<input class="regular-text" type="<?php echo esc_attr( $field[1] ); ?>" id="favr-sites-<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( Links::OPTION . '[' . $key . ']' ); ?>" value="<?php echo esc_attr( (string) ( $saved[ $key ] ?? '' ) ); ?>" placeholder="<?php echo esc_attr( Links::DEFAULTS[ $key ] ); ?>">
								<?php if ( defined( 'FAVR_SITES_' . strtoupper( $key ) ) ) : ?>
									<p class="description"><?php esc_html_e( 'Set in wp-config.php; this field is ignored.', 'favr-sites' ); ?></p>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<h2><?php esc_html_e( 'Menus', 'favr-sites' ); ?></h2>
				<p><?php esc_html_e( 'Which menus editors manage on the Favr Menus screen.', 'favr-sites' ); ?></p>
				<table class="form-table" role="presentation">
					<?php foreach ( Slots::SLOTS as $slot ) : ?>
						<tr>
							<th scope="row"><label for="favr-sites-menu-<?php echo esc_attr( $slot ); ?>"><?php echo esc_html( Slots::label( $slot ) ); ?></label></th>
							<td>
								<select id="favr-sites-menu-<?php echo esc_attr( $slot ); ?>" name="<?php echo esc_attr( Slots::OPTION . '[' . $slot . ']' ); ?>">
									<option value="0"><?php esc_html_e( '— Not connected —', 'favr-sites' ); ?></option>
									<?php foreach ( $menus as $menu ) : ?>
										<option value="<?php echo esc_attr( (string) $menu->term_id ); ?>" <?php selected( $slots[ $slot ], (int) $menu->term_id ); ?>><?php echo esc_html( $menu->name ); ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
