<?php

class NinjaFormsWrap
{

    /**
     * 	Find a submission of a form with where clause:
     * 	array(
     * 		'relation' => 'OR', // Optional, defaults to "AND"
     * 		array(
     * 			'key'     => '_seq_num',
     * 			'value'   => array(363, 350),
     * 			'compare' => 'IN'
     * 		),
     * 		array( //nested or
     * 			'relation' => 'OR',
     * 			array(
     * 				'key'     => '_field_' . $keys['email'],
     * 				'value'   => 'vivmoustaka@gmail.com',
     * 				'compare' => '='
     * 			),
     * 			array(
     * 				'key'     => '_field_' . $keys['name'],
     * 				'value'   => 'Κωνσ',
     * 				'compare' => 'LIKE'
     * 			)
     * 		)
     * 	)
     *
     */
    public static function getSubmissionsBy($form_id, $where = array(), $post_ids = array())
    {
        $keys = self::getFormFields($form_id);

        $args = array(
            'post_type' => 'nf_sub',
            'posts_per_page' => -1,
            'meta_query' => $where,
        );

        if (!empty($post_ids)) {
            $args['post__in'] = $post_ids;
        }

        $posts = get_posts($args);

        $return = array();
        foreach ($posts as $post) {
            $s = new NF_Database_Models_Submission($post->ID, $form_id);
            $fields = $s->get_field_values();
            $fields['post_id'] = $post->ID;

            foreach ($keys as $key => $keyIndex) {
                if (isset($fields['_field_' . $keyIndex])) {
                    $fields[$key] = $fields['_field_' . $keyIndex];
                }
            }

            $return[$post->ID] = $fields;
        }

        return $return;
    }

    /**
     *
     * 	Get all fields of a form
     *
     */
    public static function getFormFields($form_id)
    {
        $fields = Ninja_Forms()->form($form_id)->get_fields();

        $formFields = array();

        foreach ($fields as $field_id => $field) {
            $formFields[$field->get_setting('key')] = $field_id;
        }

        return $formFields;
    }

    /**
     *
     * 	Update a form value based on post_id
     *
     */
    public static function updateSubmissionField($post_id, $form_id, $field_key, $field_value)
    {
        global $wpdb;

        $prefix = $wpdb->prefix;

        $keys = self::getFormFields($form_id);
        $field_id = $keys[$field_key];

        $sql = "
		UPDATE
			{$prefix}postmeta
		SET
			meta_value = '{$field_value}'
		WHERE
			post_id = {$post_id}
		AND
			meta_key = '_field_{$field_id}'
		";

        $q = $wpdb->query($wpdb->prepare($sql, array()));

        //var_dump($q);
        if ($wpdb->last_error !== '') :
            $wpdb->print_error();
            return false;
        endif;

        return true;
    }

    /**
     *
     * 	Change default values of a form
     *
     */
    public static function changeDefaultValues($form_id)
    {
        global $whiteFields;
        $whiteFields = self::getFormFields($form_id);

        add_filter('ninja_forms_render_default_value', 'my_change_nf_default_value', 10, 3);

        function my_change_nf_default_value($default_value, $field_type, $field_settings)
        {
            global $whiteFields;

            $field_id = $field_settings['id'];

            //Not in the interested form
            if (!in_array($field_id, $whiteFields)) {
                return $default_value;
            }

            $key = $field_settings['key'];

            // or you can check better id
            if ($key == 'name') {
                $default_value = 'default name populated from script';
            }

            return $default_value;
        }

    }

    /**
     *
     * 	Populate a form select field with custom values
     *
     */
    public static function populateSelect()
    {
        add_filter('ninja_forms_render_options', 'cpt_prepopulate_forms', 10, 2);

        function cpt_prepopulate_forms($options, $settings)
        {
            global $post;

            if ($settings['key'] == 'counter') {
                //example
                for ($i = 0; $i <= 10; $i++) {
                    $options[] = array(
                        'label' => $i,
                        'value' => $i,
                        'calc' => null,
                        'selected' => 0
                    );
                }
            }
            return $options;
        }

    }

}


/// Register form strings
add_action('init', function()
{
    	if (!function_exists('pll_register_string')){
		return;
	}

	$forms = array(2);

	foreach ($forms as $form_id) {
		$form_id = 2;
		$GROUP_NAME = 'FORM_ID_' . $form_id;
		$fields = Ninja_Forms()->form($form_id)->get_fields();

		/*
		echo '<pre>';
		var_dump($fields);
		echo '</pre>';
		*/
		
		foreach ($fields as $field) {

			$settings = $field->get_settings();
			//echo '<pre>';var_dump($settings);echo '</pre>'; //debug

			// Skip the hr
			if ($settings['type'] == 'hr'){
				continue;
			}

			/// Always add the label
			pll_register_string($settings['label'], $settings['label'], $GROUP_NAME, true);

			if (array_key_exists("default", $settings)) {
				pll_register_string($settings['default'], $settings['default'], $GROUP_NAME, true);
	    		}

			if ($settings['type'] == 'submit'){
				pll_register_string($settings['processing_label'], $settings['processing_label'], $GROUP_NAME, true);
			}

			if (!empty($settings['options'])) {
	        	foreach($settings['options'] as $o){
	        		pll_register_string($o['label'], $o['label'], $GROUP_NAME, true);
	        	}
	        }
	}
}

},-1);

/// Fetch the translated fields when showing the form
function filter_ninja_forms_localize_fields( $field ) { 
	if (!function_exists('pll__') || !is_array($field['settings']) ){
		return $field;
	}

	
    	$field['settings']['label'] = pll__($field['settings']['label']);


    	if ($field['settings']['type'] === 'html' && array_key_exists("default", $field['settings'])) {
        	$field['settings']['default'] = pll__($field['settings']['default']);
    	}

    	if ($settings['type'] == 'submit'){
        	$field['settings']['processing_label'] = pll__($field['settings']['processing_label']);
	}

    	if (!empty($field['settings']['options'])) {
        	foreach($field['settings']['options'] as $i => $o){
        		$field['settings']['options'][$i]['label'] = pll__($o['label']);
        	}
    	}

    	if ($field['settings']['label'] == 'Lang'){
        	$field['settings']['default'] = pll_current_language('slug');
	}  

    	return $field; 
}
add_filter( 'ninja_forms_localize_fields', 'filter_ninja_forms_localize_fields', 10, 1 );
