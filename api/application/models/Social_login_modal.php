<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Social_login_modal extends CI_Model
{

    function __construct()
    {
        parent::__construct();
        /*cache control*/
        $this->output->set_header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        $this->output->set_header('Pragma: no-cache');
    }


    public function fb_validate_login($access_token = "", $fb_user_id = "")
    {
        require_once APPPATH . '/libraries/facebook-sdk-5.x/autoload.php'; // change path as needed

        $fb = new \Facebook\Facebook([
            'app_id' => get_settings('fb_app_id'),
            'app_secret' => get_settings('fb_app_secret'),
            'default_graph_version' => 'v2.10',
        ]);

        try {
            // Get the \Facebook\GraphNodes\GraphUser object for the current user.
            $response = $fb->get('/me?fields=id,first_name,last_name,email,link,picture', $access_token);
        } catch (\Facebook\Exceptions\FacebookResponseException $e) {
            // When Graph returns an error
            //   echo 'Graph returned an error: ' . $e->getMessage();
            //   exit;
        } catch (\Facebook\Exceptions\FacebookSDKException $e) {
            // When validation fails or other local issues
            //   echo 'Facebook SDK returned an error: ' . $e->getMessage();
            //   exit;
        }

        $user = $response->getGraphUser();
        // print_r($user);
        // die();
        if (isset($user['email'])) {
            if (filter_var($user['email'], FILTER_VALIDATE_EMAIL)) {
                $email = $user['email'];
                $fb_user_id = $user['id'];
                $first_name = $user['first_name'];
                $last_name = $user['last_name'];

                $credential = array('email' => $email, 'status' => 1);
                $query = $this->db->get_where('users', $credential);
                if ($query->num_rows() > 0) {
                    $row = $query->row();

                    // For device login tracker
                    $this->user_model->new_device_login_tracker($row->id);
                    $this->user_model->set_login_userdata($row->id);
                } else {
                    $data['first_name'] = $first_name;
                    $data['last_name'] = $last_name;
                    $data['email'] = $email;
                    $data['password'] = password_hash(bin2hex(random_bytes(30)), PASSWORD_BCRYPT, ['cost' => 12]);
                    $data['status'] = 1;


                    $data['wishlist'] = json_encode(array());
                    $data['date_added'] = strtotime(date("Y-m-d H:i:s"));
                    $social_links = array(
                        'facebook' => "",
                        'twitter' => "",
                        'linkedin' => ""
                    );
                    $data['social_links'] = json_encode($social_links);
                    $data['role_id'] = 2;
                    $data['payment_keys'] = json_encode(array());

                    $validity = $this->user_model->check_duplication('on_create', $data['email']);
                    if ($validity == true) {
                        $this->db->insert('users', $data);

                        $insert_id = $this->db->insert_id();
                        //$this->user_model->update_unique_identifier($insert_id);

                        //login
                        $credential = array('email' => $email, 'status' => 1);
                        $query = $this->db->get_where('users', $credential);
                        $row = $query->row();

                        // For device login tracker
                        $this->user_model->new_device_login_tracker($row->id);
                        $this->user_model->set_login_userdata($row->id);
                    } else {
                        $this->session->set_flashdata('error_message', get_phrase('email_duplication'));
                        redirect(site_url('login'), 'refresh');
                    }
                }

                $this->session->set_flashdata('flash_message', get_phrase('welcome') . ' ' . $row->first_name . ' ' . $row->last_name);

                if ($this->session->userdata('url_history')) {
                    redirect($this->session->userdata('url_history'), 'refresh');
                }
                redirect(site_url(), 'refresh');
            } else {
                $this->session->set_flashdata('error_message', get_phrase('invalid_email_address'));
                redirect(site_url('login'), 'refresh');
            }
        } else {
            $this->delete_app($fb_user_id, $access_token);
            $this->session->set_flashdata('error_message', get_phrase('email_access_permission_is_required'));
            redirect(site_url('login'), 'refresh');
        }

    }


    function delete_app($fb_user_id = "", $access_token = "")
    {
        if ($fb_user_id != "" && $access_token != "") {
            require_once APPPATH . '/libraries/facebook-sdk-5.x/autoload.php'; // change path as needed

            $fb = new \Facebook\Facebook([
                'app_id' => get_settings('fb_app_id'),
                'app_secret' => get_settings('fb_app_secret'),
                'default_graph_version' => 'v2.10',
            ]);
            $fbApp = new Facebook\FacebookApp(get_settings('fb_app_id'), get_settings('fb_app_secret'));

            $request = new Facebook\FacebookRequest($fbApp, $access_token, 'DELETE', $fb_user_id . "/permissions");
            try {
                $response = $fb->getClient()->sendRequest($request);
            } catch (Facebook\Exceptions\FacebookResponseException $ex) {
                // When Graph returns an error
                // echo("Error - graph returned an error: " . $ex->getMessage() );
                // exit();
            } catch (Facebook\Exceptions\FacebookSDKException $ex) {
                // When validation fails or other local issues
                // echo("Error - Facebook SDK returned an error: " . $ex->getMessage() );
                // exit();
            }
        }
    }

    public function google_validate_login($id_token = "")
    {
        if (empty($id_token)) {
            $this->session->set_flashdata('error_message', get_phrase('invalid_google_token'));
            redirect(site_url('login'), 'refresh');
            return;
        }

        try {
            $client = new Google_Client(['client_id' => get_settings('google_client_id')]);

            $payload = $client->verifyIdToken($id_token);

            if (!$payload) {
                throw new Exception('Invalid ID token');
            }

            $google_user_id = $payload['sub'];
            $email = $payload['email'];
            $first_name = isset($payload['given_name']) ? $payload['given_name'] : '';
            $last_name = isset($payload['family_name']) ? $payload['family_name'] : '';
            $email_verified = $payload['email_verified'];

            if (!$email_verified) {
                $this->session->set_flashdata('error_message', get_phrase('please_verify_your_google_email_first'));
                redirect(site_url('login'), 'refresh');
                return;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->session->set_flashdata('error_message', get_phrase('invalid_email_address'));
                redirect(site_url('login'), 'refresh');
                return;
            }

            $credential = array('email' => $email, 'status' => 1);
            $query = $this->db->get_where('users', $credential);

            if ($query->num_rows() > 0) {
                $row = $query->row();

                $this->user_model->new_device_login_tracker($row->id);
                $this->user_model->set_login_userdata($row->id);
            } else {
                $data['first_name'] = $first_name;
                $data['last_name'] = $last_name;
                $data['email'] = $email;
                $data['password'] = password_hash(bin2hex(random_bytes(30)), PASSWORD_BCRYPT, ['cost' => 12]);
                $data['status'] = 1;
                $data['wishlist'] = json_encode(array());
                $data['date_added'] = strtotime(date("Y-m-d H:i:s"));

                $social_links = array(
                    'facebook' => "",
                    'twitter' => "",
                    'linkedin' => ""
                );
                $data['social_links'] = json_encode($social_links);
                $data['role_id'] = 2;
                $data['payment_keys'] = json_encode(array());

                $validity = $this->user_model->check_duplication('on_create', $data['email']);

                if ($validity == true) {
                    $this->db->insert('users', $data);
                    $insert_id = $this->db->insert_id();

                    $credential = array('email' => $email, 'status' => 1);
                    $query = $this->db->get_where('users', $credential);
                    $row = $query->row();
                    $this->user_model->new_device_login_tracker($row->id);
                    $this->user_model->set_login_userdata($row->id);
                } else {
                    $this->session->set_flashdata('error_message', get_phrase('email_duplication'));
                    redirect(site_url('login'), 'refresh');
                    return;
                }
            }

            $this->session->set_flashdata('flash_message', get_phrase('welcome') . ' ' . $row->first_name . ' ' . $row->last_name);

            if ($this->session->userdata('url_history')) {
                redirect($this->session->userdata('url_history'), 'refresh');
            }
            redirect(site_url(), 'refresh');

        } catch (Exception $e) {
            log_message('error', 'Google Login Error: ' . $e->getMessage());

            $this->session->set_flashdata('error_message', get_phrase('google_login_failed') . ': ' . $e->getMessage());
            redirect(site_url('login'), 'refresh');
        }
    }










}