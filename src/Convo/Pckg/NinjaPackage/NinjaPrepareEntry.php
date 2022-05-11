<?php

namespace Convo\Pckg\NinjaPackage;

use NF_Fields_Textarea;

class NinjaPrepareEntry extends NF_Fields_Textarea
{
    public $_form_data = array();

    protected $_form_id = '';

    /** Register hook */
    public function __construct()
    {
        \add_filter('ninja_forms_submit_data', [$this, 'ninjaFormsSubmitData']);
    }

    public function NinjaPrepareEntry($entry)         //$data for validate
    {
        $nonce_name = 'ninja_forms_display_nonce';
        /**
         * We've got to get the 'nonce_ts' to append to the nonce name to get
         * the unique nonce we created
         * */
        if( isset( $_REQUEST[ 'nonce_ts' ] ) && 0 < strlen( $_REQUEST[ 'nonce_ts' ] ) ) {
            $nonce_name = $nonce_name . "_" . $_REQUEST[ 'nonce_ts' ];
        }
        $check_ajax_referer = check_ajax_referer( $nonce_name, 'security', $die = false );
        if(!$check_ajax_referer){
            /**
             * "Just in Time Nonce".
             * If the nonce fails, then send back a new nonce for the form to resubmit.
             * This supports the edge-case of 11:59:59 form submissions, while avoiding the form load nonce request.
             */

            $current_time_stamp = time();
            $new_nonce_name = 'ninja_forms_display_nonce_' . $current_time_stamp;
            $this->_errors['nonce'] = array(
                'new_nonce' => wp_create_nonce( $new_nonce_name ),
                'nonce_ts' => $current_time_stamp
            );
            $this->_respond();
        }

        $this->form_data_check();

        $this->_form_id = 2;

        /* Render Instance Fix */
        if(strpos($this->_form_id, '_')){
            $this->_form_instance_id = $this->_form_id;
            list($this->_form_id, $this->_instance_id) = explode('_', $this->_form_id);
            $updated_fields = array();
            foreach($this->_form_data['fields'] as $field_id => $field ){
                list($field_id) = explode('_', $field_id);
                list($field['id']) = explode('_', $field['id']);
                $updated_fields[$field_id] = $field;
            }
            $this->_form_data['fields'] = $updated_fields;
        }

        /* END Render Instance Fix */

        $form_fields = Ninja_Forms()->form($this->_form_id)->get_fields();
        foreach ($form_fields as $id => $field) {
            $this->_form_data['fields'][$id]['key'] = $field->get_setting('key');
            $this->_form_data['fields'][$id]['id'] = $field->get_id();
            $this->_form_data['fields'][$id]['value'] = $entry[$field->get_setting('key')] ?? null;
        }

        // Init Field Merge Tags.
        $field_merge_tags = Ninja_Forms()->merge_tags['fields'];
        $field_merge_tags->set_form_id($this->_form_id);

        if (isset($this->_form_cache['settings'])) {
            $form_settings = $this->_form_cache['settings'];
        } else {
            $form_settings = false;
        }

        if (!$form_settings) {
            $form = Ninja_Forms()->form($this->_form_id)->get();
            $form_settings = $form->get_settings();
        }

        // Init Form Merge Tags.
        $form_merge_tags = Ninja_Forms()->merge_tags['form'];
        $form_merge_tags->set_form_id($this->_form_id);
        $form_merge_tags->set_form_title($form_settings['title']);

        $this->_data['form_id'] = $this->_form_data['form_id'] = $this->_form_id;
        $this->_data['settings'] = $form_settings;

        /*
        |--------------------------------------------------------------------------
        | Fields
        |--------------------------------------------------------------------------
        */

        $form_fields = Ninja_Forms()->form($this->_form_id)->get_fields();

        $validate_fields = apply_filters('ninja_forms_validate_fields', true, $this->_data);

        $fields = [];

        foreach ($form_fields as $key => $field) {

            $get_key = $field->get_setting('key');

            if (is_object($field)) {

                //Process Merge tags on Repeater fields values
                if ($field->get_setting('type') === "repeater") {
                    $this->process_repeater_fields_merge_tags($field);
                }

                $field = array(
                    'id' => $field->get_id(),
                    'settings' => $field->get_settings()
                );
            }

            // Duplicate field ID as single variable for more readable array access.
            $field_id = $field['id'];

            // Check that the field ID exists in the submitted for data and has a submitted value.
            if (isset($this->_form_data['fields'][$field_id]) && isset($this->_form_data['fields'][$field_id]['value'])) {
                $field['value'] = $this->_form_data['fields'][$field_id]['value'];
            } else {
                $field['value'] = '';
            }

            // Duplicate field value to settings and top level array item for backwards compatible access (ie Save Action).
            $field['settings']['value'] = $field['value'];

            // Duplicate field value to form cache for passing to the action filter.
            $this->_form_cache['fields'][$key]['settings']['value'] = $this->_form_data['fields'][$field_id]['value'];

            // Duplicate the Field ID for access as a setting.
            $field['settings']['id'] = $field['id'];

            // Combine with submitted data.
            $field = array_merge($field, $this->_form_data['fields'][$field_id]);

            // Flatten the field array.
            $field = array_merge($field, $field['settings']);

            /** Validate the Field */
            if ($validate_fields && !isset($this->_data['resume'])) {
                    $field = apply_filters( 'ninja_forms_pre_validate_field_settings', $field );

                    $field_class = Ninja_Forms()->fields[ $field['type'] ];

                    if( $errors = $field_class->validate( $field, $this->_form_data ) ){
                        $field_id = $field[ 'id' ];
                        $this->_errors[ 'fields' ][ $field_id ] = $errors;
                        $err = implode( ' ; ', $errors );
                        $fields[$get_key] = $err;
//                        $fields['error'] = $errors;
                        $this->_respond();
                    }
            }

            /** Populate Field Merge Tag */
            $field_merge_tags->add_field($field);

            $this->_data['fields'][$field_id] = $field;
            $this->_data['fields_by_key'][$field['key']] = $field;
        }
        return $fields;
    }

    protected function form_data_check()
    {
        if( function_exists( 'json_last_error' ) // Function not supported in php5.2
            && function_exists( 'json_last_error_msg' )// Function not supported in php5.4
            && json_last_error() ){
            $this->_errors[] = json_last_error_msg();
        } else {
            $this->_errors[] = esc_html__( 'An unexpected error occurred.', 'ninja-forms' );
        }

        $this->_respond();
    }

    private function _respond( $data = array() )
    {
        // Restore form instance ID.
        if(property_exists($this, '_form_instance_id')
            && $this->_form_instance_id){
            $this->_data[ 'form_id' ] = $this->_form_instance_id;

            // Maybe update IDs for field errors, if there are field errors.
            if(isset($this->_errors['fields']) && $this->_errors['fields']){
                $field_errors = array();
                foreach($this->_errors['fields'] as $field_id => $error){
                    $field_errors[$field_id . '_' . $this->_instance_id] = $error;
                }
                $this->_errors['fields'] = $field_errors;
            }
        }
    }

    protected function process_repeater_fields_merge_tags( $field ){
        //Compare the Repeater field passed calling the function with the array of fields values from the submission object
        foreach( $this->_form_data['fields'][$field->get_id()]['value'] as $id => $data ){
            //Check if field is a Repeater Field
            if( Ninja_Forms()->fieldsetRepeater->isRepeaterFieldByFieldReference($id) && !empty($data['value']) && is_string($data['value']) ) {
                //Merge tags in the Repeater Field Sub Fields values
                $this->_form_data['fields'][$field->get_id()]['value'][$id]['value'] = apply_filters( 'ninja_forms_merge_tags', $data['value'] );
            }
        }
    }

}
