<?php
/*
Plugin Name: (MB) YouTube Widget
Plugin URI: http://www.mechabyte.com
Description: A widget that allows you to showcase your most recent YouTube content. Updated for YouTube's V3 API.
Version: 2.0
Author: Mechabyte - Matthew Smith
Author URI: http://www.mechabyte.com
*/

/**
 * Mechabyte_Youtube Class.
 * Define functions that will be used to format and display data by the widget
 */
 
	class Mechabyte_Youtube {

		function __construct() {

			// Add default hooks to handle the output filters
			add_filter('mbYT_construct_decorated', array(&$this, 'mbYT_construct_decorated_default'), 10, 3 );
			add_filter('mbYT_construct_plain', array(&$this, 'mbYT_construct_plain_default'), 10, 3);

			// Initiate plugin & add scripts
			add_action('widgets_init', create_function('', 'register_widget("Mechabyte_Youtube_Widget");'));
			add_action('wp_enqueue_scripts', array(&$this, 'enqueue_scripts'));

		}

		/**
		 * Retrieve YouTube User ID from username.
		 */

		function get_channel_id( $username ) {
			$cache = 60*60*24*7; // This value will never change
			$transient_id = 'Mechabyte_Youtube_Channel_ID';
			$channel_id_cache = get_transient( $transient_id );
			if( !channel_id_cache || !$channel_id_cache->{"$username"} ) {
				//$username 
				$api_key = "AIzaSyDpDehnSyxcY1_47ob7bgaeFSwEg8T5X5g";
				$api_url = "https://www.googleapis.com/youtube/v3/channels?part=contentDetails&forUsername=%s&key=%s";
				$response = wp_remote_get( sprintf($api_url, $username, $api_key) );

				// If there's an error, bail
				if( is_wp_error( $response ) || ( wp_remote_retrieve_response_code( $response ) != 200 ) ) {
					return false;
				}

				// Decode Google's JSON response into an array
				$item_data = json_decode( wp_remote_retrieve_body( $response), true );

				// Ensure we're working with an array
				if( !is_array( $item_data ) ) {
					return false;
				}

				// Check if we've already been provided an ID
				if( $item_data['items'][0] && ($item_data['items'][0]['kind'] == "youtube#channel") ) {
					$channel_id_cache_obj = new stdClass();
					$channel_id_cache_obj->{"$username"} = $item_data['items'][0]['id'];
					set_transient( $transient_id, $channel_id_cache_obj, $cache);
					return $channel_id_cache_obj->{"$username"};
				} else {
					// Items array doesn't have elements, return provided ID
					return $username;
				}
			}
			return $channel_id_cache->{"$username"};
		}

		/**
		 * Retrieve playlist of uploads by YouTube user ID
		 */

		function get_uploads_id( $user_id ) {

			$cache = 60*60*24*7; // This value will never change
			$transient_id = 'Mechabyte_Youtube_Channel_Uploads_ID';
			$channel_id_uploads_cache = get_transient( $transient_id );
			if( !channel_id_uploads_cache || !$channel_id_uploads_cache->{"$user_id"} ) {
				//$user_id 
				$api_key = "AIzaSyDpDehnSyxcY1_47ob7bgaeFSwEg8T5X5g";
				$api_url = "https://www.googleapis.com/youtube/v3/channels?part=contentDetails&id=%s&key=%s";
				$response = wp_remote_get( sprintf($api_url, $user_id, $api_key) );
				if( is_wp_error( $response ) || ( wp_remote_retrieve_response_code( $response ) != 200 ) ) {
					return false;
				}
				$item_data = json_decode( wp_remote_retrieve_body( $response), true );
				if( !is_array( $item_data ) ) {
					return false;
				}

				// Check if we've already been provided an ID
				if( $item_data['items'][0] && ($item_data['items'][0]['kind'] == "youtube#channel") ) {
					$channel_id_uploads_cache_obj = new stdClass();
					$channel_id_uploads_cache_obj->{"$user_id"} = $item_data['items'][0]['contentDetails']['relatedPlaylists']['uploads'];
					set_transient( $transient_id, $channel_id_uploads_cache_obj, $cache);
					return $channel_id_uploads_cache_obj->{"$user_id"};
				}
			}
			return $channel_id_uploads_cache->{"$user_id"};

		}

		/**
		 * Retrieve YouTube videos for a given username
		 */

		function get_youtube_videos( $username ) {

			$cache = 60*60*6; // Store transient data for 6 hours, tops
			$transient_id = 'Mechabyte_Youtube_Videos';
			$cached_video_item = get_transient( $transient_id );
			$channel_id = self::get_channel_id( $username );
			$channel_uploads_id = self::get_uploads_id( $channel_id );
			if( !$cached_video_item || !$cached_video_item->{"$channel_id"}) {
				$api_key = "AIzaSyDpDehnSyxcY1_47ob7bgaeFSwEg8T5X5g";
				$api_url = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&playlistId=%s&order=date&maxResults=25&key=%s&type=video";
				$response = wp_remote_get( sprintf($api_url, $channel_uploads_id, $api_key) );
				if( is_wp_error($response) || (wp_remote_retrieve_response_code($response) != 200 )) {
					return false;
				}

				$search_data = json_decode( wp_remote_retrieve_body( $response ), true );
				if( !is_array($search_data) ) {
					return false;
				} 

				$videos_data = $search_data['items'];
				$videos = array();
				foreach($videos_data as $key => $value) {
					if($value['snippet']['resourceId']['kind'] == "youtube#video") {
						$videos[$key]['id'] = $value['snippet']['resourceId']['videoId'];

						$video_details_url = "https://www.googleapis.com/youtube/v3/videos?part=snippet,statistics,contentDetails&id=%s&maxResults=1&key=%s";
						$video_response = wp_remote_get( sprintf($video_details_url, $videos[$key]['id'], $api_key));
						if( is_wp_error($video_response) || (wp_remote_retrieve_response_code($response) != 200 )) {
							unset($videos[$key]);
						}
						$video_data = json_decode( wp_remote_retrieve_body( $video_response ), true );
						if( !is_array($video_data) ) {
							unset($videos[$key]);
						}

						$videos[$key]['publishedAt'] = $video_data['items'][0]['snippet']['publishedAt'];
						$videos[$key]['title'] = $video_data['items'][0]['snippet']['title'];
						$vidoes[$key]['description'] = $video_data['items'][0]['snippet']['description'];
						$videos[$key]['thumbnails']['default'] = $video_data['items'][0]['snippet']['thumbnails']['default']['url'];
						$videos[$key]['thumbnails']['medium'] = $video_data['items'][0]['snippet']['thumbnails']['medium']['url'];
						$videos[$key]['thumbnails']['high'] = $video_data['items'][0]['snippet']['thumbnails']['high']['url'];
						$videos[$key]['duration'] = self::interval_to_seconds( $video_data['items'][0]['contentDetails']['duration'] );
						$videos[$key]['duration_formatted'] = self::format_time( $videos[$key]['duration'] );
						$videos[$key]['statistics']['viewCount'] = $video_data['items'][0]['statistics']['viewCount'];
						$videos[$key]['statistics']['likeCount'] = $video_data['items'][0]['statistics']['likeCount'];
						$videos[$key]['statistics']['dislikeCount'] = $video_data['items'][0]['statistics']['dislikeCount'];
						$videos[$key]['statistics']['favoriteCount'] = $video_data['items'][0]['statistics']['favoriteCount'];
						$videos[$key]['statistics']['commentCount'] = $video_data['items'][0]['statistics']['commentCount'];
						$videos[$key]['link'] = "https://youtu.be/" . $value['snippet']['resourceId']['videoId'];
					}
				}
				$video_cache_obj = new stdClass();
				$video_cache_obj->{"$channel_id"} = $videos;
				set_transient( $transient_id, $video_cache_obj, $cache);
				return $videos;
			}

			return $cached_video_item->{"$channel_id"};
		}

		/**
		 * Legacy (spaghetti) widget display code.
		 * Formats stored YouTube data into a decorated list
		 * @return string Spaghetti.exe
		 */

		function mbYT_construct_decorated_default( $youtube_videos, $number, $tab ) {

			$i = 0;
			$output = '';
			foreach( $youtube_videos as $video ) {

				// If we've reached our threshold of videos to display, exit
				if( $i == $number )
					break;

				// Determine if the video we're showing is even or odd in our list
				if( $i/2 == 0 ) {
					$class = 'even';
				} else {
					$class = 'odd';
				}

				// Output the list element depending on widget instance settings
				$output .= '<li class="' . $class . '">';
				$output .= '<a title="' . $video['title'] . '" href="' . $video['link'] . '"';
				if($tab) {
					$output .= ' target="_blank"';
				}
				$output .= '>';
				$output .= '<img src="' . $video['thumbnails']['medium'] . '" />';
				$output .= '<div class="label"><h5>' . $video['title'] . '</h5></div>';
				$output .= '</a>';
				$output .= '</li>';

				$i++;

			}

			// Return our generated list items
			return $output;

		}

		function mbYT_construct_plain_default( $youtube_videos, $number, $tab ) {

			// Same documentation as above

			$i = 0;
			$output = '';
			foreach( $youtube_videos as $video ) {

				if( $i == $number )
					break;

				if( $i/2 == 0 ) {
					$class = 'even';
				} else {
					$class = 'odd';
				}

				$output .= '<li class="' . $class . '">';
				$output .= '<a title="' . $video['title'] . '" href="' . $video['link'] . '"';
				if($tab) {
					$output .= ' target="_blank"';
				}
				$output .= '>' . $video['title'] . '</a>';
				$output .= '</li>';

				$i++;

			}

			return $output;

		}

		/**
		 * Return formatted time string.
		 * Formats int value of seconds into HH:MM:SS string
		 * @return string HH:MM:SS representation of duration (seconds)
		 */

		function interval_to_seconds($date_time) {
			$interval = new DateInterval($date_time);
			$duration = 0;
			if($interval->d) {
				$duration += $interval->d * 60*60*24;
			}
			if($interval->h) {
				$duration += $interval->h * 60*60;
			}
			if($interval->i) {
				$duration += $interval->i * 60;
			}
			if($interval->s) {
				$duration += $interval->s;
			}
			return $duration;
		}

		function format_time($s) {
		    $time = round($s);
		    $parts = array();
		    while ($time >= 1) {
		        array_unshift($parts, $time % 60);
		        $time /= 60;
		    }
		    if ($s < 60) {
		        // if it is seconds only, prepend "0:"
		        array_unshift($parts, '0');
		    }
		    $last = count($parts) - 1;
		    if ($parts[$last] < 10) {
		        $parts[$last] .= '0';
		    }
		    $duration = join(':', $parts);
		    return $duration;
		}

		/**
		 * Enqueue (MB) Youtube Widget's stylesheet.
		 * Disable with `wp_dequeue_style( 'mechabyte-youtube', array( 'Mechabyte_Youtube', 'enqueue_scripts' ) )`
		 */

		function enqueue_scripts() {
			wp_enqueue_style( 'mechabyte-youtube', plugins_url( '/css/mechabyte-youtube.css', __FILE__ ) );
		}
	} 

	// Initiate MechabyteYoutube class to gain access to its functions
	global $mechabyte_youtube;
	$mechabyte_youtube = new Mechabyte_Youtube();

/**
 * Mechabyte_Youtube_Widget Class.
 * Extend Wordpress' widget class to our liking
 */

	class Mechabyte_Youtube_Widget extends WP_Widget {

		function __construct() {
			$widget_ops = array(
				'description' => __('Use this widget to showcase your YouTube content.','mechabyte')
			);
			parent::__construct( 'mechabyte-youtube-widget', '(MB) YouTube', $widget_ops );
		}

		function widget( $args, $instance ) {
			extract( $args );
			$title = apply_filters( 'widget_title', $instance['title'] );

			// Obey theme settings for widget output
			echo $before_widget;
			if( !empty( $instance['title'] ) ) {
				echo $before_title . $instance['title'] . $after_title;
			}

			if( $instance['description'] ) {
				echo ('<p>' . $instance['description'] . '</p>');
			}

			global $mechabyte_youtube;
			echo $mechabyte_youtube->do_mechabyte_youtube( $instance['username'], $instance['number'], $instance['display_format'], $instance['tab'] );

			echo $after_widget;
		}

		function update( $new_instance, $old_instance ) {
			$instance = $old_instance;
			$instance['title'] = strip_tags( $new_instance['title']);
			$instance['description'] = strip_tags( $new_instance['description'], '<a><b><strong><i><em>');
			$instance['username'] = trim( $new_instance['username'] );
			$instance['number'] = trim( $new_instance['number'] );
			$instance['display_format'] = trim( $new_instance['display_format'] );
			$instance['tab'] = isset( $new_instance['tab'] );
			return $instance;
		}

		function form( $instance ) {
			$defaults = array(
				'title' => __( 'Latest Videos', 'mechabyte' ),
				'description' => '',
				'username' => 'TechTeenagerTV',
				'number' => 2,
				'display_format' => 'image',
				'tab' => false
			);
			$instance = wp_parse_args( (array) $instance, $defaults );
			?>

			<p>
				<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
				<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('description'); ?>"><?php _e('Description:'); ?></label>
				<input class="widefat" id="<?php echo $this->get_field_id('description'); ?>" name="<?php echo $this->get_field_name('description'); ?>" type="text" value="<?php echo $instance['description']; ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('username'); ?>"><?php _e('YouTube Username:'); ?></label>
				<input class="widefat" id="<?php echo $this->get_field_id('username'); ?>" name="<?php echo $this->get_field_name('username'); ?>" type="text" value="<?php echo $instance['username']; ?>" />
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of videos to display:'); ?></label>
				<select name="<?php echo $this->get_field_name('number'); ?>">
					<?php for( $i = 1; $i <= 25; $i++ ) { ?>
						<option value="<?php echo $i; ?>" <?php selected( $i, $instance['number'] ); ?>><?php echo $i; ?></option>
					<?php } ?>
				</select>
			</p>
			<p>
				<label for="<?php echo $this->get_field_id('display_format'); ?>"><?php _e('Display format:'); ?></label>
				<select id="<?php echo $this->get_field_id( 'display_format' ); ?>" name="<?php echo $this->get_field_name( 'display_format' ); ?>">
					<option value="plain"<?php if($instance['display_format'] == 'plain') { echo (' selected="selected"'); } ?>><?php _e('Plain List') ?></option>
					<option value="decorated"<?php if($instance['display_format'] == 'decorated') { echo (' selected="selected"'); } ?>><?php _e('Decorated List') ?></option>
				</select>
			</p>
			<p>
				<input class="checkbox" type="checkbox" <?php checked(isset( $instance['tab'] ) ? $instance['tab'] : 0  ); ?> id="<?php echo $this->get_field_id( 'tab' ); ?>" name="<?php echo $this->get_field_name( 'tab' ); ?>" />
				<label for="<?php echo $this->get_field_id( 'tab' ); ?>"><?php _e('Open videos in a new tab') ?></label>
			</p>

			<?php
		}
	}


?>