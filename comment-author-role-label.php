<?php
/*
Plugin Name:  Comment Author Role Label
Plugin URI:   
Description:  Displays a user role next to the comment from a user registered on your site with the ability to customize a background color.
Version:      1.0.0
Author:       Jeremy Roberts
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
*/
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Comment_Author_Role_Label {
	public $version = '1.0.0';

	public function __construct() {
		add_filter( 'get_comment_author', array( $this, 'get_comment_author_role' ), 10, 3 );
		add_filter( 'get_comment_author_link', array( $this, 'comment_author_role' ) );
		add_action('admin_menu', array( $this,'build_comment_author_customization_page'));
		add_action( 'admin_enqueue_scripts', array($this,'add_color_picker') );
		add_filter('admin_init', array($this,'carl_add_settings_fields'));
		add_action('wp_head', array($this,'carl_add_user_CSS'));
	}
	//add JS for the color picker field
	function add_color_picker(){
		if( current_user_can('manage_options') ) { 
     
			// Add the color picker css file       
			wp_enqueue_style( 'wp-color-picker' ); 
         
			// Include the jQuery file with WordPress Color Picker dependency
			wp_enqueue_script( 'comment-author-label-color-picker', plugins_url( 'admin/author-color.js', __FILE__ ), array( 'wp-color-picker' ), $this->version, true ); 
		}
	}
	
	// Add options page	
	function build_comment_author_customization_page(){
			add_comments_page(
			'Comment Author Labels',
			'Author Labels',
			'manage_options',
			'comment_author_RL',
			array($this,'carl_options_page_html')
			);		
	}	
		
	// Get comment author role 
	function get_comment_author_role($author, $comment_id, $comment) {
		global $wp_roles;
		$authoremail = get_comment_author_email( $comment_id ); 
		// Check if user is registered
			if (email_exists($authoremail)) {
				$comment_user_role = get_user_by( 'email', $authoremail );
				$comment_user_role = array_shift( $comment_user_role->roles );
				$comment_user_attr = $comment_user_role; // Let's store a copy of the slug form of the role here. We'll need it for the attribute.
				
				// If the user role exists as a registered WP Role (it better!)
				if ( ! empty( $wp_roles->roles[ $comment_user_role ]['name'] ) ) {
					// Let's get the display name of the role
					$comment_user_role = $wp_roles->roles[ $comment_user_role ]['name'];
					$comment_user_role = translate_user_role( $comment_user_role );
				} else {
					// If for some reason the role isn't registered, we can still make most of them look decent.
					$comment_user_role = ucfirst( $comment_user_role );
				}

				// HTML output to add next to comment author name
				$this->comment_user_role = ' <span class="comment-author-label comment-author-label-' . esc_attr( str_replace(" ", "-", $comment_user_role) ) . '" >' . esc_html( $comment_user_role ) . '</span>';
			} else { 
				$this->comment_user_role = '';
			} 
		return $author;
	} 
 
	// Display comment author                   
	function comment_author_role( $author = '' ) {
		if ( is_string( $author ) ) {
			$author .= $this->comment_user_role; 
		} else {
			$author = '';
		}

		return $author; 
	} 
	//list out user roles on options page
	function carl_add_settings_fields(){

		//add section for fields
		add_settings_section(
		'comment_author_RL_page',
		__('Comment Author Role Label Options','comment-author-role-label' ),
		'carl_header_section_cb',
		'comment_author_RL'
		);
		
		//get a list of the user roles
		global $wp_roles;
		$roles = $wp_roles->get_names(); 
		
		// get_option and update_option not adding anything?
		$data = get_option('carl_option_group', false );
		if( ! $data ){
			update_option( 'carl_option_group', $data);
		}
		
		
		//loop through and create each field
		foreach($roles as $roles =>$user_role){
			$no_space_role = str_replace(" ", "-", $user_role);
			//ensure roles have a default set
			if (! isset($data[$no_space_role]) || $data[$no_space_role] == false ){
				$data[$no_space_role] = "#a8a8a8";
			}
			
			//add a settings field for each user role
			add_settings_field(
			'carl_field_'.$no_space_role, 
			__( $user_role,'comment-author-role-label'),
			'carl_field_cb',
			'comment_author_RL',
			'comment_author_RL_page',
			$args = [
			'label_for' => 'carl_field_'.$no_space_role,
			'this_role' => 'carl_option_group['.$no_space_role.']',
			'user_role' => $no_space_role,
			'user_color' => $data[$no_space_role],
			]
		);
		//register the setting
		register_setting('comment_author_RL','carl_option_group', array('sanitize_callback' => 'carl_validate_options',));
	
		}
		
		//callback for header information
		function carl_header_section_cb( $args ) {
			?>
			<p id="<?php echo esc_attr( $args['id'] ); ?>"><?php esc_html_e( 'Author Background Colors', 'comment-author-role-label' ); ?></p>
			<?php
		}
		//added markup for each field to be a color picker
		function carl_field_cb(array $args){
			echo '<input type="text" name=',$args['this_role'],' class="carl-color-field" size="5" maxlength="7" value=',esc_attr($args['user_color']),' />';
			
		}
		
		//validate the fields are hex values
		function carl_validate_options( $data ) {
		
		global $wp_roles;
		$roles = $wp_roles->get_names();
			foreach($roles as $roles =>$user_role){
				$no_space_role = str_replace(" ", "-", $user_role);
				$user_hex_val = strip_tags( stripslashes($data[$no_space_role]));
				echo $user_hex_val . ' hex? ' . $no_space_role . ' role?';
				if(FALSE === carl_check_color($user_hex_val)){
					//add settings error
					add_settings_error( 'carl_option_group', 'carl_color_error', __('Insert a valid color, invalid colors set to #a8a8a8', 'comment-author-role-label'), 'error' );
					//set to the default value if 
					$data[$no_space_role] = '#a8a8a8';
				}
				
			}
		return apply_filters( 'carl_validate_options', $data);
		
		}
		//returns true if value is hex and false if not
		function carl_check_color( $hex_value ) { 
     
			if ( preg_match( '/^#[a-f0-9]{6}$/i', $hex_value ) ) { // if user insert a HEX color with #     
				return true;
			}
     
		return false;
		}		
		
	}
	//set up the html for options page
	function carl_options_page_html(){
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="wrap">
		<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
		<form action="options.php" method="post">
		<?php
		settings_errors('carl_option_group');
		// output security fields for the registered setting "wporg"
		settings_fields( 'comment_author_RL' );
		// output setting sections and their fields
		// (sections are registered for "wporg", each field is registered to a specific section)
		do_settings_sections( 'comment_author_RL' );
		// output save settings button
		submit_button( __( 'Save Settings','primary', 'comment-author-role-label' ) );
		?>
		</form>
		</div>
		<?php
		
	}
	
	//add the chosen colors to the site's style through wp_head
	function carl_add_user_CSS(){
		
		$userCSS = get_option('carl_option_group', false );
		 if ( empty( $userCSS ) || ! is_array( $userCSS ) ) {

              return;

       }
		
		echo PHP_EOL . '<style>' . PHP_EOL . '.comment-author-label {'. PHP_EOL .'padding: 5px;'. PHP_EOL . 'border-radius: 3px;}' .PHP_EOL;
		foreach($userCSS as $user_name=>$CSSval){
			$no_space_name = str_replace(" ", "-", $user_name);
			echo 'span.comment-author-label.comment-author-label-'.esc_attr($no_space_name).'{ background-color:'.esc_attr($userCSS[$no_space_name]).' ;} '. PHP_EOL;
		}
		echo  '</style>' . PHP_EOL;
	}
	
}
new Comment_Author_Role_Label();
