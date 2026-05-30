<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Popular Posts Widget                                                *
 *                                                                             *
 * Widget WP nativo (extends WP_Widget) que lista os posts mais visualizados   *
 * por janela temporal. Aparece em Aparência → Widgets sob o nome              *
 * "ReloadeD: Popular Posts" e pode ser arrastado pra qualquer sidebar do      *
 * tema (no momento, só "Main Sidebar").                                       *
 *                                                                             *
 * Diferente dos blocos hardcoded em sidebar.php (Discord, Donations, Ads),    *
 * esse aqui é controlado 100% pela UI nativa de widgets do WP — o admin       *
 * decide se/onde colocar e configura título + janela + quantidade direto      *
 * no widget config.                                                           *
 *                                                                             *
 * Dependências:                                                               *
 *   - rd_stats_top_posts_by_views() em mod-stats.php (cache transient 1h)     *
 *   - rd_format_views_number() em mod-views.php (respeita full/compact)       *
 *******************************************************************************/
class RD_Popular_Posts_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'rd_popular_posts',
			__( 'ReloadeD: Popular Posts', 'reloaded' ),
			array(
				'description' => __( 'Lists most-viewed posts. Uses the theme view tracker, supports time windows (all-time / year / month / week), respects the global "Views Display Format" config.', 'reloaded' ),
				'classname'   => 'widget_rd_popular_posts',
			)
		);
	}

	/**
	 * Frontend output — chamado por dynamic_sidebar() pra cada instância
	 * configurada pelo admin.
	 */
	public function widget( $args, $instance ) {
		$title  = ! empty( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'] ) : __( 'Most Read', 'reloaded' );
		$limit  = ! empty( $instance['limit'] ) ? absint( $instance['limit'] ) : 5;
		$window = ! empty( $instance['window'] ) ? $instance['window'] : 'all';

		// Defensiva: se o módulo de stats não estiver carregado (theme em
		// setup inicial, modo manutenção etc), apenas não renderiza nada.
		if ( ! function_exists( 'rd_stats_top_posts_by_views' ) ) {
			return;
		}

		$posts = rd_stats_top_posts_by_views( $limit, $window );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP widget API convention: $args['before_widget'] is structural HTML defined via register_sidebar()
		echo $args['before_widget'];

		if ( $title ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP widget API convention: before/after_title are structural HTML from register_sidebar(); $title is esc_html'd inline
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}

		if ( empty( $posts ) ) {
			echo '<p class="rd-popular-empty">' . esc_html__( 'No popular posts yet.', 'reloaded' ) . '</p>';
		} else {
			echo '<ul class="rd-popular-posts">';
			foreach ( $posts as $p ) {
				$this->render_item( $p );
			}
			echo '</ul>';
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP widget API convention: $args['after_widget'] is structural HTML defined via register_sidebar()
		echo $args['after_widget'];
	}

	/**
	 * Render de uma linha do ranking — thumb 16/9 + título + views.
	 */
	private function render_item( $post_obj ) {
		$permalink  = get_permalink( $post_obj->post_id );
		$thumb_html = get_the_post_thumbnail(
			$post_obj->post_id,
			'rd-popular-thumb', // Custom size 200x113 registrado em core.php — display 100x56 com DPR 2x retina
			array(
				'loading' => 'lazy',
				'class'   => 'rd-popular-thumb-img',
			)
		);

		$views_formatted = function_exists( 'rd_format_views_number' )
			? rd_format_views_number( $post_obj->views )
			: number_format_i18n( $post_obj->views );

		?>
		<li class="rd-popular-item">
			<a href="<?php echo esc_url( $permalink ); ?>" class="rd-popular-link">
				<div class="rd-popular-thumb-wrap<?php echo $thumb_html ? '' : ' rd-popular-thumb-empty'; ?>" aria-hidden="true">
					<?php echo $thumb_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_the_post_thumbnail() returns already-escaped HTML ?>
				</div>
				<div class="rd-popular-meta">
					<span class="rd-popular-title"><?php echo esc_html( $post_obj->post_title ); ?></span>
				</div>
				<span class="rd-popular-views" aria-label="<?php esc_attr_e( 'Views', 'reloaded' ); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
						<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
						<circle cx="12" cy="12" r="3"></circle>
					</svg>
					<?php echo esc_html( $views_formatted ); ?>
				</span>
			</a>
		</li>
		<?php
	}

	/**
	 * Form de configuração na tela Aparência → Widgets.
	 */
	public function form( $instance ) {
		$title  = isset( $instance['title'] ) ? (string) $instance['title'] : __( 'Most Read', 'reloaded' );
		$limit  = isset( $instance['limit'] ) ? absint( $instance['limit'] ) : 5;
		$window = isset( $instance['window'] ) ? (string) $instance['window'] : 'all';

		$window_options = array(
			'all'   => __( 'All time', 'reloaded' ),
			'year'  => __( 'This year', 'reloaded' ),
			'month' => __( 'This month', 'reloaded' ),
			'week'  => __( 'This week', 'reloaded' ),
		);
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'reloaded' ); ?></label>
			<input class="widefat"
					type="text"
					id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
					value="<?php echo esc_attr( $title ); ?>">
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'window' ) ); ?>"><?php esc_html_e( 'Time window:', 'reloaded' ); ?></label>
			<select class="widefat"
					id="<?php echo esc_attr( $this->get_field_id( 'window' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'window' ) ); ?>">
				<?php foreach ( $window_options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $window, $value ); ?>>
						<?php echo esc_html( $label ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>

		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php esc_html_e( 'Number of posts to show:', 'reloaded' ); ?></label>
			<select class="widefat"
					id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"
					name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>">
				<?php for ( $n = 3; $n <= 15; $n++ ) : ?>
					<option value="<?php echo esc_attr( $n ); ?>" <?php selected( $limit, $n ); ?>>
						<?php echo esc_html( $n ); ?>
					</option>
				<?php endfor; ?>
			</select>
		</p>
		<?php
	}

	/**
	 * Sanitiza os inputs antes de salvar. Whitelist nos enums (window),
	 * clamp no range numérico (limit).
	 */
	public function update( $new_instance, $old_instance ) {
		$valid_windows = array( 'all', 'year', 'month', 'week' );

		return array(
			'title'  => sanitize_text_field( $new_instance['title'] ?? '' ),
			'limit'  => max( 3, min( 15, absint( $new_instance['limit'] ?? 5 ) ) ),
			'window' => in_array( $new_instance['window'] ?? '', $valid_windows, true )
				? $new_instance['window']
				: 'all',
		);
	}
}

// phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- mistura intencional: classe Widget + helper register na mesma unidade lógica. Wave 10 (pré-submissão wp.org) move pra `class-rd-popular-posts-widget.php`.
function rd_register_popular_widget() {
	register_widget( 'RD_Popular_Posts_Widget' );
}
add_action( 'widgets_init', 'rd_register_popular_widget' );
