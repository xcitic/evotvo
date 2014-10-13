<?php

// HOOK INTO REGISTRATION FORM
add_action('register_form','pedadida_register_form');

    function pedadida_register_form (){
    	//$first_name = ( isset( $_POST['first_name'] ) ) ? $_POST['first_name']: '';
    	?>
    	
        <p>
            <label for="first_name"><?php _e('First Name','pedadida') ?><br />
            <input type="text" name="first_name" id="first_name" class="input" value="<?php echo esc_attr(stripslashes($first_name)); ?>" size="25" /></label>
        </p>
    	
    	<?php
    }
    
// MAKE FIRST AND LAST NAME REQUIRED FIELDS
add_filter('registration_errors', 'pedadida_registration_errors', 10, 3);

    function pedadida_registration_errors ($errors, $sanitized_user_login, $user_email) {
        if ( empty( $_POST['first_name'] ) )
            $errors->add( 'first_name_error', __('<strong>ERROR</strong>: You must include a first name.','pedadida') );
          return $errors;
    }
    
// SAVE FIELDS INTO DATABASE
add_action('user_register', 'pedadida_user_register');

    function pedadida_user_register ($user_id) {
    	if ( isset( $_POST['first_name'] ) )
            update_user_meta($user_id, 'first_name', $_POST['first_name']);
      }
