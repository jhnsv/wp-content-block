<?php
/*
Plugin Name: WP Content Block
Plugin URI: http://www.wordpress.org
Description: Plugin for adding block to context
Author: John Svensson
Version: 1.0
Author URI: http://www.johnsvensson.com
*/

// create custom post type
add_action('init', 'wcb_create_post_type');
// add meta box to block post type
add_action('admin_init', 'wcb_add_custom_box', 1);
// save post data
add_action('save_post', 'wcb_save_postdata');
// add css to plugin
add_action('admin_print_styles','wcb_add_admin_css');
// some translation stuff
add_action('init', 'wcb_textdomain');

// stolen from ... guess? Drupal! Well it's open source aint it?
function drupal_match_path($path, $patterns) {
  static $regexps;

  if (!isset($regexps[$patterns])) {
    // Convert path settings to a regular expression.
    // Therefore replace newlines with a logical or, /* with asterisks and the <front> with the frontpage.
    $to_replace = array(
      '/(\r\n?|\n)/', // newlines
      '/\\\\\*/', // asterisks
      '/(^|\|)\\\\<front\\\\>($|\|)/', // <front>
    );
    $replacements = array(
      '|',
      '.*',
      '\1' . preg_quote('', '/') . '\2',
    );
    $patterns_quoted = preg_quote($patterns, '/');
    $regexps[$patterns] = '/^(' . preg_replace($to_replace, $replacements, $patterns_quoted) . ')$/';
  }
  return preg_match($regexps[$patterns], $path);
}

function wcb_textdomain() {
  if (function_exists('load_plugin_textdomain')) {
    load_plugin_textdomain('wp-content-block', false, 'wp-content-block');
  }		
}

function wcb_add_admin_css() {
  $x = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__));
  echo '<link rel="stylesheet" type="text/css" href="'.$x.'/wp-content-block.css" />';
}

// create block post type
function wcb_create_post_type () {
  register_post_type( 'block',
    array(
      'labels' => array(
		    'name' => __( 'Blocks' ),
				'singular_name' => __( 'Block' )
			),
		'public' => true,
		'capability_type' => 'page',
		'has_archive' => true,
		'supports' => array('title', 'editor', 'page-attributes')
		)
	);
}

// add the custom box at block screen
function wcb_add_custom_box() {    

  add_meta_box( 
    'wcb_meta_boxes',
    __( 'WP content block', 'wcb_textdomain' ),
    'wcb_meta_boxes',
    'block',
    'side'
  );
}

function wcb_meta_boxes($post) {
  
  // Use nonce for verification
  wp_nonce_field( plugin_basename( __FILE__ ), 'wcb_noncename' );
  
  echo "<h4>" . __( 'Show block on specific pages', 'wcb_textdomain' ) . "</h4>";
  echo '<textarea id="wcb_block_specific_page" name="wcb_block_specific_page">';
  echo get_post_meta($post->ID, "wcb_block_specific_page", true);
  echo '</textarea>';
  echo '<p class="description">';
  _e("Specify pages by using their paths. Enter one path per line. The '*' character is a wildcard. Example paths are blog for the blog page and blog/* for every personal blog. &lt;front&gt; is the front page.", 'wcb_textdomain' );
  echo '</p>';

  echo "<h4>" . __( 'Extra classes', 'wcb_textdomain' ) . "</h4>";
  echo '<input type="text" id="wcb_extra_block_classes" name="wcb_extra_block_classes" value="' . get_post_meta($post->ID, "wcb_extra_block_classes", true) . '" size="25" />';
  echo '<p class="description">';
       _e("Add extra classes separated by space", 'wcb_textdomain' );
  echo '</p>';
  
  echo "<h4>" . __( 'Regions', 'wcb_textdomain' ) . "</h4>";
  //echo '<input type="text" id="wcb_regions" name="wcb_regions" value="' . get_post_meta($post->ID, "wcb_regions", true) . '" size="25" />';
  
	$e_regions = explode("\n", get_option('block_options_regions'));
	//echo get_post_meta($post->ID, "wcb_regions", true);
  echo '<select name="wcb_regions">';
  echo '<option value="">- None -</option>';
  foreach ( $e_regions as $e_region ) {
  	//echo trim($e_region);
  	echo '<option ';
  	if ( get_post_meta($post->ID, "wcb_regions", true) == trim($e_region) ) echo 'selected="selected" ';
  	echo 'value="'.$e_region.'">'.$e_region.'</option>';
  }

	echo "</select>";  
  echo '<p class="description">';
       _e("Show block in specific region", 'wcb_textdomain' );
  echo '</p>';
}


// When page is saved, save custom data
function wcb_save_postdata($post_id) {

  // verify if this is an auto save routine. 
  // If it is our form has not been submitted, so we dont want to do anything
  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

  // verify this came from the our screen and with proper authorization,
  // because save_post can be triggered at other times
  if ( !wp_verify_nonce( $_POST['wcb_noncename'], plugin_basename( __FILE__ ) ) ) return;

  // Check permissions
  if ( 'page' == $_POST['post_type'] ) {
    if ( !current_user_can( 'edit_page', $post_id ) ) return;
  } 
  else {
    if ( !current_user_can( 'edit_post', $post_id ) ) return;
  }

  // OK, we're authenticated: we need to find and save the data
  global $wpdb;
  $table_name = $wpdb->prefix . "content_block";
  
  // save field extra classes
  if ( $_POST['wcb_extra_block_classes'] ) {
    update_post_meta($post_id, 'wcb_extra_block_classes', $_POST['wcb_extra_block_classes']);
  }
  else {
    delete_post_meta($post_id, 'wcb_extra_block_classes');
  }
  
  // save field specific page
  if ( $_POST['wcb_block_specific_page'] ) {
    update_post_meta($post_id, 'wcb_block_specific_page', trim(htmlentities($_POST['wcb_block_specific_page']),'/'));
  }
  else {
    delete_post_meta($post_id, 'wcb_block_specific_page');
  }
  
  // save field region
  if ( $_POST['wcb_regions'] ) {
    update_post_meta($post_id, 'wcb_regions', trim($_POST['wcb_regions']));
  }
  else {
    delete_post_meta($post_id, 'wcb_regions');
  }
   
  return;
}


function wcb_output($block_class='', $title='', $title_after='', $region=null) {

  $title_after = str_replace("<", "</", $title);
  global $post;
  global $wpdb;
  
  $rs = $wpdb->get_results("SELECT * 
    FROM $wpdb->postmeta
    WHERE $wpdb->postmeta.meta_key = 'wcb_block_specific_page'
  ");
  
  
  $permalink = esc_url($_SERVER["SERVER_NAME"] . $_SERVER["REQUEST_URI"]);
    
  // clean up the path
  $remove = get_bloginfo('url');
  $path = rtrim(str_replace($remove."/","",$permalink),"/");
  
  if ( is_front_page() || is_home() ) $path = htmlentities("<front>");
  
  foreach ( $rs as $result ) {
    if ( drupal_match_path($path, $result->meta_value) )
      $get_blocks[] =  $result->post_id;
  }
 
  
  if ( $get_blocks ) {
  	$my_query = array( 'post_type' => 'block', 'post__in' => $get_blocks, 'orderby' => 'menu_order', 'order' => 'ASC' );
  	
  	if ( $region ) {
  		$my_query['meta_key'] = 'wcb_regions';
			$my_query['meta_value'] = $region;
  	}
  	
    $the_query = new WP_Query( $my_query );
    
    while ( $the_query->have_posts() ) : $the_query->the_post();
      $block_class = get_post_meta($post->ID, "wcb_extra_block_classes", true);
      $output .= "<section id=\"wp-content-block-".$post->ID."\" class=\"wp-content-block module ".$block_class."\">\n";

          if ( $title ) :
            $output .= "\t\t" . $title;
              $output .= get_the_title(); 
            $output .= $title_after . "\n";
          endif;
          $output .= "\t\t<div class=\"wp-content-block-content inner\">\n";
            $output .= "\t\t\t" . wpautop(do_shortcode(get_the_content())); 
          $output .= "\t\t</div> <!-- /wp-content-block-content -->\n";

      $output .= "</section>\n";
      unset($block_class);
    endwhile;
  
    wp_reset_postdata();
        
    $html = preg_replace('#(<img.+?)height=(["\']?)\d*\2(.*?/?>)#i', '$1$3', $output);   
    return preg_replace('#(<img.+?)width=(["\']?)\d*\2(.*?/?>)#i', '$1$3', $html);   
  } //if

}

// options page
add_action('admin_menu', 'plugin_admin_add_page');
function plugin_admin_add_page() {
	add_options_page('Block options', 'Block options', 'activate_plugins', 'block_options', 'block_options_page');
}
?>
<?php // display the admin options page
function block_options_page() {
?>  
	<div class="wrap">  
		<?php screen_icon('options-general'); ?><h2><?php _e("Block options", 'wcb_textdomain' ); ?></h2>  
		<form method="post" action="options.php">  
			<?php wp_nonce_field('update-options') ?>  
			<table class="form-table">

				<tr valign="top">
				<th scope="row"><?php _e( 'Regions' ); ?></th>
    			<td>
						<textarea rows="10" cols="50" class="code" id="block_options_regions" name="block_options_regions"><?php echo get_option('block_options_regions'); ?></textarea>
						<p class="description">Add regions, one region per row</p>
    			</td>
  			</tr> 
  		</table>
			<?php submit_button(); ?>
			<input type="hidden" name="action" value="update" />  
      <input type="hidden" name="page_options" value="block_options_regions" /> 
    </form>  
	</div>  
<?php  
}  



?>