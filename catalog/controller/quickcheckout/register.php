<?php
class ControllerQuickCheckoutRegister extends Controller {
	private $error;

  	public function index() {
		$data = $this->load->language('checkout/checkout');
		$data = array_merge($data, $this->load->language('quickcheckout/checkout'));

		$data['entry_newsletter'] = sprintf($this->language->get('entry_newsletter'), $this->config->get('config_name'));

		if ($this->config->get('config_account_id')) {
			$this->load->model('catalog/information');

			$information_info = $this->model_catalog_information->getInformation($this->config->get('config_account_id'));

			if ($information_info) {
				$data['text_agree'] = sprintf($this->language->get('text_agree'), $this->url->link('information/information/agree', 'information_id=' . $this->config->get('config_account_id'), true), $information_info['title'], $information_info['title']);
			} else {
				$data['text_agree'] = '';
			}
		} else {
			$data['text_agree'] = '';
		}

		// All variables
		$data['field_newsletter'] = $this->config->get('quickcheckout_field_newsletter');


		$data['event_day'] = false;
		$event_day_status = $this->config->get('setting_birthdaycoupon_status');
		if(isset($event_day_status) && $event_day_status == 1){
			$data['event_day'] = true;
		}

		return $this->load->view('quickcheckout/register', $data);
  	}

	public function validate() {
		$this->load->language('checkout/checkout');
		$this->load->language('quickcheckout/checkout');

		$this->load->model('account/customer');

		$json = array();

		// Validate if customer is already logged out.
		if ($this->customer->isLogged()) {
			$json['redirect'] = $this->url->link('quickcheckout/checkout', '', true);
		}

		$this->validateSubsribe();

		if (!$json) {
			// Customer Group
			if (isset($this->request->post['customer_group_id']) && is_array($this->config->get('config_customer_group_display')) && in_array($this->request->post['customer_group_id'], $this->config->get('config_customer_group_display'))) {
				$customer_group_id = $this->request->post['customer_group_id'];
			} else {
				$customer_group_id = $this->config->get('config_customer_group_id');
			}
			
			if ($this->model_account_customer->getTotalCustomersByEmail($this->request->post['email'])) {
				$json['error']['warning'] = $this->language->get('error_exists');
			}

			if ((utf8_strlen($this->request->post['password']) < 4) || (utf8_strlen($this->request->post['password']) > 20)) {
				$json['error']['password'] = $this->language->get('error_password');
			}

			if ($this->request->post['confirm'] != $this->request->post['password']) {
				$json['error']['confirm'] = $this->language->get('error_confirm');
			}
			
			if(ADVANCE_PASSWORD){
				$password_check = $this->validatePassword();
				if(!$password_check['result']) {
					$this->error['password'] = implode('<br/>', $password_check['error']);
					unset($json['error']['confirm']);
				}
			}

			if ($this->config->get('config_account_id')) {
				$this->load->model('catalog/information');

				$information_info = $this->model_catalog_information->getInformation($this->config->get('config_account_id'));

				if ($information_info && !isset($this->request->post['agree'])) {
					$json['error']['warning'] = sprintf($this->language->get('error_agree'), $information_info['title']);
				}
			}

			//Validate birthday
			$event_day_status = $this->config->get('setting_birthdaycoupon_status');
			$language_id = $this->config->get('config_language_id');
			$event_day_age_validate = $this->config->get('setting_birthdaycoupon_age_validate_'.$language_id);
			if(isset($event_day_status) && $event_day_status == 1){
				if ((utf8_strlen(trim($this->request->post['birthday'])) < 1) || $this->request->post['birthday'] == '0000-00-00'){
					$json['error']['birthday'] = $this->language->get('error_birthday');
				} else {
					//validate age
					if($event_day_age_validate == 1 && time() < strtotime('+18 years', strtotime($this->request->post['birthday']))){
						$json['error']['birthday'] = $this->language->get('error_birthday_age');
					}
				}
			}
		}

		if (!$json) {
			$customer_id = $this->model_account_customer->addCustomer($this->request->post);
			
			// Clear any previous login attempts for unregistered accounts.
			$this->model_account_customer->deleteLoginAttempts($this->request->post['email']);

			$this->session->data['account'] = 'register';

			$this->load->model('account/customer_group');

			$customer_group_info = $this->model_account_customer_group->getCustomerGroup($customer_group_id);

			if ($customer_group_info && !$customer_group_info['approval']) {
				$this->customer->login($this->request->post['email'], $this->request->post['password']);

				// Default Payment Address
				$this->load->model('account/address');

				$this->session->data['payment_address'] = $this->model_account_address->getAddress($this->customer->getAddressId());

				if (!empty($this->request->post['shipping_address'])) {
					$this->session->data['shipping_address'] = $this->model_account_address->getAddress($this->customer->getAddressId());
				}
			} else {
				$json['redirect'] = $this->url->link('account/success');
			}

			$json['redirect'] = $this->url->link('quickcheckout/checkout');
			
			unset($this->session->data['guest']);

			// Add to activity log
			if ($this->config->get('config_customer_activity')) {
				$this->load->model('account/activity');

				$activity_data = array(
					'customer_id' => $customer_id,
					'name'        => $this->request->post['firstname'] . ' ' . $this->request->post['lastname']
				);

				$this->model_account_activity->addActivity('register', $activity_data);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	
	public function validateSubsribe() {
		$json = array();
		
		$this->load->language('extension/module/newsletter_module');
		
		if ($this->request->server['REQUEST_METHOD'] == 'POST') {

			if ((utf8_strlen($this->request->post['email']) > 96) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
				$json['error']['email'] = $this->language->get('error_email');
            }
            else {
                $query = $this->db->query("SELECT email FROM " . DB_PREFIX . "customer_newsletter_list WHERE email = '" . $this->db->escape($this->request->post['email']) . "' AND status = '1'");

                if($query->num_rows){
                    $json['error']['email'] = $this->language->get('error_email_exists');
                }
            }
			
			if (!isset($json['error'])) {
                $query = $this->db->query("SELECT email FROM " . DB_PREFIX . "customer_newsletter_list WHERE email = '" . $this->db->escape($this->request->post['email']) . "'");

                if($query->num_rows){
                    // update record
                    $this->db->query("UPDATE " . DB_PREFIX . "customer_newsletter_list SET status = '1' WHERE customer_id = '".$this->customer->isLogged()."'");
                }
                else {
                    // save record to database
                    $this->db->query("INSERT INTO " . DB_PREFIX . "customer_newsletter_list SET customer_id = '".$this->customer->isLogged()."', email = '" . $this->db->escape($this->request->post['email']) . "', date_added = NOW(), status = '1'");
                }

                // mailchimp (newlsetter module)
                $the_mailchimp = new Newsletter_Module($this->config, $this->db, $this->log, $this->session, $this->url, $this->modulehelper);
                $mailchimp = $the_mailchimp->initMailchimp();
                $mailchimp_param = array('email_address' => $this->request->post['email'], 'status' => 'subscribed');
                $chimp = $the_mailchimp->subscribeTheSubscriber($mailchimp, $mailchimp_param); 
                 // mailchimp (newlsetter module)

                // update newsletter in customer
			    $this->db->query("UPDATE " . DB_PREFIX . "customer SET newsletter = '1' WHERE email = '".$this->db->escape($this->request->post['email'])."'");

				$json['success'] = $this->language->get('text_success_newsletter');
			}
		}
		
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
    }

	protected function validatePassword() {
		//Options (Set any to 0 to disable checks).
		$min_length = 8; //Minimum password length.
		$max_length = 20;  //Maximum password length.
		$n_numbers = 1;  //Minimum number of numbers (0-9).
		$n_characters = 1; //Minimum number of special characters (/\$%£! .etc).
		$n_lower = 1; //Minimum number of lowercase letters.
		$n_upper = 1; //Minimum number of uppercase letters.

		$error_message = array();
		$error = false;
		$password = $this->request->post['password'];
		if($min_length) {
			$error_message[] = $min_length .' to '. $max_length .' characters.';
			if(utf8_strlen($password) < $min_length || utf8_strlen($password) > $max_length) {
				$error = true;
			}
		}
		if($n_lower) {
			$error_message[] = $n_lower .' or more lowercase letters.';
			if(preg_match_all( "/[a-z]/", $password ) < $n_lower) {
				$error = true;
			}
		}
		if($n_upper) {
			$error_message[] = $n_upper .' or more uppercase letters.';
			if(preg_match_all( "/[A-Z]/", $password ) < $n_upper) {
				$error = true;
			}
		}
		if($n_numbers) {
			$error_message[] = $n_numbers .' or more numbers.';
			if(preg_match_all( "/[0-9]/", $password ) < $n_numbers) {
				$error = true;
			}
		}
		if($n_characters) {
			$error_message[] = $n_characters .' or more special characters (e.g. !?.,#$&%).';
			if(preg_match_all( "/(?=\D)(?=\W)(?=\S)./", $password ) < $n_characters) {
				$error = true;
			}
		}
		if($error) {
			return array('result'=>false, 'error'=>$error_message);
		} else {
			return array('result'=>true);
		}
	}
}