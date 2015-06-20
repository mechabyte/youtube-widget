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
			add_action('admin_menu', array(&$this, 'admin_menu'));
			add_action('admin_init', array(&$this, 'admin_settings'));
			add_action('admin_notices', array(&$this, 'admin_notices'));

		}

		/**
		 * Add menu to WP admin
		 */

		function admin_menu() {
		    add_submenu_page (
		    	'tools.php',
		        __( '(MB) YouTube Videos', 'mechabyte' ),
		        __( '(MB) YouTube Videos', 'mechabyte' ),
		        'manage_options',
		        'mb-youtube-videos',
		        array($this,'admin_menu_fn'),
		        'dashicons-playlist-video'
		    );
		}

		function admin_settings() {
			register_setting( 'mechabyte-youtube-videos', 'mbyt_google_api_key' );
		}

		function admin_menu_fn() {
		?>
		<div class="wrap">
			<h2>Mechabyte YouTube Videos</h2>

			<p>
			As of v3 of YouTube's API your application must have an API key to access information.
			</p>
			<p>
			Brief instructions are included below however you can also find additional information through Google's guide to <a href="https://developers.google.com/youtube/registering_an_application" target="_blank">registering an application</a> and the <a href="https://developers.google.com/youtube/v3/getting-started" target="_blank">getting started guide to YouTube's Data API</a>.
			</p>
			<hr>
			<h4>Create your project and select API services</h4>
				<ol>
					<li>
					Go to the <a href="https://console.developers.google.com/" target="_blank">Google Developer Console</a>.
					</li>
					<li>
						Select a project, or create a new one.
					</li>
					<li>
						In the sidebar on the left, expand <strong>APIs &amp; auth</strong>. Next, click <strong>APIs</strong>. In the list of APIs, make sure the status is <strong>ON</strong> for the <strong>YouTube Data API v3</strong>.
					</li>
					<li>
						In the sidebar on the left, select <strong>Credentials</strong>.
					</li>
					<li>
						The API supports two types of credentials. We'll be using the <strong>API key</strong>. Create one and paste it below.
					</li>
				</ol>
			<hr>

			<form method="post" action="options.php">
			    <?php settings_fields( 'mechabyte-youtube-videos' ); ?>
			    <?php do_settings_sections( 'mechabyte-youtube-videos' ); ?>
			    <table class="form-table">
			        <th scope="row">Google Developer API Key</th>
			        <td><input type="text" name="mbyt_google_api_key" value="<?php echo esc_attr( get_option('mbyt_google_api_key') ); ?>" /></td>
			        </tr>
			    </table>
			    
			    <?php submit_button(); ?>

			</form>
		</div>
		<?php }

		function admin_notices() {
			if(get_option('mbyt_google_api_key') == "") {
				$class = "updated";
				$message = "Changes in v3 of YouTube's Data API require you to take action before <strong>(MB) YouTube Videos</strong> can fetch information. Take a look in <strong>'Tools' -> '<a href='tools.php?page=mb-youtube-videos'>(MB) YouTube Videos'</a></strong> for additional details.";
			    echo"<div class=\"$class\"> <p>$message</p></div>"; 
			}
		}

		/**
		 * Retrieve YouTube User ID from username.
		 */

		function get_channel_id( $username ) {
			$cache = 60*60*24*7; // This value will never change
			$transient_id = 'Mechabyte_Youtube_Channel_ID';
			$channel_id_cache = get_transient( $transient_id );
			if( !channel_id_cache || !property_exists($channel_id_cache,$username) ) {
				//$username 
				$api_key = get_option('mbyt_google_api_key');
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
					if(!$channel_id_cache) {
						$channel_id_cache_obj = new stdClass();
					}
					$channel_id_cache->{"$username"} = $item_data['items'][0]['id'];
					set_transient( $transient_id, $channel_id_cache, $cache);
					return $channel_id_cache->{"$username"};
				} else {
					// Items array doesn't have elements, return provided ID
					return $username;
				}
			} else {
				return $channel_id_cache->{"$username"};
			}
		}

		/**
		 * Retrieve playlist of uploads by YouTube user ID
		 */

		function get_uploads_id( $user_id ) {

			$cache = 60*60*24*7; // This value will never change
			$transient_id = 'Mechabyte_Youtube_Channel_Uploads_ID';
			$channel_id_uploads_cache = get_transient( $transient_id );
			if( !$channel_id_uploads_cache || !property_exists($channel_id_uploads_cache,$user_id) ) {
				//$user_id 
				$api_key = get_option('mbyt_google_api_key');
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
					if(!$channel_id_uploads_cache) {
						$channel_id_uploads_cache_obj = new stdClass();
					}
					$channel_id_uploads_cache->{"$user_id"} = $item_data['items'][0]['contentDetails']['relatedPlaylists']['uploads'];
					set_transient( $transient_id, $channel_id_uploads_cache, $cache);
					return $channel_id_uploads_cache->{"$user_id"};
				}
			} else {
				return $channel_id_uploads_cache->{"$user_id"};
			}

		}

		/**
		 * Retrieve YouTube videos for a given username
		 */

		function get_youtube_videos( $username ) {

			$cache = 60*60*6; // Store transient data for 6 hours, tops
			$transient_id = 'Mechabyte_Youtube_Channel_Videos';
			$cached_channel_item = get_transient( $transient_id );
			$channel_id = $this->get_channel_id( $username );
			$channel_uploads_id = $this->get_uploads_id( $channel_id );
			if( !$cached_channel_item || !property_exists($cached_channel_item,$channel_id) ) {
				$api_key = get_option('mbyt_google_api_key');
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
						$videos[$key]['duration'] = $this->interval_to_seconds( $video_data['items'][0]['contentDetails']['duration'] );
						$videos[$key]['duration_formatted'] = $this->format_time( $videos[$key]['duration'] );
						$videos[$key]['statistics']['viewCount'] = $video_data['items'][0]['statistics']['viewCount'];
						$videos[$key]['statistics']['likeCount'] = $video_data['items'][0]['statistics']['likeCount'];
						$videos[$key]['statistics']['dislikeCount'] = $video_data['items'][0]['statistics']['dislikeCount'];
						$videos[$key]['statistics']['favoriteCount'] = $video_data['items'][0]['statistics']['favoriteCount'];
						$videos[$key]['statistics']['commentCount'] = $video_data['items'][0]['statistics']['commentCount'];
						$videos[$key]['link'] = "https://youtu.be/" . $value['snippet']['resourceId']['videoId'];
					}
				}
				if(!$cached_channel_item) {
					$cached_channel_item = new stdClass();
				}
				$cached_channel_item->{"$channel_id"} = $videos;
				set_transient( $transient_id, $cached_channel_item, $cache);
				return $videos;
			} else {
				return $cached_channel_item->{"$channel_id"};
			}
		}

		/**
		 * Display cached videos in a widget
		 */

		function do_mechabyte_youtube( $username, $number, $display_format = 'decorated', $tab = false ) {
			if (get_option('mbyt_google_api_key') != "") {
				// Retrieve the user's videos with our 'get_youtube_videos' function
				$youtube_videos = $this->get_youtube_videos( $username );
				
				// Create a list with our videos
				if( $youtube_videos ) {
					$output = '<ul class="mechabyte-youtube-videos mechabyte-display-' .$display_format. '">';
					// Apply a filter based on the user's display preferences-- 'mbYT_construct_decorated' or 'mbYT_construct_plain'.
					$output .= apply_filters( 'mbYT_construct_'.$display_format, $youtube_videos, $number, $tab );
					$output .= '</ul>';
				} else {
					// If there was an error, we'll let the user know in the front-end
					$output = '<em>' . __('Whoops! There was an error retrieving the YouTube videos', 'mechabyte') . '</em>';
				}
				return $output;
			} else {
				return null;
			}
		}

		/**
		 * Legacy (spaghetti) widget display code.
		 * Formats stored YouTube data into a decorated list
		 * @return string Spaghetti.exe
		 */

		function mbYT_construct_decorated_default( $youtube_videos, $number, $tab ) {

			$i = 1;
			$output = '';
			foreach( (array) $youtube_videos as $key => $video ) {

				// If we've reached our threshold of videos to display, exit
				if( $i == ($number + 1) )
					break;

				if(isset($video['title'])) {

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

				}

				$i++;

			}

			// Return our generated list items
			return $output;

		}

		function mbYT_construct_plain_default( $youtube_videos, $number, $tab ) {

			// Same documentation as above

			$i = 1;
			$output = '';
			foreach( (array) $youtube_videos as $key => $video ) {

				if( $i == ($number + 1) )
					break;

				if(isset($video['title'])) {

					if( ($i % 2) == 0 ) {
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

				}

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