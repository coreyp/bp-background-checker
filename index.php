<?php
/*
  Plugin Name: User Background Checker
  Plugin URI:
  Description: Checks users against the criminal records search API provided by IMSAS LLC.
  Author: Rakhitha Nimesh for New Old Media
  Version: 1.0
  Author URI: http://www.innovativephp.com/
 */

class User_Background_Checker {

    public $user_dob;

    public function __construct() {
        add_action('manage_users_custom_column', array($this, 'add_custom_user_columns'), 15, 3);

        add_filter('manage_users_columns', array($this, 'add_user_columns'), 15, 1);
        
        add_action('admin_menu', array($this, 'create_menu_pages'));

        add_action('admin_enqueue_scripts', array($this, 'include_Script_files'));

        add_filter('xprofile_get_field_data', array($this, 'bxcft_get_field_value'), 10, 2);

        $this->user_dob = '';
    }

    public function bxcft_get_field_value($value, $field_id) {
        $field = new BP_XProfile_Field($field_id);

        if ($field->type == 'birthdate' && $field->name == 'Age') {

            $this->user_dob = date("Y-m-d", strtotime($value));
        }
        return $value;
    }

    public function include_Script_files() {
        wp_register_style('ubc_styles', plugin_dir_url(__FILE__) . 'ubc_styles.css');
        wp_enqueue_style('ubc_styles');
    }

    public function create_menu_pages() {
        add_menu_page('User Checker', 'User Checker', 'manage_options', 'ub_checker_page', array($this, 'display_user_checker_page'));
    }

    public function display_user_checker_page() {

        $user_id = isset($_GET['ubc_user']) ? $_GET['ubc_user'] : 0;

        if (isset($_GET['ubc_action']) && 'get_check_results' == $_GET['ubc_action']) {
            $result = $this->create_user_background($user_id);

            if ($result['status']) {
                $submit_url = remove_query_arg('ubc_action');
                $submit_url = add_query_arg('ubc_action', 'display_check_results', $submit_url);

                $this->js_redirect($submit_url);
                exit;
            } else {
                echo '<p class="error">' . $result['msg'] . '</p>';

                $submit_url = remove_query_arg('ubc_action');
                $submit_url = add_query_arg('ubc_action', 'get_check_results', $submit_url);


                echo $this->generate_run_check_form($submit_url);
            }
        } else if (isset($_GET['ubc_action']) && 'view_check' == $_GET['ubc_action']) {

            $submit_url = remove_query_arg('ubc_action');
            $submit_url = add_query_arg('ubc_action', 'get_check_results', $submit_url);

            echo $this->generate_run_check_form($submit_url);

            $user_data = xprofile_get_field_data('Background Check', $user_id);
            $this->display_user_info($user_data, $user_id);

        } else if (isset($_GET['ubc_action']) && 'run_check' == $_GET['ubc_action']) {

            $submit_url = remove_query_arg('ubc_action');
            $submit_url = add_query_arg('ubc_action', 'get_check_results', $submit_url);

            $html = '<p>';
            $html .= __('Do you want to run check on this user?', 'ub_checker');
            $html .= '<form action="' . $submit_url . '" method="POST" >
					
					<input type="submit" value="Run Check" />
				  </form></p>';
            echo $html;

        } else if (isset($_GET['ubc_action']) && 'display_check_results' == $_GET['ubc_action']) {

            $user_data = xprofile_get_field_data('Background Check', $user_id);
            $this->display_user_info($user_data, $user_id);

        } else {
            $url = admin_url('users.php');
            $this->js_redirect($url);
            exit;
        }
    }

    function display_user_info($user_data, $user_id) {
        $data = unserialize(base64_decode($user_data));

        $user = get_user_by('id', $user_id);
        $username = '';

        if ($user) {
            $username = $user->data->user_login;
        }

        $last_checked = array_pop($data['checked_date']);

        $html = '<div class="ubc_user_info"><h2>Username : ' . $username . '</h2>';
        $html .= '<p>Last Checked Date : ' . $last_checked . '<p>';
        $url = admin_url('users.php');
        $html .= '<p><a href="' . $url . '"><input type="button" value="Back" /></a><p></div>';

        if (isset($data['Message']) && isset($data['Inputs'])) {
            $html .= '<table class="ubc_main_table">';
            $html .= '<tr><td class="tr_header tr_left">Message</td><td  class="tr_data">' . $data['Message'] . '</td></tr>';
            foreach ($data['Inputs'] as $k => $v) {
                $html .= '<tr>';
                $res = isset($offense[$v]) ? $offense[$v] : '';
                $html .= '<td class="tr_header tr_left">' . $k . '</td><td  class="tr_data">' . $v . '</td>';
                $html .= '</tr>';
            }


            $html .= '</table>';
        }
        if (isset($data['CriminalInformation']['Records'])) {

            foreach ($data['CriminalInformation']['Records']['Record'] as $key => $record) {
                $html .= '<table class="ubc_main_table">';

                foreach ($record as $keys => $value) {
                    if ($keys == 'Offenses') {
                        if (is_array($value['Offense']) && isset($value['Offense'][0])) {

                            foreach ($value['Offense'] as $offense) {

                                $tb_keys = array_keys($offense);
                                $html .= '<tr><td colspan="2" class="tr_left tr_head_highlight">Offense</td></tr>';
                                $html .= '<tr><td colspan="2"><table class="ubc_sub_table">
										<tr>';

                                foreach ($tb_keys as $v) {
                                    $html .= '<td class="tr_header">' . $v . '</td>';
                                }

                                $html .= '</tr>';

                                $html .= '<tr>';
                                foreach ($tb_keys as $v) {
                                    $res = isset($offense[$v]) ? $offense[$v] : '';
                                    $html .= '<td  class="tr_data">' . $res . '</td>';
                                }
                                $html .= '</tr>';
                                $html .= '</table></td></tr>';
                            }

                        } else if (is_array($value['Offense'])) {
                            $tb_keys = array_keys($value['Offense']);

                            $html .= '<tr><td colspan="2" class="tr_left tr_head_highlight">Offense</td></tr>';
                            $html .= '<tr><td colspan="2"><table class="ubc_sub_table">
										<tr>';

                            foreach ($tb_keys as $v) {
                                $html .= '<td class="tr_header">' . $v . '</td>';
                            }

                            $html .= '</tr>';

                            $html .= '<tr>';
                            foreach ($value['Offense'] as $offense) {
                                $html .= '<td  class="tr_data">' . $offense . '</td>';
                            }
                            $html .= '</tr>';
                            $html .= '</table></td></tr>';
                        }
                    } else if ($keys == 'Addresses') {

                        $html .= '<tr><td colspan="2" class="tr_left tr_head_highlight">Addresses</td></tr>';
                        foreach ($value['Address'] as $k => $v) {
                            $html .= '<tr><td class="tr_left tr_data_highlight">' . $k . '</td><td class="tr_right">' . $v . '</td></tr>';
                        }

                    } else if ($keys == 'Aliases') {

                        $html .= '<tr><td colspan="2" class="tr_left tr_head_highlight">Aliases</td></tr>';
                        foreach ($value['Alias'] as $k => $v) {
                            $html .= '<tr><td class="tr_left tr_data_highlight">Alias</td><td class="tr_right">' . $v['FullName'] . '</td></tr>';
                        }

                    } else {
                        if ($keys == 'Photo') {
                            
                        } else {
                            $html .= '<tr><td class="tr_left">' . $keys . ' </td><td class="tr_right">' . $value . '</td></tr>';
                        }
                    }
                }
                $html .= '</table>';
            }

            echo $html;
        }
    }

    function generate_run_check_form($submit_url) {
        $html = '<p>';
        $html .= __('Do you want to run a criminal background check on this user?', 'ub_checker');
        $html .= '<form action="' . $submit_url . '" method="POST" >
				<input type="submit" value="Run Check" />
			  </form></p>';
        return $html;
    }

    function add_user_columns($defaults) {
        $defaults['background_check'] = __('Background Check', 'ub_checker');
        return $defaults;
    }

    function add_custom_user_columns($value, $column_name, $id) {
        if ($column_name == 'background_check') {

            $user_data = (xprofile_get_field_data('Background Check', $id));

            $user_data = unserialize(base64_decode($user_data));


            $menu_page_url = menu_page_url('ub_checker_page', false);
            if ('' != $user_data) {

                $check_url = add_query_arg('ubc_action', 'view_check', $menu_page_url);
                $check_url = add_query_arg('ubc_user', $id, $check_url);
                return '<p><a href="' . $check_url . '" >View Check</a></p><p>Date : ' . array_pop($user_data['checked_date']) . '</p>';
            } else {

                $check_url = add_query_arg('ubc_action', 'run_check', $menu_page_url);
                $check_url = add_query_arg('ubc_user', $id, $check_url);
                return '<p><a href="' . $check_url . '" >Run Check</a></p>';
            }
        }
    }

    function request_criminal_api_data($data) {
        $url = 'https://www.imsasllc.com/api/v2/data/';
        $data_string = json_encode($data);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string))
        );
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    function create_user_background($user_id) {

        $fname = xprofile_get_field_data('First Name', $user_id);
        $lname = xprofile_get_field_data('Last Name', $user_id);

        xprofile_get_field_data('Age', $user_id);
        $dob = $this->user_dob;

        $output = array();

        if ($fname == '' || $lname == '' || $dob == '') {
            $output['status'] = false;
            $output['msg'] = __('Please complete your profile with First Name, Last Name and DOB', 'ub_checker');
            return $output;
        }

        $data = array();
        $data['credentials'] = array(
            'account_id' => '127706',
            'api_key' => '12ZZPjeiW41391940979'
        );
        $data['product'] = 'criminal_database';

        $data['data'] = array(
            'FirstName' => $fname,
            'LastName' => $lname,
            'DOB' => $dob
        );

        $result = $this->request_criminal_api_data($data);

        $result_check = json_decode($result);
        if ($result_check->Results) {


            $user_data_arr = objectToArray($result_check->Results);


            $user_data = xprofile_get_field_data('Background Check', $user_id);
            $user_data = unserialize(base64_decode($user_data));

            if (isset($user_data['checked_date'])) {
                $user_data_arr['checked_date'] = $user_data['checked_date'];
                array_push($user_data_arr['checked_date'], date("Y-m-d H:i:s"));
            } else {
                $user_data_arr['checked_date'] = array();
                array_push($user_data_arr['checked_date'], date("Y-m-d H:i:s"));
            }

            $user_data = base64_encode(serialize($user_data_arr));

            xprofile_set_field_data('Background Check', $user_id, $user_data);

            $output['status'] = true;
            $output['msg'] = __('User check completed succesfully.', 'ub_checker');
        } else if ($result_check->error) {
            $output['status'] = false;
            $output['msg'] = $result->error;
        }

        return $output;
    }

    function js_redirect($url) {
        echo '<script > window.location.href = "' . $url . '"; </script>';
    }

}

$user_background_checker = new User_Background_Checker();

function objectToArray($d) {
    if (is_object($d)) {
        // Gets the properties of the given object
        // with get_object_vars function
        $d = get_object_vars($d);
    }

    if (is_array($d)) {
        /*
         * Return array converted to object
         * Using __FUNCTION__ (Magic constant)
         * for recursive call
         */
        return array_map(__FUNCTION__, $d);
    } else {
        // Return array
        return $d;
    }
}
