<?php
/*
Plugin Name: Game Fruit WP Publisher
Plugin URI:
Description: A Plugin to easily manage and Publish Game Fruit Games on Wordpress
Version: 0.1
Author: sasky
Author URI: http://sasky.org
License: GPLv2

*/

class Gf_game_uploader {
    public function __construct(){
        if(is_admin()){
            // set up custom post type.
            add_action( 'init', array( $this, 'create_custom_post_type' ));
            add_action( 'add_meta_boxes', array( $this , 'register_upload_zip_meta_box' ));
            // form and processing of data and assets
            add_action( 'post_edit_form_tag' , array( $this , 'post_edit_form_tag' ));
            add_action( 'save_post', array( $this , 'process_zip_file' ));
            add_action( 'before_delete_post', array( $this , 'remove_game_file_on_delete'));
        } else {
            add_shortcode('gf_game',array (&$this, 'shortcode_hook') );
        }
    }
    function post_edit_form_tag( ) {
               echo ' enctype="multipart/form-data"';
    }
    public function create_custom_post_type(){

        $labels = array(
            'name'               => _x( 'Games', 'post type general name' ),
            'singular_name'      => _x( 'Game', 'post type singular name' ),
            'add_new'            => _x( 'Add New', 'game' ),
            'add_new_item'       => __( 'Add New Game' ),
            'edit_item'          => __( 'Edit Game' ),
            'new_item'           => __( 'New Game' ),
            'all_items'          => __( 'All Games' ),
            'view_item'          => __( 'View Game' ),
            'search_items'       => __( 'Search Games' ),
            'not_found'          => __( 'No games found' ),
            'not_found_in_trash' => __( 'No games found in the Trash' ),
            'parent_item_colon'  => '',
            'menu_name'          => 'Games'
        );

        $args = array(
            'labels'             => $labels,
            'description'        => 'Holds a list of Game Fruit games that can be displayed on this site',
            'public'             => false,
            'menu_position'      => 65,
            'supports'           => array( 'title', 'editor' ),
            'has_archive'        => false,
            'show_ui'            => true,
            'show_in_nav_menus'  => false,
            'publicly_queryable' => true,
            'show_in_menu'       => true,
            'show_in_admin_bar'  => false,
            'menu_icon'=> '',// TODO get menu icon
            'hierarchical' => false
        );
        register_post_type( 'gf_games', $args );

    }
    function register_upload_zip_meta_box() {
        add_meta_box( 'upload_zip_box',
                    __( 'Upload the Zip file' ),
                    array( $this , 'upload_zip_meta_box' ),
                    'gf_games',
                    'normal',
                    'default' );

        add_meta_box( 'display_options',
                      __('Display Options'),
                      array( $this , 'display_options_meta_box'),
                      'gf_games',
                      'side',
                      'default');
    }
    function upload_zip_meta_box( $post ) {
    	wp_nonce_field( plugin_basename( __FILE__ ), 'upload_zip_meta_box' );

        $url_to_game = get_post_meta($post->ID, 'url_to_game', true);

        if($url_to_game){
            echo '<strong> To Publish this game, place the following Shortcode in the content of any post or page. </strong></br>';
            echo '<strong style="color:green" > [gf_game id="' . $post->ID . '"]</strong>';
           // echo '<h5> The game has been uploaded to <br/> '. $url_to_game .' </h5>';

        } ?>
    <h4><?php _e('Upload a file in .zip format'); ?></h4>
        <p class="install-help"><?php _e('If you have a plugin in a .zip format, you may install it by uploading it here.'); ?></p>
        <label class="screen-reader-text" for="gamezip"><?php _e('Plugin zip file'); ?></label>
        <input type="file" id="gamezip" name="gamezip" />
        <?php
    }

    public function display_options_meta_box( $post ){
        $defaults = array(  'width'        => 650,
                            'height'       => 500,
                            'show_title'   => 'false',
                            'show_disc'    => 'false');

        $args = get_post_meta( $post->ID, 'game_options', true );

        if(is_array( $args )){
            $args = $args + $defaults;
        } else {
            $args = $defaults;
        }

        wp_nonce_field( plugin_basename( __FILE__ ), 'upload_zip_meta_box' );
        ?>
        <label for="width_of_game" >Width</label>
        <input name="game_options[width]" type="text" value="<?php echo $args['width'] ?>"/><br />
        <br />
        <label for="height_of_game" >Height</label>
        <input name="game_options[height]" type="text" value="<?php echo $args['height'] ?>"/><br />
        <br />
        <label for="show_title" >Display the Title</label><br />
        <input type="radio" name="game_options[show_title]" value="false" <?php checked($args['show_title'],'false') ?> />No<br />
        <input type="radio" name="game_options[show_title]" value="true" <?php checked($args['show_title'],'true') ?> /> Yes<br />
        <br />
        <label for="show_disc" >Display the Description </label><br />
        <input type="radio" name="game_options[show_disc]" value="false" <?php checked($args['show_disc'],'false')?> />No<br />
        <input type="radio" name="game_options[show_disc]" value="true" <?php checked($args['show_disc'],'true') ?>" /> Yes<br />
    <?php
    }

    public function process_zip_file( $post_id ) {
        global $post_type;
        if ( $post_type != 'gf_games' )
            return;
        if( empty($_POST) || !isset($_POST['upload_zip_meta_box']))
            return;
        // sercuity mumbo jumbo
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
            return;
        if ( !wp_verify_nonce( $_POST['upload_zip_meta_box'], plugin_basename( __FILE__ ) ) )
            return;
        if ( 'page' == $_POST['post_type'] ) {
            if ( !current_user_can( 'edit_page', $post_id ) )
            return;
        } else {
            if ( !current_user_can( 'edit_post', $post_id ) )
            return;
        }

        // get the WP File system working

        $url = wp_nonce_url('wp-admin/post.php','gamezip-upload');
        if (false === ($creds = request_filesystem_credentials($url, '', false, false, null) ) ) {
            wp_die('problem connecting to the WP file system');
        }
        if ( ! WP_Filesystem($creds) ) {
            wp_die('problem connecting to the WP file system');
        }
        global $wp_filesystem;

        // Upload the file and Unzip it.
        // TODO v 1+ make the error notes more user friendly
        //1. make sure the file is a zip. and its legit

        $zip_name = $_FILES['gamezip']['name'];
        if($zip_name){ // upload and save the files
            if( ! preg_match('/^[0-9a-zA-Z\-_]+\.zip$/', $zip_name))
                wp_die('Sorry, the file must end in the .zip extension, and can only contain letters (a-z) or (A-Z), numbers (0-9), underscores (_) and hyphens (-). No spaces allowed.');

            //2. Upload the zip file

            $file_upload = wp_upload_bits( $_FILES['gamezip']['name'], null, file_get_contents($_FILES['gamezip']['tmp_name']));

            if (  $file_upload['error']  )
                wp_die( 'error uploading the zip archive ' .$file_upload['error'] );

            // If there are files already in the desination directory, then delete them

            $upload_dir = wp_upload_dir();
            $upgrade_folder = $upload_dir['basedir'] . '/games/'. $post_id . '/';

            if($wp_filesystem->is_dir( $upgrade_folder )){
                $wp_filesystem->delete( $upgrade_folder , true );
            }

            //3. unzip the zip

            $result = unzip_file($file_upload['file'], $upgrade_folder);
            if( ! $result)
                wp_die('Problem unzipping the file' .$result );

            //4. Delete the zip
            $temp_zip_file= $file_upload['file'];
            if( $wp_filesystem->is_file( $temp_zip_file )){
                $wp_filesystem->delete( $temp_zip_file );
            }

            //5. Place the url to the files in the post meta
            $zip_name = preg_replace( '/\.zip$/','', $zip_name);
            $dir =  $upgrade_folder . $zip_name;


            if( $wp_filesystem->is_dir( $dir) ) {
                // find the html doc
                $dir_list = $wp_filesystem->dirlist( $dir );
                // save directory to post meta so I can delete the files on a post delete hook
                update_post_meta( $post_id, 'dir', $upgrade_folder );
                $html_file = '';
                foreach($dir_list as $file){
                    if($file['type'] == 'f'){
                        // this regx may need a bit more love, but should do the trick
                       if(preg_match('/(\.html|\.htm)$/',$file['name'] )){
                           $html_file = $file['name'];
                       }
                    }
                }
                // TODO if there is more than one html file, it will get the last one, refine this perhaps?
                if(!$html_file)
                    wp_die('you need to upload one html file in your zip file ');

                $url_to_game = content_url('/uploads/games/' . $post_id . '/' . $zip_name . '/' .$html_file );
                update_post_meta( $post_id, 'url_to_game', $url_to_game );

            } else {
                wp_die('There was a problem with the name of your ziped file');
            }
        }

        // Lets just quickly save the options meta box as well
        // TODO a bit of verification wouldn't go a miss
        $game_options = $_POST['game_options'];
        update_post_meta( $post_id, 'game_options', $game_options );

    }
    public function remove_game_file_on_delete( $postid ){

        global $post_type;
        if ( $post_type != 'gf_games' ) return;

        $url = wp_nonce_url('wp-admin/edit.php','gamezip-delete');
        if (false === ($creds = request_filesystem_credentials($url, '', false, false, null) ) ) {
            wp_die('problem connecting to the WP file system');
        }
        if ( ! WP_Filesystem($creds) ) {
            wp_die('problem connecting to the WP file system');
        }
        global $wp_filesystem;

        $dir = get_post_meta( $postid , 'dir' , true );
        global $wp_filesystem;

        if( $dir ){
            $wp_filesystem->delete( $dir , true);
        }

        }
    public function shortcode_hook( $args ){
        // I made the booleans strings to help with the shortcode parsing
        $defaults = array(    'id'           => null,
                              'width'        => 650,
                              'height'       => 500,
                              'show_title'   => 'false',
                              'show_disc'    => 'false');

        $from_options = get_post_meta( $args['id'], 'game_options', true );
        if(!is_array($from_options))
            $from_options = $defaults;

        // shortcode options overide options from post meta, which in turn overide the defaults
        // $from_options should always be set so $defaults isn't really needed, just here for clarity

        $args = $args + $from_options ;

        $url = get_post_meta( $args['id'] , 'url_to_game', true);

        $title = '';
        $disc = '';
        // only grab the loop if needed
        if( $args['show_title'] == 'true' || $args['show_disc'] == 'true' ){
            $query = new WP_Query( array('post_type' => 'gf_games',
                                         'p' => $args['id'] ) );

                 while( $query->have_posts() ){
                     $query->next_post();

                     $title = $query->post->post_title;
                     $disc  = $query->post->post_content;
                 }
            wp_reset_postdata();
        }


        $html = '<div class="games">';
        if( $args['show_title'] == 'true' ){
            $html .= '<h3 class="game_title">' .$title . '</h3>';
        }

        $html .= '<iframe width="' . $args['width'] . '" height="' . $args['height'] . '"
        src="' . $url . '" scrolling="no" frameborder="no" seamless="seamless" ></iframe>';

        if( $args['show_disc'] == 'true' ){
            $html .= '<p class="game_disc">' .$disc . '</p>';
        }
        $html .= '</div>';
        return $html;
    }
}
new Gf_game_uploader();