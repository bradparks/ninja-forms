<?php

function ninja_forms_display_js($form_id, $local_vars = ''){
	global $post, $ninja_forms_display_localize_js;

	// Get all of our form fields to see if we need to include the datepicker and/or jqueryUI
	$datepicker = 0;
	$qtip = 0;
	$mask = 0;
	$currency = 0;
	$rating = 0;
	$calc_value = array();
	$calc_fields = array();
	$calc_eq = false;
	$sub_total = false;
	$tax = false;
	$fields = ninja_forms_get_fields_by_form_id( $form_id );
	if( is_array( $fields ) AND !empty( $fields ) ){
		foreach( $fields as $field ){
			$field_id = $field['id'];
			$field_type = $field['type'];
			$field['data'] = apply_filters( 'ninja_forms_display_script_field_data', $field['data'], $field_id );
			if( isset( $field['data']['datepicker'] ) AND $field['data']['datepicker'] == 1 ){
				$datepicker = 1;
			}

			if( isset( $field['data']['show_help'] ) AND $field['data']['show_help'] == 1 ){
				$qtip = 1;
			}

			if( isset( $field['data']['mask'] ) AND $field['data']['mask'] != '' ){
				$mask = 1;
			}

			if( isset( $field['data']['mask'] ) AND $field['data']['mask'] == 'currency' ){
				$currency = 1;
			}

			if( $field_type == '_rating' ){
				$rating = 1;
			}

			// Populate an array of calculation values for the form fields.
			// Check to see if this field has a calc_value. If it does, add this to our calc_value array so that we can tell what it is in our JS.
			if ( isset( $field['data']['calc_value'] ) ) {
				$calc_value[$field_id] = $field['data']['calc_value'];
			} else if ( $field_type == '_list' ) {
				// Get a list of options and their 'calc' setting.
				if ( isset ( $field['data']['list']['options'] ) AND is_array ( $field['data']['list']['options'] ) ) {
					$list_options = $field['data']['list']['options'];
					foreach ( $list_options as $option ) {
						if ( isset ( $field['data']['list_show_value'] ) AND $field['data']['list_show_value'] == 1 ) {
							$key = $option['value'];
						} else {
							$key = $option['label'];
						}
						if ( !isset ( $option['calc'] ) OR ( isset ( $option['calc'] ) AND empty ( $option['calc'] ) ) ) {
							$option['calc'] = 0;
						}
						$calc_value[$field_id][$key] = $option['calc'];
					}
				}
			}

			// Check to see if this is a tax field;
			if ( $field_type == '_tax' ) {
				$tax = $field_id;
			}

			// Check to see if this is a calculation field. If it is, store it in our calc_fields array along with its method.
			if ( $field_type == '_calc' ) {
				if ( isset ( $field['data']['calc_method'] ) ) {
					$calc_method = $field['data']['calc_method'];
				} else {
					$calc_method = 'auto';
				}

				// Check to see if this is a sub_total calculation
				if ( isset ( $field['data']['payment_sub_total'] ) AND $field['data']['payment_sub_total'] == 1 ) {
					$sub_total = $field_id;
				}
				
				switch ( $calc_method ) {
					case 'auto':
						$calc_fields[$field_id] = array( 'method' => 'auto' );
						break;
					case 'fields':
						$field_ops = $field['data']['calc'];
						$calc_fields[$field_id] = array( 'method' => 'fields', 'fields' => $field_ops );
						break;
					case 'eq':
						$calc_fields[$field_id] = array( 'method' => 'eq', 'eq' => $field['data']['calc_eq'] );
						$calc_eq = true;
						break;
				}

				$calc_fields[$field_id]['places'] = $field['data']['calc_places'];
			}
		}
		
		// Loop through our fields again looking for calc fields that are totals.
		foreach( $fields as $field ){
			$field_id = $field['id'];
			$field_type = $field['type'];
			if ( $field_type == '_calc' ) {
				if ( isset ( $field['data']['payment_total'] ) AND $field['data']['payment_total'] == 1 ) {
					if ( $sub_total AND $tax AND $field['data']['calc_method'] == 'auto' ) {
						$calc_fields[$field_id]['method'] = 'eq';
						$calc_fields[$field_id]['eq'] = 'field_'.$sub_total.' + ( field_'.$sub_total.' * field_'.$tax.' )';
						$calc_eq = true;
					}
				}
			}
		}
	}

	// Loop through our fields once more to add them to our calculation field with the method of 'eq'.
	if ( $calc_eq ) {
		foreach ( $calc_fields as $calc_id => $calc ) {
			if( $calc['method'] == 'eq' ) {
				foreach ( $fields as $field ) {
					if (preg_match("/\bfield_".$field['id']."\b/i", $calc['eq'] ) ) {
						$calc_fields[$calc_id]['fields'][] = $field['id'];
					}
				}
			}
		}
	}

	if( $datepicker == 1 ){
		wp_enqueue_script( 'jquery-ui-datepicker' );
	}

	if( $qtip == 1 ){
		wp_enqueue_script( 'jquery-qtip',
			NINJA_FORMS_URL .'/js/min/jquery.qtip.min.js',
			array( 'jquery', 'jquery-ui-position' ) );
	}

	if( $mask == 1 ){
		wp_enqueue_script( 'jquery-maskedinput',
			NINJA_FORMS_URL .'/js/min/jquery.maskedinput.min.js',
			array( 'jquery' ) );
	}

	if( $currency == 1 ){
		wp_enqueue_script('jquery-autonumeric',
			NINJA_FORMS_URL .'/js/min/autoNumeric.min.js',
			array( 'jquery' ) );
	}

	if( $rating == 1 ){
		wp_enqueue_script('jquery-rating',
			NINJA_FORMS_URL .'/js/min/jquery.rating.min.js',
			array( 'jquery' ) );
	}

	$form_row = ninja_forms_get_form_by_id($form_id);
	$form_row = apply_filters( 'ninja_forms_display_form_form_data', $form_row );
	if( isset( $form_row['data']['ajax'] ) ){
		$ajax = $form_row['data']['ajax'];
	}else{
		$ajax = 0;
	}

	if( isset( $form_row['data']['hide_complete'] ) ){
		$hide_complete = $form_row['data']['hide_complete'];
	}else{
		$hide_complete = 0;
	}

	if( isset( $form_row['data']['clear_complete'] ) ){
		$clear_complete = $form_row['data']['clear_complete'];
	}else{
		$clear_complete = 0;
	}

	$ninja_forms_js_form_settings['ajax'] = $ajax;
	$ninja_forms_js_form_settings['hide_complete'] = $hide_complete;
	$ninja_forms_js_form_settings['clear_complete'] = $clear_complete;
	
	$calc_settings['calc_value'] = '';
	$calc_settings['calc_fields'] = '';

	if ( !empty ( $calc_value ) ) {
		$calc_settings['calc_value'] = $calc_value;
	}

	$calc_settings['calc_fields'] = $calc_fields;

	$plugin_settings = get_option("ninja_forms_settings");
	if(isset($plugin_settings['date_format'])){
		$date_format = $plugin_settings['date_format'];
	}else{
		$date_format = 'm/d/Y';
	}

	$date_format = ninja_forms_date_to_datepicker($date_format);
	$currency_symbol = $plugin_settings['currency_symbol'];

	$password_mismatch = esc_html(stripslashes($plugin_settings['password_mismatch']));
	$msg_format = $plugin_settings['msg_format'];
	$msg_format = 'inline';
	wp_enqueue_script( 'ninja-forms-display',
		NINJA_FORMS_URL .'/js/min/ninja-forms-display.min.js',
		array( 'jquery', 'jquery-form' ) );

	if( !isset( $ninja_forms_display_localize_js ) OR !$ninja_forms_display_localize_js ){
		wp_localize_script( 'ninja-forms-display', 'ninja_forms_settings', array('ajax_msg_format' => $msg_format, 'password_mismatch' => $password_mismatch, 'plugin_url' => NINJA_FORMS_URL, 'date_format' => $date_format, 'currency_symbol' => $currency_symbol ) );
		$ninja_forms_display_localize_js = true;
	}

	wp_localize_script( 'ninja-forms-display', 'ninja_forms_form_'.$form_id.'_settings', $ninja_forms_js_form_settings );
	wp_localize_script( 'ninja-forms-display', 'ninja_forms_form_'.$form_id.'_calc_settings', $calc_settings );

	wp_localize_script( 'ninja-forms-display', 'ninja_forms_password_strength', array(
		'empty' => __( 'Strength indicator', 'ninja-forms' ),
		'short' => __( 'Very weak', 'ninja-forms' ),
		'bad' => __( 'Weak', 'ninja-forms' ),
		/* translators: password strength */
		'good' => _x( 'Medium', 'password strength', 'ninja-forms' ),
		'strong' => __( 'Strong', 'ninja-forms' ),
		'mismatch' => __( 'Mismatch', 'ninja-forms' )
		) );

}
add_action( 'ninja_forms_display_js', 'ninja_forms_display_js', 10, 2 );

function ninja_forms_display_css(){
	wp_enqueue_style( 'ninja-forms-display', NINJA_FORMS_URL .'/css/ninja-forms-display.css' );
	wp_enqueue_style( 'jquery-qtip', NINJA_FORMS_URL .'/css/qtip.css' );
	wp_enqueue_style( 'jquery-rating', NINJA_FORMS_URL .'/css/jquery.rating.css' );
}
add_action( 'ninja_forms_display_css', 'ninja_forms_display_css', 10, 2 );