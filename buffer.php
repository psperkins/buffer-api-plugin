<?php
/*
  Plugin Name: Custom Buffer
  Description: Add most gawked to buffer for scheduled posting
  Version: 1.0
  License: GPLv2 or later
 */



class GV_buffer_settings {

	var $plugin_url;

	function __construct() {
		add_action( 'admin_menu', array( $this, 'add_buffer_settings_page' ) );
        add_action( 'admin_init', array( $this, 'buffer_page_init' ) );
		$this->plugin_url = plugins_url( null, __FILE__ );
	}

	public function add_buffer_settings_page() {
        add_options_page(
            'Buffer Settings', 
            'Buffer Settings', 
            'manage_options', 
            'buffer-settings-admin', 
            array( $this, 'create_admin_page' )
        );
    }

    public function create_admin_page() {
        $this->options = get_option( 'buffer_api_options' );
        ?>
        <div class="wrap">
            <h2>Buffer Settings</h2>           
            <form method="post" action="options.php">
            <?php
                settings_fields( 'buffer_options' );   
                do_settings_sections( 'buffer-admin' );
                submit_button(); 
                //$this->gv_query_most_gawked();
                $this->gv_build_postdata();
        ?>
        	</form>
        </div>
        <?php
    }

     public function buffer_page_init() {        
        register_setting(
            'buffer_options',
            'buffer_api_options',
            array( $this, 'sanitize' )
        );

        add_settings_section(
            'buffer_api_settings',
            'Buffer API Settings',
            array( $this, 'print_section_info' ),
            'buffer-admin'
        );

        add_settings_field(
            'access_token',
            'Access Token',
            array( $this, 'access_token_callback' ),
            'buffer-admin',
            'buffer_api_settings'           
        );

        add_settings_field(
            'twitter_count',
            'Most Gawked Threshold - Twitter',
            array( $this, 'twitter_count_callback' ),
            'buffer-admin',
            'buffer_api_settings'         
        );

        add_settings_field(
            'fb_count',
            'Most Gawked Threshold - Facebook', 
            array( $this, 'fb_count_callback' ),
            'buffer-admin',
            'buffer_api_settings'         
        );

        add_settings_field(
            'pin_count',
            'Most Gawked Threshold - Pinterest',
            array( $this, 'pin_count_callback' ),
            'buffer-admin',
            'buffer_api_settings'     
        );

    }

    public function sanitize( $input ) {
        $new_input = array();
        if( isset( $input['access_token'] ) )
            $new_input['access_token'] = esc_attr( $input['access_token'] );
        if( isset( $input['twitter_count'] ) )
            $new_input['twitter_count'] = intval( $input['twitter_count'] );
        if( isset( $input['fb_count'] ) )
            $new_input['fb_count'] = intval( $input['fb_count'] );
        if( isset( $input['pin_count'] ) )
            $new_input['pin_count'] = intval( $input['pin_count'] );

        return $new_input;
    }

    public function print_section_info() {
        print 'Enter Buffer API settings below:';
    }

    public function access_token_callback() {
        printf(
            '<input type="text" id="access_token" size="55" name="buffer_api_options[access_token]" value="' . $this->options['access_token'] . '" />',
            isset( $this->options['access_token'] ) ? esc_attr( $this->options['access_token']) : ''
        );
    }

    public function twitter_count_callback() {
        printf(
            '<input type="text" id="twitter_count" size="4" maxlength="4" name="buffer_api_options[twitter_count]" value="' . $this->options['twitter_count'] . '" />',
            isset( $this->options['twitter_connt'] ) ? esc_attr( $this->options['twitter_count']) : ''
        );
    }

    public function fb_count_callback() {
        printf(
            '<input type="text" id="fb_count" size="4" maxlength="4" name="buffer_api_options[fb_count]" value="' . $this->options['fb_count'] . '" />',
            isset( $this->options['fb_connt'] ) ? esc_attr( $this->options['fb_count']) : ''
        );
    }

    public function pin_count_callback() {
    	$options = get_option( 'buffer_api_options' );
        $pin_count = $options['pin_count'];
        printf(
            '<input type="text" id="pin_count" size="4" maxlength="4" name="buffer_api_options[pin_count]" value="' . $pin_count . '" />',
            isset( $this->options['pin_count'] ) ? esc_attr( $this->options['pin_count']) : ''
        );
    }

    public function get_gv_buffer_profile_data( ) {
		$profiles_url = 'https://api.bufferapp.com/1/profiles.json';
        $options = get_option( 'buffer_api_options' );
        $options_token = $options['access_token'];
        	
        $curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_URL => $profiles_url ."?access_token=".$options_token,
		));

		$resp = json_decode(curl_exec($curl), TRUE);
		curl_close($curl);

		if($resp){
			$svcs = array();
			echo "<h3>Active Social Accounts linked to this key on Buffer</h3>";
			foreach ($resp as $item){
				echo "<p>" . $item['formatted_service'] ." - ".$item['_id']."</p>";
				$svcs[] = $item['_id'];
			}
		}
			//echo "<pre>";
			//var_dump($resp);
			//echo "</pre>";
			return $svcs;	
    }

    private function gv_query_most_gawked() {
    	$options = $this->options;
    	$options = array_splice( $options, 1 );
    	$buffer = array();
    	foreach( $options as $option => $value ) {
    		switch( $option ){
    			case "fb_count":
    				$svc = "Facebook";
    				break;
    			case "twitter_count":
    				$svc = "Twitter";
    				break;
    			case "pin_count":
    				$svc = "Pinterest";
    				break;
    		}
	    	$args = array(
	    		'post_type' => 'post',
	    		'post_status' => 'publish',
	    		'posts_per_page' => 5,
	    		'meta_key' => 'gawked',
	    		'meta_query' => array(
	    			array(
			    	'key' => 'gawked',
			    	'value' => $value, 
			    	'type' => 'NUMERIC',
			    	'compare' => '>',
	    			),
	    		)
	    	);

	    	$buff_content = array();
	    	$gawk = new WP_Query( $args );

			if ( $gawk->have_posts() ) {
				$count = 0;
				while ( $gawk->have_posts() ) {
					$gawk->the_post();
					$buff_content[$count]['content'] = get_the_excerpt();
					$buff_content[$count]['url'] = get_the_permalink();
					$buff_content[$count++]['title'] = get_the_title();
					//echo '<li><a href="' . get_the_permalink() . '">' . get_the_title() . '</a></li>';
				}
			}
			$postarr = json_encode( $buff_content, false );
			$buffer[] = $buff_content;
			wp_reset_postdata();
			
	    }
	    	//echo "<pre>";
			//print_r($buffer);
			//echo "</pre>";
			return $buffer;
	}

	public function gv_build_postdata() {
		$postto = "https://api.bufferapp.com/1/updates/create.json";
		$options = $this->options;
        $options_token = $options['access_token'];
        $postarr = $this->gv_query_most_gawked();
        $svcarr = $this->get_gv_buffer_profile_data();
        foreach( $postarr as $piece ) {
        	foreach( $piece as $pc => $val ){
        		$text = $val['content'];
		        foreach( $svcarr as $svcid => $val ) {
		        	$svcid = $val;
					$data = array(
						'access_token' => $options_token,
						'profile_ids[]' => $svcid,
						'text' => $text
					);

					//echo " " . $this->gv_post_to_buffer( $postto, $data );
				}
			}
		}
		
	}

	public function gv_post_to_buffer( $url, $post ) {
		$ch = curl_init( $url );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, "POST" );  
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $post );
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 ); 
		$result = curl_exec( $ch );
		curl_close( $ch );
		return $result;
    }

}

$gv_buffer_settings = new GV_buffer_settings;

