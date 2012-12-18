<?php
/*
Plugin Name: (MB) YouTube Widget
Plugin URI: http://www.mechabyte.com
Description: A widget that allows you to showcase your most recent YouTube content.
Version: 1.0
Author: Mechabyte - Matthew Smith
Author URI: http://www.mechabyte.com
*/

/**
 * mechabyteYouTube Class
 */

class mechabyteYouTube {

	function __construct() {
		// Add default functions to handle output filters
		add_filter('mbYT_construct_decorated', array(&$this, 'mbYT_construct_decorated_default'), 10, 3 );
		add_filter('mbYT_construct_plain', array(&$this, 'mbYT_construct_plain_default'), 10, 3);
		// Iniatite plugin stuff
		add_action('widgets_init', create_function('', 'register_widget("mechabyteYouTube_Widget");'));
		add_action('wp_enqueue_scripts', array(&$this, 'enqueue_scripts'));
	}

	function get_youtube_videos( $username ) {
		$cache = 60*60; // Store the transient data for 1 hour
		$transient_id = 'mechabyte_YouTube'; // Name our transient 'mechabyte_YouTube'
		$cached_item = get_transient( $transient_id ); // Check to see if we already have cached data. If so, let's continue with that instead of grabbing it again.
		if ( !$cached_item || ( $cached_item->username != $username ) ) {
		
			// YouTube API URL retrieves the fields we want in JSON format so that it's super easy to work with
			$api_url = "https://gdata.youtube.com/feeds/api/users/%s/uploads?fields=entry(published,title,media:group(yt:videoid,yt:duration),yt:statistics,yt:rating)&alt=json&v=2"; 
			// Get the videos for our user with 'wp_remote_get()'
			$response = wp_remote_get( sprintf($api_url, $username) );
			
			// If there's an error getting a user's videos, return false
			if ( is_wp_error( $response ) or ( wp_remote_retrieve_response_code( $response ) != 200 ) ) {
				return false;
			}
			
			// Let's decode what we retrieved so that we can store it efficiently
			$item_data = json_decode( wp_remote_retrieve_body( $response ), true );
			
			// If our info isn't in an array then these next steps won't work-- return false
			if ( !is_array( $item_data ) ) {
				return false;
			}
			
			// Let's sort through everything we've got. We'll keep the stuff we need and drop the rest from the array.
			$item_data = $item_data['feed']['entry'];
			foreach(array_keys($item_data) as $key) {
				$item_data[$key]['title'] = $item_data[$key]['title']['$t']; // Video title
				$item_data[$key]['videoID'] = $item_data[$key]['media$group']['yt$videoid']['$t']; // Video ID
				$item_data[$key]['viewCount'] = number_format($item_data[$key]['yt$statistics']['viewCount']); // Video view count
				$item_data[$key]['published'] = $item_data[$key]['published']['$t']; // Publish date
				$item_data[$key]['duration'] = $this->format_time( $item_data[$key]['media$group']['yt$duration']['seconds'] );
				$item_data[$key]['numLikes'] = number_format($item_data[$key]['yt$rating']['numLikes']); // Number of people who have liked the video
				$item_data[$key]['link'] = 'http://www.youtube.com/watch?v='.$item_data[$key]['videoID']; // Video link
				$img_base = 'http://img.youtube.com/vi/'.$item_data[$key]['videoID']; // Various thumbnail sizes for the video. These might come in handy.
					$item_data[$key]['image']['default'] = $img_base.'/default.jpg';
					$item_data[$key]['image']['mqdefault'] = $img_base.'/mqdefault.jpg';
					$item_data[$key]['image']['hqdefault'] = $img_base.'/hqdefault.jpg';
				// Since we have what we want, we'll drop the rest of the stuff
				unset($item_data[$key]['media$group']);
				unset($item_data[$key]['yt$statistics']);
				unset($item_data[$key]['yt$rating']);
			}

			/* After all that, we now store each video's info like this:
				$item['title']
				$item['videoID']
				$item['viewCount']
				$item['published']
				$item['duration']
				$item['numLikes']
				$item['link']
				$item['image']
					$item['image']['default']
					$item['image']['mqdefault']
					$item['image']['hqdefault'] */
			
			// Create an object with our data
			$data_to_cache = new stdClass();
			$data_to_cache->username = $username;
			$data_to_cache->item_info = $item_data;
			// Store our data object as an item in the 'mechabyte_YouTube' transient
			set_transient( $transient_id, $data_to_cache, $cache );
			
			// Return the data we found
			return $item_data;
		}
		
		// Shweet! We already have a cached version of the data. Let's return it.
		return $cached_item->item_info;
	}

	function do_mechabyte_youtube( $username, $number, $display_format = 'decorated', $tab = false ) {
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
	}

	/* These are the default functions that handle the user's YouTube video data. 
	   Developers can remove these and add their own functions to the filters
	   ('mbYT_construct_decorated_default' & 'mbYT_construct_plain_default')
	   See README.md for more info */

	function mbYT_construct_decorated_default( $youtube_videos, $number, $tab ) {

		$i = 0;
		$output = '';
		foreach( $youtube_videos as $youtube_video ) {
					if( $i == $number )
						break;

					if( $i/2 == 0 ) {
						$class = 'even';
					} else {
						$class = 'odd';
					}

					$output .= '<li class="' . $class . '">';
					$output .= '<a href="' . $youtube_video['link'] . '"';
					if($tab) {
						$output .= ' target="_blank"';
					}
					$output .= '>';
					$output .= '<img src="' . $youtube_video['image']['mqdefault'] . '" />';
					$output .= '<div class="label"><h5>' . $youtube_video['title'] . '</h5></div>';
					$output .= '</a>';
					$output .= '</li>';
					
					$i++;
				}
		return $output; 

	}

	function mbYT_construct_plain_default( $youtube_videos, $number, $tab ) {

		$i = 0;
		$output = '';
		foreach( $youtube_videos as $youtube_video ) {
					if( $i == $number )
						break;

					if ($i % 2 == 0) {
						$class = 'odd';
					} else {
						$class = 'even';
					}

					$output .= '<li class="' . $class . '">';
					$output .= '<a href="' . $youtube_video['link'] . '"';
					if($tab) {
						$output .= ' target="_blank"';
					}
					$output .= '>';
					$output .= $youtube_video['title'];
					$output .= '</a>';
					$output .= '</li>';
					
					$i++;
				}
		return $output;

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

	//
	function enqueue_scripts() {
		wp_enqueue_style( 'mechabyte-youtube', plugins_url( '/css/mechabyte-youtube.css', __FILE__ ) );
	}
}
global $mechabyte_youtube;
$mechabyte_youtube = new mechabyteYouTube();

// If you're an individual & would like to style this widget on your own, simply uncomment the following line. If you're a developer, check out 'README.md'.
//remove_action( 'wp_enqueue_scripts', array( 'mechabyteYouTube', 'enqueue_scripts' ) );

/**
 * Widget
 */

class mechabyteYouTube_Widget extends WP_Widget {

	function __construct() {
		$widget_ops = array( 'description' => __('Use this widget to showcase your YouTube content.') );
		parent::__construct( 'mechabyte-youtube-widget', '(MB) YouTube', $widget_ops );
	}

	function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'] );
		$desc = $instance['description'];
		$username = $instance['username'];
		$number = $instance['number'];
		$display_format = $instance['display_format'];
		$tab = $instance['tab'];

		echo $before_widget;
		if ( !empty( $title ) ) echo $before_title . $title . $after_title;

		if( $desc ) echo '<p>' . $desc . '</p>';

		global $mechabyte_youtube;
		echo $mechabyte_youtube->do_mechabyte_youtube( $username, $number, $display_format, $tab );

		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['description'] = strip_tags($new_instance['description'], '<a><b><strong><i><em>');
		$instance['username'] = trim($new_instance['username']);
		$instance['number'] = trim($new_instance['number']);
		$instance['display_format'] = trim($new_instance['display_format']);
		$instance['tab'] = isset($new_instance['tab']);
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

		$title = $instance['title'];
		$desc = $instance['description'];
		$username = $instance['username'];
		$number = $instance['number'];
		$display_format = $instance['display_format'];
		$tab = $instance['tab'];

		?>

		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr( $title ); ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('description'); ?>"><?php _e('Description:'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('description'); ?>" name="<?php echo $this->get_field_name('description'); ?>" type="text" value="<?php echo $desc; ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('username'); ?>"><?php _e('YouTube Username:'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('username'); ?>" name="<?php echo $this->get_field_name('username'); ?>" type="text" value="<?php echo $username; ?>" />
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of videos to display:'); ?></label>
			<select name="<?php echo $this->get_field_name('number'); ?>">
				<?php for( $i = 1; $i <= 25; $i++ ) { ?>
					<option value="<?php echo $i; ?>" <?php selected( $i, $number ); ?>><?php echo $i; ?></option>
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