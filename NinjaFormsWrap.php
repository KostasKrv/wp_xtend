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
