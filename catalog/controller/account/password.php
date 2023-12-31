<?php
class ControllerAccountPassword extends Controller {
	private $error = array();

	public function index() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/password', '', true);

			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('account/password');

		$this->document->setTitle($this->language->get('heading_title'));

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->load->model('account/customer');

			$this->model_account_customer->editPassword($this->customer->getEmail(), $this->request->post['password']);

			$this->session->data['success'] = $this->language->get('text_success');

			// Add to activity log
			if ($this->config->get('config_customer_activity')) {
				$this->load->model('account/activity');

				$activity_data = array(
					'customer_id' => $this->customer->getId(),
					'name'        => $this->customer->getFirstName() . ' ' . $this->customer->getLastName()
				);

				$this->model_account_activity->addActivity('password', $activity_data);
			}

			$this->response->redirect($this->url->link('account/account', '', true));
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('account/password', '', true)
		);

		$data['heading_title'] = $this->language->get('heading_title');

		$data['text_password'] = $this->language->get('text_password');

		$data['entry_password'] = $this->language->get('entry_password');
		$data['entry_confirm'] = $this->language->get('entry_confirm');

		$data['button_continue'] = $this->language->get('button_continue');
		$data['button_back'] = $this->language->get('button_back');

		if (isset($this->error['password'])) {
			$data['error_password'] = $this->error['password'];
		} else {
			$data['error_password'] = '';
		}

		if (isset($this->error['confirm'])) {
			$data['error_confirm'] = $this->error['confirm'];
		} else {
			$data['error_confirm'] = '';
		}

		$data['action'] = $this->url->link('account/password', '', true);

		if (isset($this->request->post['password'])) {
			$data['password'] = $this->request->post['password'];
		} else {
			$data['password'] = '';
		}

		if (isset($this->request->post['confirm'])) {
			$data['confirm'] = $this->request->post['confirm'];
		} else {
			$data['confirm'] = '';
		}

		$data['back'] = $this->url->link('account/account', '', true);

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
		$data['menu_left'] = $this->load->controller('extension/module/account');

		$this->response->setOutput($this->load->view('account/password', $data));
	}

	protected function validate() {
		if ((utf8_strlen($this->request->post['password']) < 4) || (utf8_strlen($this->request->post['password']) > 20)) {
			$this->error['password'] = $this->language->get('error_password');
		}

		if ($this->request->post['confirm'] != $this->request->post['password']) {
			$this->error['confirm'] = $this->language->get('error_confirm');
		}
		
		if(ADVANCE_PASSWORD){
			$password_check = $this->validatePassword();
			if(!$password_check['result']) {
				$this->error['password'] = implode('<br/>', $password_check['error']);
				unset($this->error['error']['confirm']);
			}
		}

		return !$this->error;

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