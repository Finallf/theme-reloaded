<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Ad / Banner Widget                                                  *
 *                                                                             *
 * Native WP widget (extends WP_Widget) for an ad/banner slot. Holds a raw     *
 * ad-code field (AdSense, GAM, any HTML/JS) plus an optional title, and can be *
 * dropped into any widget area and ordered freely (Appearance → Widgets).     *
 * Replaces the old hardcoded "sidebar top" ad block + its panel field.        *
 *                                                                             *
 * The sticky sidebar ad (rd_render_ad_sidebar_sticky) stays hardcoded — it    *
 * has special bottom-of-sidebar sticky positioning. Page-level ad slots       *
 * (top-of-page, mobile anchor) also stay in the panel.                        *
 *                                                                             *
 * Security: the ad code is stored/rendered RAW by design (ad networks need    *
 * <script>/HTML). Widget editing requires edit_theme_options (admin) — same   *
 * trust model as the panel's `ad_` fields. CSP nonce is injected into every   *
 * <script> at render time via rd_csp_inject_nonce().                          *
 *******************************************************************************/
class RD_Ad_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'rd_ad',
			__( 'ReloadeD: Ad / Banner 300x250', 'reloaded' ),
			array(
				'description' => __( 'Ad/banner slot — paste AdSense, GAM or any HTML. Place and order it freely in any widget area.', 'reloaded' ),
				'classname'   => 'widget_rd_ad',
			)
		);
	}

	/**
	 * Frontend output. Renders nothing (not even the wrapper) when no code is set.
	 */
	public function widget( $args, $instance ) {
		$code = $instance['code'] ?? '';
		if ( '' === trim( $code ) ) {
			return;
		}

		$title = ! empty( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'] ) : '';

		// CSP nonce on <script> tags (defensive: mod-csp may be absent in edge cases).
		$code_out = function_exists( 'rd_csp_inject_nonce' ) ? rd_csp_inject_nonce( $code ) : $code;

		// Drop duplicate network loader scripts (one adsbygoogle.js per page —
		// see rd_ads_dedupe_loader in mod-ads.php).
		if ( function_exists( 'rd_ads_dedupe_loader' ) ) {
			$code_out = rd_ads_dedupe_loader( $code_out );
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP widget API convention: before_widget is structural HTML from register_sidebar()
		echo $args['before_widget'];

		if ( $title ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP widget API convention: before/after_title are structural HTML; $title is esc_html'd inline
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- BY DESIGN: ad codes (AdSense, GAM, etc.) require raw HTML/JS. Widget editors have edit_theme_options (trusted, same model as the panel ad_ fields). CSP nonce injected into <script> tags via rd_csp_inject_nonce().
		echo '<div class="rd-ad-container">' . $code_out . '</div>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP widget API convention: after_widget is structural HTML from register_sidebar()
		echo $args['after_widget'];
	}

	/**
	 * Configuration form on the Appearance → Widgets screen.
	 */
	public function form( $instance ) {
		$title = $instance['title'] ?? '';
		$code  = $instance['code'] ?? '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title (optional):', 'reloaded' ); ?></label>
			<input class="widefat" type="text"
					id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
					value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'code' ) ); ?>"><?php esc_html_e( 'Ad / banner code:', 'reloaded' ); ?></label>
			<textarea class="widefat" rows="6"
					id="<?php echo esc_attr( $this->get_field_id( 'code' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'code' ) ); ?>"><?php echo esc_textarea( $code ); ?></textarea>
			<small class="description"><?php esc_html_e( 'Paste the ad-network code (AdSense, GAM, etc.) or any HTML. Script tags receive a CSP nonce automatically.', 'reloaded' ); ?></small>
		</p>
		<?php
	}

	/**
	 * Saves the inputs. Title is sanitized; the ad code is stored RAW by design.
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title' => sanitize_text_field( $new_instance['title'] ?? '' ),
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.InputNotValidated -- BY DESIGN: ad codes need raw HTML/JS; widget editing requires edit_theme_options (trusted, same model as the panel ad_ fields). CSP nonce injected at render.
			'code'  => $new_instance['code'] ?? '',
		);
	}
}

// phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- intentional: widget class + register helper in one logical unit (mirrors the other RD widget files).
function rd_register_ad_widget() {
	register_widget( 'RD_Ad_Widget' );
}
add_action( 'widgets_init', 'rd_register_ad_widget' );
