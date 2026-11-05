<?php
/**
 * Blockonomics Payment Admin Controller
 */
namespace Opencart\Admin\Controller\Extension\Blockonomics\Payment;

use Opencart\Admin\Config;

class Blockonomics extends \Opencart\System\Engine\Controller {

	/** @var BlockonomicsLibrary $blockonomics */
	private $blockonomics;

	/**
	 * Blockonomics Payment Admin Controller Constructor
	 * @param Registry $registry
	 */
	public function __construct($registry) {
		parent::__construct($registry);

		require_once(DIR_EXTENSION . 'blockonomics/system/library/blockonomics.php');

		// echo "<h1>WORKIN</h1>";
		// Make langauge strings and Blockonomics Library available to all
		$this->load->language('extension/blockonomics/payment/blockonomics');
		// echo "<h1>WORKIN</h1>";
		$this->blockonomics = new \Opencart\Extension\Blockonomics\System\Library\Blockonomics($registry);
	}
	
	/**
	 * Primary settings page
	 * @return void
	 */
	public function index() {

		$this->document->setTitle($this->language->get('heading_title'));

		$data = array();

		$data['text_changes'] = $this->language->get('text_changes');
		$data['text_congrats'] = $this->language->get('congrats');

		$data['text_statuses'] = $this->language->get('text_statuses');
		$data['text_gen_secret'] = $this->language->get('text_gen_secret');

		$data['entry_geo_zone'] = $this->language->get('entry_geo_zone');
		$data['entry_status'] = $this->language->get('entry_status');
		$data['entry_sort_order'] = $this->language->get('entry_sort_order');
		$data['entry_callback_url'] = $this->language->get('entry_callback_url');
		$data['entry_api_key'] = $this->language->get('entry_api_key');
		$data['entry_underpayment_slack'] = $this->language->get('entry_underpayment_slack');
		$data['entry_callback_secret'] = $this->language->get('entry_callback_secret');
    	$data['entry_complete_status'] = $this->language->get('entry_complete_status');

		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['button_gen_secret'] = $this->language->get('button_save');

		// OpenCart 4.0.0.0 - 4.0.1.x use 'route|method', OpenCart 4.0.2.0 and later use 'route.method'
		$route = 'extension/blockonomics/payment/blockonomics';

		$data['url_action'] = $this->url->link($this->blockonomics->route($route, 'save'), 'user_token=' . $this->session->data['user_token']);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');
		$data['url_reset'] = $this->url->link('extension/blockonomics/payment/reset', 'user_token=' . $this->session->data['user_token']);
		$data['url_gen_secret'] = $this->url->link($this->blockonomics->route($route, 'gensecret'), 'user_token=' . $this->session->data['user_token']);
		$data['url_test_setup'] = $this->url->link($this->blockonomics->route($route, 'testsetup'), 'user_token=' . $this->session->data['user_token']);

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'])
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_payment'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/blockonomics/payment/blockonomics', 'user_token=' . $this->session->data['user_token'])
		);

		#GENERAL
		$data['blockonomics_connection'] = $this->config->get('payment_blockonomics_connection');
		$this->load->model('localisation/geo_zone');

		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
		$data['blockonomics_geo_zone_id'] = $this->config->get('payment_blockonomics_geo_zone_id');
		$data['blockonomics_status'] = $this->config->get('payment_blockonomics_status');
		$data['blockonomics_sort_order'] = $this->config->get('payment_blockonomics_sort_order');

		#ADVANCED
		$data['blockonomics_callback_url'] = $this->config->get('payment_blockonomics_callback_url');
		$data['blockonomics_callback_secret'] = $this->config->get('payment_blockonomics_callback_secret');
		$data['blockonomics_api_key'] = $this->config->get('payment_blockonomics_api_key');
		$data['blockonomics_underpayment_slack'] = $this->config->get('payment_blockonomics_underpayment_slack');
		$data['blockonomics_extra_margin'] = $this->config->get('payment_blockonomics_extra_margin');
		$data['blockonomics_network_confirmation'] = $this->getNetworkConfirmation();
		$data['blockonomics_store_name'] = $this->config->get('payment_blockonomics_store_name');

		#CHECKOUT TEXTS (one value for each store language)
		$this->load->model('localisation/language');
		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['blockonomics_title'] = array();
		$data['blockonomics_description'] = array();

		foreach ($data['languages'] as $language) {
			$data['blockonomics_title'][$language['language_id']] = $this->config->get('payment_blockonomics_title_' . $language['language_id']);
			$data['blockonomics_description'][$language['language_id']] = $this->config->get('payment_blockonomics_description_' . $language['language_id']);
		}

    #ORDER STATUSES
		$this->load->model('localisation/order_status');
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		$data['blockonomics_complete_status'] = $this->config->get('payment_blockonomics_complete_status');

		#LAYOUT
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		
		$this->response->setOutput($this->load->view('extension/blockonomics/payment/blockonomics', $data));
	}

	public function save(): void {

		$json = [];

		$post = $this->request->post;

		if (!$this->user->hasPermission('modify', 'extension/blockonomics/payment/blockonomics')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!empty($post['payment_blockonomics_callback_url']) && false === filter_var($post['payment_blockonomics_callback_url'], FILTER_VALIDATE_URL)) {
			$json['error'] = $this->language->get('error_callback_url');
		}

		if (empty($post['payment_blockonomics_api_key'])) {
			$json['error'] = $this->language->get('error_api_key');
		}

		// Same limits as the Blockonomics WooCommerce plugin
		$extra_margin = isset($post['payment_blockonomics_extra_margin']) && $post['payment_blockonomics_extra_margin'] !== '' ? $post['payment_blockonomics_extra_margin'] : '0';

		if (!is_numeric($extra_margin) || $extra_margin < 0 || $extra_margin > 4) {
			$json['error'] = $this->language->get('error_extra_margin');
		}

		$underpayment_slack = isset($post['payment_blockonomics_underpayment_slack']) && $post['payment_blockonomics_underpayment_slack'] !== '' ? $post['payment_blockonomics_underpayment_slack'] : '0';

		if (!is_numeric($underpayment_slack) || $underpayment_slack < 0 || $underpayment_slack > 20) {
			$json['error'] = $this->language->get('error_underpayment_slack');
		}

		$network_confirmation = isset($post['payment_blockonomics_network_confirmation']) ? (string)$post['payment_blockonomics_network_confirmation'] : '2';

		if (!in_array($network_confirmation, array('0', '1', '2'), true)) {
			$json['error'] = $this->language->get('error_network_confirmation');
		}

		if (!$json) {

			$keys = array('geo_zone_id', 'status', 'sort_order', 'callback_secret', 'callback_url', 'api_key', 'complete_status');

			$setting = array();

			foreach ($keys as $key) {
				$setting['payment_blockonomics_' . $key] = isset($post['payment_blockonomics_' . $key]) ? $post['payment_blockonomics_' . $key] : '';
			}

			$setting['payment_blockonomics_extra_margin'] = $extra_margin;
			$setting['payment_blockonomics_underpayment_slack'] = $underpayment_slack;
			$setting['payment_blockonomics_network_confirmation'] = $network_confirmation;

			// Checkout title and description for each language
			$this->load->model('localisation/language');

			foreach ($this->model_localisation_language->getLanguages() as $language) {
				foreach (array('title', 'description') as $key) {
					$name = 'payment_blockonomics_' . $key . '_' . $language['language_id'];
					$setting[$name] = isset($post[$name]) ? $post[$name] : '';
				}
			}

			// Values that the form does not send. editSetting() deletes all values of the extension first.
			// The store name comes from Test Setup: clear it if the API key changes.
			if ($setting['payment_blockonomics_api_key'] == $this->config->get('payment_blockonomics_api_key')) {
				$setting['payment_blockonomics_store_name'] = (string)$this->config->get('payment_blockonomics_store_name');
			} else {
				$setting['payment_blockonomics_store_name'] = '';
			}

			$setting['payment_blockonomics_version'] = $this->blockonomics->version;

			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('payment_blockonomics', $setting);
			
			$json['success'] = $this->language->get('text_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function gensecret() {

		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/blockonomics/payment/blockonomics')) {
			$json['error'] = $this->language->get('error_permission');
		}

		$secret = md5(uniqid(rand(), true));

		$json['callback_secret'] = $secret;
		$json['callback_url'] = $this->getCallbackUrl($secret);

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
  	}

  	public function testsetup() {
		$json = [];

		if (!$this->user->hasPermission('modify', 'extension/blockonomics/payment/blockonomics')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!$json) {

			$response = $this->blockonomics->testsetup();

			if($response){
				// Use the language text if it exists, else show the message from Blockonomics
				$key = 'error_' . $response;
				$message = $this->language->get($key);

				$json['error'] = ($message != $key) ? $message : $response;
			}

			// Test Setup saves the name of the matching Blockonomics store
			$json['store_name'] = (string)$this->config->get('payment_blockonomics_store_name');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Install the extension by setting up some smart defaults
	 * @return void
	 */
	public function install() {

    	$this->load->model('localisation/order_status');
		$order_statuses = $this->model_localisation_order_status->getOrderStatuses();
		// 2 = 'Processing' in a default OpenCart installation.
		// Do not use null, because OpenCart cannot save a null setting value (TypeError).
		$default_complete_status = 2;

		foreach ($order_statuses as $order_status) {
			if ($order_status['name'] == 'Processing') {
				$default_complete_status = $order_status['order_status_id'];
			}
    	}

		$this->blockonomics->log("info", $default_complete_status );

		//Generate callback secret
		$secret = md5(uniqid(rand(), true));

		$default_callback_url = $this->getCallbackUrl($secret);

		$this->db->query("DELETE FROM ".DB_PREFIX."setting WHERE code = 'blockonomics'");
		
		$default_settings = array(
			'payment_blockonomics_token' => '',
			'payment_blockonomics_geo_zone_id' => '0',
			'payment_blockonomics_status' => '0',
			'payment_blockonomics_sort_order' => '',
			'payment_blockonomics_callback_secret' => $secret,
			'payment_blockonomics_callback_url' => $default_callback_url,
			'payment_blockonomics_api_key' => '',
			'payment_blockonomics_underpayment_slack' => '0',
			'payment_blockonomics_extra_margin' => '0',
			'payment_blockonomics_network_confirmation' => '2',
			'payment_blockonomics_store_name' => '',
			'payment_blockonomics_version' => $this->blockonomics->version,
			'payment_blockonomics_complete_status' => $default_complete_status
		);

		$this->load->model('setting/setting');
		$this->model_setting_setting->editSetting('payment_blockonomics', $default_settings);

		$this->db->query(
			"CREATE TABLE IF NOT EXISTS ".DB_PREFIX."blockonomics_bitcoin_orders (
					id INT UNSIGNED NOT NULL AUTO_INCREMENT,
					id_order INT UNSIGNED NOT NULL,
					timestamp INT(8) NOT NULL,
					addr varchar(255) NOT NULL,
					txid varchar(255) NOT NULL,
					status int(8) NOT NULL,
					value double(10,2) NOT NULL,
					bits int(8) NOT NULL,
					bits_payed int(8) NOT NULL,
					PRIMARY KEY (id),
				UNIQUE KEY order_table (addr))"
		);
	}

	/**
	 * Uninstall the extension by removing the settings
	 * @return void
	 */
	public function uninstall() {
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('payment_blockonomics');
		$this->db->query('DROP TABLE IF EXISTS `' .DB_PREFIX. 'blockonomics_bitcoin_orders`;');
	}

	/**
	 * Returns the number of network confirmations that completes a payment (0, 1 or 2, default 2)
	 *
	 * Installations of earlier versions do not have this setting.
	 *
	 * @return string
	 */
	private function getNetworkConfirmation() {
		$value = (string)$this->config->get('payment_blockonomics_network_confirmation');

		return in_array($value, array('0', '1', '2'), true) ? $value : '2';
	}

	/**
	 * Makes the catalog (store front) callback URL for the callback secret
	 *
	 * The method separator ('|' or '.') is selected for the installed OpenCart version.
	 *
	 * @param string $secret Callback secret
	 * @return string
	 */
	private function getCallbackUrl($secret) {
		$callback_url = $this->url->link($this->blockonomics->route('extension/blockonomics/payment/blockonomics', 'callback'), 'secret=' . $secret, true);

		// The admin URL object makes admin URLs. Change them to catalog URLs.
		$callback_url = str_replace(HTTP_SERVER, HTTP_CATALOG, $callback_url);

		if (defined('HTTPS_SERVER') && defined('HTTPS_CATALOG')) {
			$callback_url = str_replace(HTTPS_SERVER, HTTPS_CATALOG, $callback_url);
		}

		return str_replace('&amp;', '&', $callback_url);
	}
}
