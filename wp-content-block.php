<?php
/*
Plugin Name: WP Content Block
Plugin URI: http://www.wordpress.org
Description: Plugin for adding block to context
Author: John Svensson
Version: 2
Author URI: http://www.johnsvensson.com
*/

/*
TODO
- include css in load_custom_wp_admin_files
- check permissions on save
- make settings-field instead of multiple update_post_meta
- auto-inject
*/

// settings page
/*
include 'wpcb-settings.php';
if( is_admin() )
    $my_settings_page = new WPCBSettings();
  */  
include 'wpcb-widget.php';
// register Foo_Widget widget
function register_wpcb_widget() {
    register_widget( 'WPCB_Widget' );
}
add_action( 'widgets_init', 'register_wpcb_widget' );

// create custom post type
add_action('init', 'wpcb_create_post_type');
// add meta box to block post type
add_action('admin_init', 'wcb_add_custom_box', 1);
// save post data
add_action('save_post', 'wcb_save_postdata');
// add css to plugin
add_action('admin_print_styles','wcb_add_admin_css');
// add some files to admin, REMOVED in v2
//add_action( 'admin_enqueue_scripts', 'load_custom_wp_admin_files' );
// add some fancy icons, tnx to @satto satto.se
add_action( 'admin_head', 'wcb_icons' );
// some translation stuff
load_plugin_textdomain('wpcb', false, basename( dirname( __FILE__ ) ) . '/languages');
// add row in Right Now Dashboard
add_action('right_now_content_table_end', 'add_wpcb_counts');
// in later versions
//add_action('dynamic_sidebar', 'wpcb_autoinject', 10, 1);

function wcb_add_admin_css() {
  $x = WP_PLUGIN_URL.'/'.str_replace(basename( __FILE__),"",plugin_basename(__FILE__));
  echo '<link rel="stylesheet" type="text/css" href="'.$x.'/wp-content-block.css" />';
}

function load_custom_wp_admin_files() {
  wp_enqueue_script( 'my_custom_script', plugins_url('/javascript/wpcb.min.js', __FILE__) );
}

// create wpcb (block) post type
function wpcb_create_post_type () {
  register_post_type( 'wpcb',
    array(
      'labels' => array(
        'name' => __( 'Blocks', 'wpcb' ),
        'singular_name' => __( 'Block', 'wpcb' ),
        'add_new_item' => __( 'Add new block', 'wpcb' ),
        'not_found' => __( 'No blocks found.', 'wpcb' )
      ),
    'public' => true,
    'capability_type' => 'page',
    'has_archive' => true,
    'supports' => array('title', 'editor', 'page-attributes', 'thumbnail', 'revisions'),
    'publicly_queryable' => false,
    'exclude_from_search' => true,
    'show_in_nav_menus' => false,
    'rewrite' => false,
    )
  );
}

// add the custom box at block screen
function wcb_add_custom_box() {

  add_meta_box( 
    'wcb_meta_boxes',
    __( 'WP Content Block', 'wpcb' ),
    'wcb_meta_boxes',
    'wpcb',
    'side'
  );
}

function construct_select($active) {

  $output .= "<div class='wpcb-location-wrap'>";
    $pages = get_pages();
    $posts = get_posts(array('post_type' => 'post'));
    //print_r($pages);
    $output .= "<fieldset>";
    
    
    
    $output .= "<div><label class='wpcb-indent wpcb-indent-home' for='wcb_block_specific_page[]'><input type='checkbox' name='wcb_block_specific_page[]' value='home'";
      if ( in_array('home', $active) ) {
        $output .= " checked";  
      }
      $output .= ">Hem</label></div>";

    $output .= "<div><label class='wpcb-indent wpcb-indent-single' for='wcb_block_specific_page[]'><input type='checkbox' name='wcb_block_specific_page[]' value='single'";
      if ( in_array('single', $active) ) {
        $output .= " checked";  
      }
      $output .= ">Singelpost</label></div>";   
    
    
    foreach ( $pages as $page ) :
    
      $depth = count(get_ancestors($page->ID, 'page'));
      $output .= "<div><label class='wpcb-indent wpcb-indent-$depth' for='wcb_block_specific_page[]'><input type='checkbox' name='wcb_block_specific_page[]' value='page-id-$page->ID'";
      if ( in_array('page-id-'.$page->ID, $active) ) {
        $output .= " checked";  
      }
      $output .= ">$page->post_title</label></div>";
      
    endforeach;
    $output .= '<strong>Inlägg</strong>';
    foreach ($posts as $single_post) :
      $output .= "<div><label class='wpcb-indent' for='wcb_block_specific_page[]'><input type='checkbox' name='wcb_block_specific_page[]' value='postid-$single_post->ID'";
      if (in_array('postid-'.$single_post->ID, $active)) {
        $output .= " checked";  
      }
      $output .= ">$single_post->post_title</label></div>";
    endforeach;
    $output .= "</fieldset>";
  $output .= "</div>";

  return $output;
}

function wcb_meta_boxes($post) {
  
  // Use nonce for verification
  wp_nonce_field( plugin_basename( __FILE__ ), 'wcb_noncename' );
  
  echo "<h4>" . __( 'Show block on', 'wpcb' ) . "</h4>";
  $wcb_block_specific_pages = get_post_meta($post->ID, "wcb_block_specific_page");
  
  echo construct_select($wcb_block_specific_pages);

  echo "<h4>" . __( 'Extra classes', 'wpcb' ) . "</h4>";
  echo '<input type="text" id="wcb_extra_block_classes" name="wcb_extra_block_classes" value="' . get_post_meta($post->ID, "wcb_extra_block_classes", true) . '" size="25" />';
  echo '<p class="description">';
       _e("Add extra classes separated by space", 'wpcb' );
  echo '</p>';
  
  echo "<h4>" . __( 'Link', 'wpcb' ) . "</h4>";
  echo '<input type="text" id="wpcb_link" name="wpcb_link" value="' . get_post_meta($post->ID, "wpcb_link", true) . '" size="25" />';
  echo '<p class="description">';
       _e("Where to link this block", 'wpcb' );
  echo '</p>';
  
  echo "<h4>" . __( 'Regions', 'wpcb' ) . "</h4>";
  
  global $wp_registered_sidebars;    
  $e_regions = $wp_registered_sidebars;

  echo '<select name="wcb_regions">';
  echo '<option value="">' . __( 'Hide block', 'wpcb' ). '</option>';
  foreach ( $e_regions as $e_region ) {

    echo '<option ';
    if ( get_post_meta($post->ID, "wcb_regions", true) == trim($e_region['id']) ) echo 'selected="selected" ';
    echo 'value="'.$e_region['id'].'">'.$e_region['name'].'</option>';
  }

  echo "</select>";  

  echo '<p class="description">';
       _e("Show block in specific region", 'wpcb' );
  echo '</p>';
  
/*  
// Moved to settings
  echo "<p>";
    _e("Auto-inject in specified region");
    $autoinject = get_post_meta($post->ID, "wpcb_autoinject", true);
    echo "<div><label for='wpcb_autoinject'><input type='checkbox' name='wpcb_autoinject' value='true'";
      if ( $autoinject ) {
        echo " checked";  
      }
    echo ">".__('Yes', 'wpcb')."</label></div>";
  echo "</p>";
 */
}


// When page is saved, save custom data
function wcb_save_postdata($post_id) {

  // verify if this is an auto save routine. 
  // If it is our form has not been submitted, so we dont want to do anything
  if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

  // verify this came from the our screen and with proper authorization,
  // because save_post can be triggered at other times
  if ( !wp_verify_nonce( $_POST['wcb_noncename'], plugin_basename( __FILE__ ) ) ) return;

  // Check permissions no need for 2? todo.
  if ( 'page' == $_POST['post_type'] ) {
    if ( !current_user_can( 'edit_page', $post_id ) ) return;
  } 
  else {
    if ( !current_user_can( 'edit_post', $post_id ) ) return;
  }

  // OK, we're authenticated: we need to find and save the data
  
  // stuff below? clean up? old stuff?
  global $wpdb;
  $table_name = $wpdb->prefix . "content_block";
  
  // save field extra classes
  if ( $_POST['wcb_extra_block_classes'] ) {
    update_post_meta($post_id, 'wcb_extra_block_classes', $_POST['wcb_extra_block_classes']);
  }
  else {
    delete_post_meta($post_id, 'wcb_extra_block_classes');
  }
  
  // save field extra classes
  if ( $_POST['wpcb_link'] ) {
    update_post_meta($post_id, 'wpcb_link', $_POST['wpcb_link']);
  }
  else {
    delete_post_meta($post_id, 'wpcb_link');
  }
  
  // save field specific page
  delete_post_meta($post_id, 'wcb_block_specific_page');  
  foreach ( $_POST['wcb_block_specific_page'] as $wcb_block_specific_page ) {
    if ( !empty($wcb_block_specific_page) ) {
      add_post_meta($post_id, 'wcb_block_specific_page', $wcb_block_specific_page);   
    }
  }
  
  // save field region
  if ( $_POST['wcb_regions'] ) {
    update_post_meta($post_id, 'wcb_regions', trim($_POST['wcb_regions']));
  }
  else {
    delete_post_meta($post_id, 'wcb_regions');
  }
  
  // save autoinject
  if ( $_POST['wpcb_autoinject'] ) {
    update_post_meta($post_id, 'wpcb_autoinject', $_POST['wpcb_autoinject']);
  }
  else {
    delete_post_meta($post_id, 'wpcb_autoinject');
  }
   
  return;
}


function wcb_output($args) {

  $defaults = array(
    'outer_block_class' => '',
    'inner_block_class' => '',
    'markup' => 'div',
    'before' => '',
    'after' => '',
    'region' => null,
    'echo' => true
  );
      
  $r = wp_parse_args( $args, $defaults );
  extract( $r, EXTR_SKIP );
    
  $conditionals = get_body_class();

  $query = array(
    'post_type' => 'wpcb',
    'order' => 'DESC',
    'orderby' => 'menu_order',
    'posts_per_page' => -1,
    'meta_query' => array(
      array(
        'key' => 'wcb_block_specific_page',
        'value' =>  $conditionals,
      ),
      array(
        'key' => 'wcb_regions',
        'value' =>  $region,
      )
    )
  );
  $results = get_posts($query);
  
  if ( $echo ) :
    global $post;
    foreach ( $results as $post ) {

      $xblock_class = get_post_meta($post->ID, "wcb_extra_block_classes", true);
      $wpcb_link = get_post_meta($post->ID, "wpcb_link", true);
      
      $output .= "<!-- wpcb start -->";
      $output .= "<$markup id='wp-content-block-$post->ID' class='wp-content-block $outer_block_class $xblock_class'>";
      
      if ( $wpcb_link ) $output .= "<a href='$wpcb_link'>";
      
      if ( has_post_thumbnail() ) :
        $output .= "<figure>";
          $output .= get_the_post_thumbnail();
        $output .= "</figure>";
      endif;

      setup_postdata($post);
      if ( $before ) :
        $output .= $before. get_the_title() . $after;
      endif;

        $output .= "<div class='wp-content-block-content wp-content-block-inner $inner_block_class'>";
          $output .= apply_filters('the_content', get_the_content());
        $output .= "</div><!-- /wp-content-block-content --> ";
        if ( current_user_can('edit_page') ) :
          $output .= "<a href='".get_edit_post_link()."'>"._('Edit')."</a>";
        endif;

      if ( $wpcb_link ) $output .= "</a>";
      $output .= "</$markup>";
      $output .= "<!-- wpcb end -->";
      unset($xblock_class);
    }
    
    // check if autoinject is set to true? ->
    // then autoinject the wpcb in selected region 
    // IE no need for php-call in template file.
    //if ( get_post_meta($post->ID, "wpcb_autoinject", true) ) :
    
    //endif;
    
    echo $output; 
  else : 
    return $results;
  endif;
  wp_reset_postdata();

}

/* todo
*/
function wpcb_autoinject($args, $instance) {
  
  $options = get_option('wpcb_options');
  if ( !$options['auto_inject'] ) return;
  static $counter = 0;
  
  // only output wpcb first, kind of hackish, no good wp-actions
  if ( $counter < 1 ) {
    echo wcb_output();
  }
  $counter++;
  return;
}

function add_wpcb_counts() {
  if (!post_type_exists('wpcb')) {
       return;
  }

  $num_posts = wp_count_posts( 'wpcb' );
  $num = number_format_i18n( $num_posts->publish );
  $text = _n( 'Block', 'Blocks', intval($num_posts->publish) );
  if ( current_user_can( 'edit_posts' ) ) {
      $num = "<a href='edit.php?post_type=wpcb'>$num</a>";
      $text = "<a href='edit.php?post_type=wpcb'>$text</a>";
  }
  echo '<tr>';
  echo '<td class="first b b-wpcb">' . $num . '</td>';
  echo '<td class="t wpcbs">' . $text . '</td>';
  echo '</tr>';
}

function wcb_icons() {
    ?>
    <style type="text/css" media="screen">
      .icon32-posts-wpcb { background: url(<?php echo plugins_url('images/wp-content-block-icon32.png',  __FILE__); ?>) 0 0 no-repeat !important; }
      #menu-posts-wpcb .wp-menu-image { background: url(<?php echo plugins_url('images/wp-content-block-icon16-sprite.png',  __FILE__); ?>) no-repeat 6px 6px !important; }
      #menu-posts-wpcb:hover .wp-menu-image, #menu-posts-block.wp-has-current-submenu .wp-menu-image { background-position:6px -23px !important; }
    </style>
<?php } ?>
