<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Social Networks Widget ("Follow Us")                                *
 *                                                                             *
 * Native WP widget that lists the portal's social networks as a 2-column      *
 * grid of "[icon] Network name" chips (8 networks = 4 rows of 2).             *
 *                                                                             *
 * Division of responsibilities (theme convention):                            *
 *   - Network URLs = GLOBAL site data → stay in the panel                     *
 *     (Integrations → Social Networks), shared with the footer/top-bar row.   *
 *   - The widget only decides DISPLAY: which networks to show (checkboxes).   *
 *     No follower counts: live numbers would require per-network API          *
 *     credentials, and manual text nobody keeps updated. Rows render as a     *
 *     2-column grid (8 networks = 4 rows of 2).                               *
 *                                                                             *
 * Icons/labels/order come from rd_social_get_icons()/rd_social_get_networks() *
 * in mod-social.php — the same library as the footer icon row.                *
 ******************************************************************************/
class RD_Social_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'rd_social',
			__( 'ReloadeD: Social Networks', 'reloaded' ),
			array(
				'description' => __( '"Follow Us" grid of the portal\'s social networks (URLs come from Integrations → Social Networks). Just pick which networks to show.', 'reloaded' ),
				'classname'   => 'widget_rd_social',
			)
		);
	}

	/**
	 * Frontend output. A network renders only when BOTH checked in the widget
	 * AND configured with a URL in the panel. Nothing qualifying → no output.
	 */
	public function widget( $args, $instance ) {
		if ( ! function_exists( 'rd_social_get_networks' ) ) {
			return;
		}

		$rows = '';
		foreach ( rd_social_get_networks() as $slug => $label ) {
			if ( empty( $instance[ 'show_' . $slug ] ) ) {
				continue;
			}
			$url = (string) rd_get_option( 'social_' . $slug );
			if ( '' === $url ) {
				continue;
			}

			$icons = rd_social_get_icons();

			$rows .= sprintf(
				'<a class="rd-social-row rd-%1$s" href="%2$s" target="_blank" rel="noopener noreferrer">%3$s<span class="rd-social-row__name">%4$s</span></a>',
				esc_attr( $slug ),
				esc_url( $url ),
				$icons[ $slug ] ?? '', // static SVG from the hardcoded library
				esc_html( $label )
			);
		}

		if ( '' === $rows ) {
			return; // nothing checked/configured → no empty widget shell
		}

		$title = ! empty( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'] ) : __( 'Follow Us', 'reloaded' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP widget API convention: before_widget is structural HTML from register_sidebar()
		echo $args['before_widget'];
		if ( $title ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- before/after_title are structural HTML; $title esc_html'd inline
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rows built above with esc_url/esc_attr/esc_html; SVGs are static theme assets
		echo '<div class="rd-social-list">' . $rows . '</div>';
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP widget API convention
		echo $args['after_widget'];
	}

	/**
	 * Configuration form: title + one show/hide checkbox per network.
	 * Networks without a panel URL appear disabled with a hint.
	 */
	public function form( $instance ) {
		$title = $instance['title'] ?? '';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title (heading):', 'reloaded' ); ?></label>
			<input class="widefat" type="text"
					id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
					value="<?php echo esc_attr( $title ); ?>"
					placeholder="<?php esc_attr_e( 'Follow Us', 'reloaded' ); ?>">
		</p>

		<p class="description" style="margin-bottom:10px;">
			<?php esc_html_e( 'URLs come from Integrations → Social Networks. Networks without a URL are disabled here.', 'reloaded' ); ?>
		</p>

		<?php
		if ( ! function_exists( 'rd_social_get_networks' ) ) {
			return;
		}
		foreach ( rd_social_get_networks() as $slug => $label ) :
			$has_url = '' !== (string) rd_get_option( 'social_' . $slug );
			$checked = ! empty( $instance[ 'show_' . $slug ] );
			?>
			<p style="display:flex;align-items:center;gap:8px;margin:6px 0;">
				<input type="checkbox"
						id="<?php echo esc_attr( $this->get_field_id( 'show_' . $slug ) ); ?>"
						name="<?php echo esc_attr( $this->get_field_name( 'show_' . $slug ) ); ?>"
						value="1" <?php checked( $checked ); ?> <?php disabled( ! $has_url ); ?>>
				<label for="<?php echo esc_attr( $this->get_field_id( 'show_' . $slug ) ); ?>">
					<?php echo esc_html( $label ); ?>
					<?php if ( ! $has_url ) : ?>
						<em class="description"> — <?php esc_html_e( 'no URL configured', 'reloaded' ); ?></em>
					<?php endif; ?>
				</label>
			</p>
		<?php endforeach; ?>
		<?php
	}

	/**
	 * Sanitizes the inputs before saving.
	 */
	public function update( $new_instance, $old_instance ) {
		$out = array(
			'title' => sanitize_text_field( $new_instance['title'] ?? '' ),
		);
		if ( function_exists( 'rd_social_get_networks' ) ) {
			foreach ( array_keys( rd_social_get_networks() ) as $slug ) {
				$out[ 'show_' . $slug ] = empty( $new_instance[ 'show_' . $slug ] ) ? 0 : 1;
			}
		}
		return $out;
	}
}

// phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- intentional: widget class + register helper in one logical unit (mirrors the other RD widget files).
function rd_register_social_widget() {
	register_widget( 'RD_Social_Widget' );
}
add_action( 'widgets_init', 'rd_register_social_widget' );
