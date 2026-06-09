<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Discord Widget                                                      *
 *                                                                             *
 * Native WP widget (extends WP_Widget) for the Discord server block. Config   *
 * (server ID, facade toggle, facade logo) lives in the widget form            *
 * (Appearance → Widgets) so settings and placement sit together; drag it into *
 * any registered area and order it freely. Replaces the old hardcoded sidebar *
 * block + theme-panel fields + the `discord_widget` master toggle — placement *
 * is the enable now.                                                          *
 *                                                                             *
 * CSP: mod-csp.php adds Discord's frame-src origins only when this widget is   *
 * active (is_active_widget('rd_discord')), so the live iframe isn't blocked.   *
 *                                                                             *
 * Frontend markup is delegated to rd_get_discord_widget_html() in             *
 * inc/mod-integrations.php (single source of the block HTML).                  *
 *******************************************************************************/
class RD_Discord_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'rd_discord',
			__( 'ReloadeD: Discord', 'reloaded' ),
			array(
				'description' => __( 'Discord server widget (facade or live iframe). All options are configured here in the widget.', 'reloaded' ),
				'classname'   => 'widget_rd_discord',
			)
		);
	}

	/**
	 * Frontend output — delegates the markup to rd_get_discord_widget_html().
	 */
	public function widget( $args, $instance ) {
		if ( ! function_exists( 'rd_get_discord_widget_html' ) ) {
			return;
		}

		$html = rd_get_discord_widget_html(
			array(
				'discord_id'  => $instance['discord_id'] ?? '',
				'facade'      => $instance['facade'] ?? '1',
				'facade_logo' => $instance['discord_facade_logo'] ?? '',
			)
		);

		if ( '' === $html ) {
			return;
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP widget API convention: before_widget is structural HTML from register_sidebar()
		echo $args['before_widget'];
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rd_get_discord_widget_html() escapes every dynamic value internally
		echo $html;
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP widget API convention: after_widget is structural HTML from register_sidebar()
		echo $args['after_widget'];
	}

	/**
	 * Plain text field row with an optional description.
	 */
	private function text_field( $key, $label, $value, $placeholder = '', $desc = '' ) {
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $label ); ?></label>
			<input class="widefat" type="text"
					id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>">
			<?php if ( '' !== $desc ) : ?>
				<small class="description"><?php echo wp_kses( $desc, array( 'strong' => array() ) ); ?></small>
			<?php endif; ?>
		</p>
		<?php
	}

	/**
	 * Checkbox field row (value "1" when checked).
	 */
	private function checkbox_field( $key, $label, $checked ) {
		?>
		<p>
			<input type="checkbox" class="checkbox"
					id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>"
					value="1" <?php checked( ! empty( $checked ) ); ?>>
			<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $label ); ?></label>
		</p>
		<?php
	}

	/**
	 * Image field row (URL input + preview + media-picker buttons), bound by
	 * assets/js/widget-media.js via the .rd-widget-media-field wrapper.
	 */
	private function media_field( $key, $label, $value ) {
		?>
		<p class="rd-widget-media-field">
			<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $label ); ?></label>
			<input class="widefat rd-widget-media-url" type="text"
					id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="<?php esc_attr_e( 'Image URL', 'reloaded' ); ?>">
			<span class="rd-widget-media-preview">
				<?php if ( $value ) : ?>
					<img src="<?php echo esc_url( $value ); ?>" alt="" style="max-width:120px;height:auto;display:block;margin:6px 0;">
				<?php endif; ?>
			</span>
			<button type="button" class="button rd-widget-media-btn"><?php esc_html_e( 'Select image', 'reloaded' ); ?></button>
			<button type="button" class="button rd-widget-media-remove"><?php esc_html_e( 'Remove', 'reloaded' ); ?></button>
		</p>
		<?php
	}

	/**
	 * Configuration form on the Appearance → Widgets screen.
	 */
	public function form( $instance ) {
		$discord_id = $instance['discord_id'] ?? '';
		$facade     = $instance['facade'] ?? '1'; // facade ON by default for a new widget
		$logo       = $instance['discord_facade_logo'] ?? '';

		$this->text_field(
			'discord_id',
			__( 'Discord Server ID', 'reloaded' ),
			$discord_id,
			'408089552759029788',
			__( 'Numeric <strong>Server ID</strong> (17-19 digits), not an invite code. Enable Developer Mode in Discord, right-click the server icon → "Copy Server ID". The server must also have Widget enabled in Server Settings → Widget.', 'reloaded' )
		);

		$this->checkbox_field( 'facade', __( 'Use facade (load the live widget only on click — recommended)', 'reloaded' ), $facade );

		$this->media_field( 'discord_facade_logo', __( 'Facade Logo (optional)', 'reloaded' ), $logo );
		?>
		<p class="description"><?php esc_html_e( 'Shown next to the Discord logo on the facade. Empty = site Custom Logo, then the theme default. ~430x100px (4:1).', 'reloaded' ); ?></p>
		<?php
	}

	/**
	 * Sanitizes the inputs before saving.
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'discord_id'          => sanitize_text_field( $new_instance['discord_id'] ?? '' ),
			'facade'              => ! empty( $new_instance['facade'] ) ? '1' : '0',
			'discord_facade_logo' => esc_url_raw( $new_instance['discord_facade_logo'] ?? '' ),
		);
	}
}

// phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- intentional: widget class + register helper in one logical unit (mirrors the other RD widget files).
function rd_register_discord_widget() {
	register_widget( 'RD_Discord_Widget' );
}
add_action( 'widgets_init', 'rd_register_discord_widget' );
