<?php
defined( 'ABSPATH' ) || exit;

/*******************************************************************************
 * Module: Latest Video Widget                                                 *
 *                                                                             *
 * Native WP widget (extends WP_Widget) that shows the most recent video from  *
 * a chosen category. It scans the latest posts in that category and grabs the *
 * first YouTube embed found in the content.                                   *
 *                                                                             *
 * When the YouTube Facade is ON it reuses the same click-to-load facade       *
 * markup (lightweight thumbnail; navigation.js swaps in the iframe on click). *
 * When OFF it shows the same thumbnail as a plain link to the post, so the    *
 * sidebar/footer never loads a heavy iframe.                                  *
 *                                                                             *
 * Like RD_Popular_Posts_Widget, it can be dropped into any registered widget  *
 * area (Main Sidebar or Footer) and positioned via Appearance → Widgets.      *
 *                                                                             *
 * Dependencies (inc/mod-performance.php):                                     *
 *   - rd_youtube_extract_id()    — finds the first video ID in content        *
 *   - rd_youtube_facade_markup() — .rd-facade wrapper (facade on)             *
 *   - rd_youtube_cover_html()    — thumbnail + play button (facade off)       *
 *******************************************************************************/
class RD_Latest_Video_Widget extends WP_Widget {

	/** How many recent posts to scan for a usable YouTube video. */
	const SCAN_LIMIT = 10;

	public function __construct() {
		parent::__construct(
			'rd_latest_video',
			__( 'ReloadeD: Latest Video', 'reloaded' ),
			array(
				'description' => __( 'Shows the most recent video from a chosen category. Reuses the YouTube facade when enabled; otherwise links to the post.', 'reloaded' ),
				'classname'   => 'widget_rd_latest_video',
			)
		);
	}

	/**
	 * Frontend output — called by dynamic_sidebar() for each instance.
	 */
	public function widget( $args, $instance ) {
		$title = ! empty( $instance['title'] ) ? apply_filters( 'widget_title', $instance['title'] ) : __( 'Latest Video', 'reloaded' );
		$cat   = ! empty( $instance['category'] ) ? absint( $instance['category'] ) : 0;

		// No category chosen, or the facade helpers aren't loaded → render nothing.
		if ( ! $cat || ! function_exists( 'rd_youtube_extract_id' ) ) {
			return;
		}

		$video = $this->get_latest_video( $cat );
		if ( null === $video ) {
			return; // no post with a YouTube video in this category
		}

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP widget API convention: $args['before_widget'] is structural HTML defined via register_sidebar()
		echo $args['before_widget'];

		if ( $title ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP widget API convention: before/after_title are structural HTML from register_sidebar(); $title is esc_html'd inline
			echo $args['before_title'] . esc_html( $title ) . $args['after_title'];
		}

		echo '<div class="rd-lvideo">';

		if ( rd_get_option_bool( 'facade_youtube' ) ) {
			// Facade ON — reuse the click-to-load facade (navigation.js handles the click).
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup built with esc_attr() inside rd_youtube_facade_markup()
			echo rd_youtube_facade_markup( $video['id'] );
		} else {
			// Facade OFF — same thumbnail, but a plain link to the post (keeps the sidebar light).
			echo '<a class="rd-lvideo__poster" href="' . esc_url( $video['permalink'] ) . '" aria-label="' . esc_attr( $video['title'] ) . '">';
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- cover markup built with esc_attr() inside rd_youtube_cover_html()
			echo rd_youtube_cover_html( $video['id'] );
			echo '</a>';
		}

		echo '<a class="rd-lvideo__title" href="' . esc_url( $video['permalink'] ) . '">' . esc_html( $video['title'] ) . '</a>';

		echo '</div>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WP widget API convention: $args['after_widget'] is structural HTML defined via register_sidebar()
		echo $args['after_widget'];
	}

	/**
	 * Finds the most recent published post in $cat_id whose content has a
	 * YouTube video. Result (or a "none" marker) is cached in a transient,
	 * busted on save_post/before_delete_post — so the scan runs at most once
	 * per hour per category.
	 *
	 * @return array{id:string,title:string,permalink:string}|null
	 */
	private function get_latest_video( $cat_id ) {
		$cache_key = 'rd_latest_video_' . $cat_id;
		$cached    = get_transient( $cache_key );

		if ( is_array( $cached ) ) {
			return $cached;
		}
		if ( 'none' === $cached ) {
			return null;
		}

		$posts = get_posts(
			array(
				'cat'                    => $cat_id,
				'post_status'            => 'publish',
				'posts_per_page'         => self::SCAN_LIMIT,
				'orderby'                => 'date',
				'order'                  => 'DESC',
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		foreach ( $posts as $post ) {
			$video_id = rd_youtube_extract_id( $post->post_content );
			if ( '' !== $video_id ) {
				$result = array(
					'id'        => $video_id,
					'title'     => get_the_title( $post ),
					'permalink' => get_permalink( $post ),
				);
				set_transient( $cache_key, $result, HOUR_IN_SECONDS );
				return $result;
			}
		}

		// Cache the negative result too, so we don't re-scan every page load.
		set_transient( $cache_key, 'none', HOUR_IN_SECONDS );
		return null;
	}

	/**
	 * Configuration form on the Appearance → Widgets screen.
	 */
	public function form( $instance ) {
		$title = isset( $instance['title'] ) ? (string) $instance['title'] : __( 'Latest Video', 'reloaded' );
		$cat   = isset( $instance['category'] ) ? absint( $instance['category'] ) : 0;
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
			<label for="<?php echo esc_attr( $this->get_field_id( 'category' ) ); ?>"><?php esc_html_e( 'Category:', 'reloaded' ); ?></label>
			<?php
			wp_dropdown_categories(
				array(
					'show_option_none'  => __( '— Select —', 'reloaded' ),
					'option_none_value' => 0,
					'hide_empty'        => false,
					'selected'          => $cat,
					'name'              => $this->get_field_name( 'category' ),
					'id'                => $this->get_field_id( 'category' ),
					'class'             => 'widefat',
				)
			);
			?>
		</p>
		<?php
	}

	/**
	 * Sanitizes the inputs before saving.
	 */
	public function update( $new_instance, $old_instance ) {
		return array(
			'title'    => sanitize_text_field( $new_instance['title'] ?? '' ),
			'category' => absint( $new_instance['category'] ?? 0 ),
		);
	}
}

// phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed -- intentional mix: Widget class + register/flush helpers in the same logical unit (mirrors class-rd-popular-posts-widget.php).
function rd_register_latest_video_widget() {
	register_widget( 'RD_Latest_Video_Widget' );
}
add_action( 'widgets_init', 'rd_register_latest_video_widget' );

/**
 * Busts the Latest Video transient for each category a post belongs to, so the
 * widget refreshes as soon as a video post is published/edited/deleted instead
 * of waiting out the 1h TTL.
 */
function rd_latest_video_flush_cache( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	foreach ( wp_get_post_categories( $post_id ) as $cat_id ) {
		delete_transient( 'rd_latest_video_' . $cat_id );
	}
}
add_action( 'save_post', 'rd_latest_video_flush_cache' );
add_action( 'before_delete_post', 'rd_latest_video_flush_cache' );
