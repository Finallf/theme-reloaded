<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Support the Project Widget                                          *
 *                                                                             *
 * Native WP widget (extends WP_Widget) for the donation block — GitHub        *
 * Sponsors, PayPal and PIX (Brazil). All options live in the widget form      *
 * (Appearance → Widgets), so configuration and placement sit together; the    *
 * admin drags it into any registered area (Main Sidebar or Footer) and orders *
 * it freely. Replaces the old hardcoded sidebar block + theme-panel fields.   *
 *                                                                             *
 * Image fields (PayPal/PIX QR codes) use the WP media uploader wired by       *
 * assets/js/widget-media.js (enqueued on the classic Widgets screen by        *
 * rd_enqueue_widget_media() in core.php).                                      *
 *                                                                             *
 * Frontend markup is delegated to rd_get_support_block_html() in              *
 * inc/mod-donations.php (single source of the block HTML).                     *
 *******************************************************************************/
class RD_Support_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'rd_support',
			__( 'ReloadeD: Support the Project', 'reloaded' ),
			array(
				'description' => __( 'Donation block (GitHub Sponsors, PayPal, PIX). All options are configured here in the widget.', 'reloaded' ),
				'classname'   => 'widget_rd_support',
			)
		);
	}

	/**
	 * Frontend output. Builds the block from the instance values; renders
	 * nothing (not even the widget wrapper) when no donation method is set.
	 */
	public function widget( $args, $instance ) {
		if ( ! function_exists( 'rd_get_support_block_html' ) ) {
			return;
		}

		$html = rd_get_support_block_html(
			array(
				'title'           => $instance['title'] ?? '',
				'github_sponsors' => $instance['github_sponsors'] ?? '',
				'paypal_url'      => $instance['paypal_url'] ?? '',
				'paypal_qrcode'   => $instance['paypal_qrcode'] ?? '',
				'pix_url'         => $instance['pix_url'] ?? '',
				'pix_qrcode'      => $instance['pix_qrcode'] ?? '',
				'pix_chave'       => $instance['pix_chave'] ?? '',
			)
		);

		if ( '' === $html ) {
			return; // nothing configured → no empty widget wrapper
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP widget API convention: before_widget is structural HTML from register_sidebar()
		echo $args['before_widget'];
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rd_get_support_block_html() escapes every dynamic value internally
		echo $html;
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP widget API convention: after_widget is structural HTML from register_sidebar()
		echo $args['after_widget'];
	}

	/**
	 * Renders a plain text field row.
	 */
	private function text_field( $key, $label, $value, $placeholder = '' ) {
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"><?php echo esc_html( $label ); ?></label>
			<input class="widefat" type="text"
					id="<?php echo esc_attr( $this->get_field_id( $key ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( $key ) ); ?>"
					value="<?php echo esc_attr( $value ); ?>"
					placeholder="<?php echo esc_attr( $placeholder ); ?>">
		</p>
		<?php
	}

	/**
	 * Renders an image field row: URL input + preview + media-picker buttons.
	 * The .rd-widget-media-field wrapper is what assets/js/widget-media.js binds to.
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
	 * Configuration form on the (classic) Appearance → Widgets screen.
	 */
	public function form( $instance ) {
		$title      = $instance['title'] ?? '';
		$github     = $instance['github_sponsors'] ?? '';
		$paypal_url = $instance['paypal_url'] ?? '';
		$paypal_qr  = $instance['paypal_qrcode'] ?? '';
		$pix_url    = $instance['pix_url'] ?? '';
		$pix_qr     = $instance['pix_qrcode'] ?? '';
		$pix_key    = $instance['pix_chave'] ?? '';
		?>
		<p class="description" style="margin-bottom:12px;">
			<?php esc_html_e( 'Leave a section empty to hide it. The widget hides itself entirely when nothing is filled in.', 'reloaded' ); ?>
		</p>

		<?php $this->text_field( 'title', __( 'Title (heading):', 'reloaded' ), $title, __( 'Support the Project', 'reloaded' ) ); ?>

		<p style="margin-bottom:4px;"><strong><?php esc_html_e( 'International', 'reloaded' ); ?></strong></p>
		<?php
		$this->text_field( 'github_sponsors', __( 'GitHub Sponsors URL', 'reloaded' ), $github, 'https://github.com/sponsors/user' );
		$this->text_field( 'paypal_url', __( 'PayPal Donation Link', 'reloaded' ), $paypal_url, 'https://www.paypal.com/donate?...' );
		$this->media_field( 'paypal_qrcode', __( 'PayPal QR Code', 'reloaded' ), $paypal_qr );
		?>

		<hr>
		<p style="margin-bottom:4px;"><strong><?php esc_html_e( 'Brazil (PIX)', 'reloaded' ); ?></strong></p>
		<?php
		$this->text_field( 'pix_url', __( 'PIX Link (Copy and Paste)', 'reloaded' ), $pix_url, 'https://nubank.com.br/pagar/xxx' );
		$this->media_field( 'pix_qrcode', __( 'PIX QR Code', 'reloaded' ), $pix_qr );
		$this->text_field( 'pix_chave', __( 'PIX Key', 'reloaded' ), $pix_key, __( 'email@domain.com or CPF/CNPJ', 'reloaded' ) );
	}

	/**
	 * Sanitizes the inputs before saving. URLs via esc_url_raw, the PIX key
	 * (email/CPF/CNPJ) via sanitize_text_field.
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title'           => sanitize_text_field( $new_instance['title'] ?? '' ),
			'github_sponsors' => esc_url_raw( $new_instance['github_sponsors'] ?? '' ),
			'paypal_url'      => esc_url_raw( $new_instance['paypal_url'] ?? '' ),
			'paypal_qrcode'   => esc_url_raw( $new_instance['paypal_qrcode'] ?? '' ),
			'pix_url'         => esc_url_raw( $new_instance['pix_url'] ?? '' ),
			'pix_qrcode'      => esc_url_raw( $new_instance['pix_qrcode'] ?? '' ),
			'pix_chave'       => sanitize_text_field( $new_instance['pix_chave'] ?? '' ),
		);
	}
}

// phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- intentional: widget class + register helper in one logical unit (mirrors the other RD widget files).
function rd_register_support_widget() {
	register_widget( 'RD_Support_Widget' );
}
add_action( 'widgets_init', 'rd_register_support_widget' );
