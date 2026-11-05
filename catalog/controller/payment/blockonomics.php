<?php
/**
 * Blockonomics Payment Controller
 */
namespace Opencart\Catalog\Controller\Extension\Blockonomics\Payment;

class Blockonomics extends \Opencart\System\Engine\Controller {

	/** @var boolean $ajax Whether the request was made via AJAX */
	private $ajax = false;

	/** @var BlockonomicsLibrary $blockonomics */
	private $blockonomics;

	/**
	 * Blockonomics Payment Controller Constructor
	 * @param Registry $registry
	 */
	public function __construct($registry) {
		parent::__construct($registry);

		require_once(DIR_EXTENSION . 'blockonomics/system/library/blockonomics.php');

		// Make langauge strings and Blockonomics Library available to all
		$this->load->language('extension/blockonomics/payment/blockonomics');
		$this->blockonomics = new \Opencart\Extension\Blockonomics\System\Library\Blockonomics($registry);

		// Is this an ajax request?
		if (!empty($this->request->server['HTTP_X_REQUESTED_WITH']) &&
			strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
		{
			$this->ajax = true;
		}
	}

	/**
	 * Displays the Payment Method (a redirect button)
	 * @return void
	 */
	public function index() {

		$data['url_redirect'] = $this->url->link($this->blockonomics->route('extension/blockonomics/payment/blockonomics', 'invoice'), $this->getLanguageArgs());
		$data['button_confirm'] = $this->language->get('button_confirm');

		if (isset($this->session->data['error_blockonomics'])) {
			unset($this->session->data['error_blockonomics']);
		}

		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/extension/payment/blockonomics')) {
			return $this->load->view($this->config->get('config_template') . '/template/extension/payment/blockonomics', $data);
		} else {
			return $this->load->view('extension/blockonomics/payment/blockonomics', $data);
		}
	}

	/**
	 * Generatew new bitcoin addres and show the invoice
	 * @return void
	 */
	public function invoice() {

		$this->load->model('checkout/order');

		if (!isset($this->session->data['order_id'])) {
			$this->response->redirect($this->url->link('checkout/cart', $this->getLanguageArgs(), true));
			return;
		}
		$order_info = $this->model_checkout_order->getOrder((int)$this->session->data['order_id']);
		// OpenCart 4.x returns an empty array if the order is not found
		if (empty($order_info)) {
			$this->response->redirect($this->url->link('checkout/cart', $this->getLanguageArgs(), true));
			return;
		}

		$this->document->setTitle($this->language->get('text_title'));

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home', $this->getLanguageArgs())
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_basket'),
			'href' => $this->url->link('checkout/cart', $this->getLanguageArgs())
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_checkout'),
			'href' => $this->url->link('checkout/checkout', $this->getLanguageArgs())
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_invoice'),
			'href' => $this->url->link($this->blockonomics->route('extension/blockonomics/payment/blockonomics', 'invoice'), $this->getLanguageArgs())
		);

		if (empty($order_info['currency_code'])) {
			$this->blockonomics->log('error', 'Cannot prepare invoice without `currency_code`');
			throw new \Exception('Cannot prepare invoice without `currency_code`');
		}

		if (empty($order_info['total'])) {
			$this->blockonomics->log('error', 'Cannot prepare invoice without `total`');
			throw new \Exception('Cannot prepare invoice without `total`');
		}
		
		$this->document->addLink($this->getAssetUrl('catalog/view/css/order.css'), "stylesheet");
		$this->document->addScript($this->getAssetUrl('catalog/view/javascript/reconnecting-websocket.min.js'));
		$this->document->addScript($this->getAssetUrl('catalog/view/javascript/qrious.min.js'));
		$this->document->addScript($this->getAssetUrl('catalog/view/javascript/checkout.js'));
		
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$data['currency_code'] = $this->config->get('config_currency');
		$price = $this->blockonomics->getBTCPrice();

		// Do not divide by an empty price (for example, if the price API is not available)
		if (!is_numeric($price) || $price <= 0) {
			$this->blockonomics->log('error', 'Cannot get the BTC price for currency ' . $data['currency_code']);
			$data['address_error'] = true;
			$this->response->setOutput($this->load->view('extension/blockonomics/payment/blockonomicsinvoice', $data));
			return;
		}

		$fiat_amount = $order_info['total'];
		$bits = intval(1.0e8*$fiat_amount/$price);
		$satoshi_amount = $this->blockonomics->fix_displaying_small_values($bits);
		$data['satoshi_amount'] = $satoshi_amount;
		$data['fiat_amount'] = number_format($fiat_amount, 2);
		$data['crypto_rate'] = $this->blockonomics->get_crypto_rate_from_params($fiat_amount, $bits);
		$order_id = (int)$order_info['order_id'];
    
		$this->model_checkout_order->addHistory($order_id, 1, "", true);

		$current_time = time();
		$data['order_id'] = $order_id;

		// These URLs are used in JavaScript, so do not encode '&' as '&amp;'
		$data['success_url'] = $this->url->link('checkout/success', $this->getLanguageArgs(), true);
		$data['timeout_url'] = $this->url->link($this->blockonomics->route('extension/blockonomics/payment/blockonomics', 'timeout'), $this->getLanguageArgs(), true);
    
		$sql = $this->db->query("SELECT * FROM ".DB_PREFIX."blockonomics_bitcoin_orders WHERE `id_order` = '".$order_id."' LIMIT 1");
		$order = $sql->row;

		// If there is no existing order in database, generate Bitcoin address
		if(!isset($order['id_order'])){
			$response = $this->blockonomics->getNewAddress();
			if($response->response_code == 200 && $response->address !== '') {
				$btc_address=$response->address;
				$data['btc_address'] = $btc_address;
				$data['btc_href'] = "bitcoin:".$btc_address."?amount=".$satoshi_amount;

				$this->blockonomics->log('info', $btc_address, 1);
				$this->blockonomics->log('info', $price, 1);

				//Insert into blockonomics orders table
				$this->db->query("INSERT IGNORE INTO ".DB_PREFIX."blockonomics_bitcoin_orders (id_order, timestamp,  addr, txid, status,value, bits, bits_payed) VALUES
				('".(int)$order_id."','".(int)$current_time."','".$this->db->escape($btc_address)."', '', -1,'".(float)$fiat_amount."','".(int)$bits."', 0)");
			} else {
				$data['address_error'] = true;
			}
		// If existing order is found, use existing BTC address and update price and timestamp
		} else {
			$data['btc_address'] = $order['addr'];
			$data['btc_href'] = "bitcoin:".$order['addr']."?amount=".$satoshi_amount;

			$query="UPDATE ".DB_PREFIX."blockonomics_bitcoin_orders SET bits='".(int)$bits."',value='".(float)$fiat_amount."',timestamp=".(int)$current_time." WHERE addr='".$this->db->escape($order['addr'])."'";
			$this->db->query($query);
		}

		$this->response->setOutput($this->load->view('extension/blockonomics/payment/blockonomicsinvoice', $data));
	}

	/**
	 * Convenience wrapper for blockonomics settings
	 *
	 * Automatically persists to database on set and combines getting and setting into one method
	 * Assumes 'blockonomics_' prefix
	 *
	 * @param string $key Setting key
	 * @param string $value Setting value if setting the value
	 * @return string|null|void Setting value, or void if setting the value
	 */
	public function setting($key, $value = null) {
		// Set the setting
		if (func_num_args() === 2) {
			return $this->blockonomics->setting($key, $value);
		}

		// Get the setting
		return $this->blockonomics->setting($key);
	}

	/**
	 * Callback Handler
	 * @return void
	 */
	public function callback() {
		$this->load->model('checkout/order');

		//Get parameters from callback url
		// Blockonomics sends: secret, addr, status (0 = unconfirmed, 1 = partially confirmed, 2 = confirmed),
		// value (satoshi), txid and, only for unconfirmed Replace-By-Fee transactions, rbf.
		$secret = isset($this->request->get['secret']) ? (string)$this->request->get['secret'] : '';
		$txid = isset($this->request->get['txid']) ? (string)$this->request->get['txid'] : '';
		$value = isset($this->request->get['value']) ? (int)$this->request->get['value'] : 0;
		$status = isset($this->request->get['status']) ? (string)$this->request->get['status'] : '';
		$addr = isset($this->request->get['addr']) ? (string)$this->request->get['addr'] : '';
		$rbf = !empty($this->request->get['rbf']);

		if($secret === '' || $this->setting('callback_secret') != $secret) {
			die('Invalid secret');
		}

		if (!in_array($status, array('0', '1', '2'), true)) {
			$this->blockonomics->log('error', 'Callback with unknown status "'.$status.'" for address '.$addr.' is ignored.');
			return;
		}

		$sql = $this->db->query("SELECT * FROM ".DB_PREFIX."blockonomics_bitcoin_orders WHERE `addr` = '".$this->db->escape($addr)."' LIMIT 1");
		$order = $sql->row;

		if(!isset($order['id_order'])){
			$this->blockonomics->log('error', 'Order with bitcoin address '.$addr.' not found in the records.');
			return;
		}

		// A confirmed address ignores all other callbacks (Blockonomics can send a callback again)
		if ((int)$order['status'] === 2) {
			$this->blockonomics->log('info', 'Callback ignored, payment to '.$addr.' is already confirmed.');
			return;
		}

		$where = " WHERE addr='".$this->db->escape($addr)."' AND status < 2";

		// An unconfirmed Replace-By-Fee transaction can still be cancelled by the sender:
		// keep only the transaction id (same as the Blockonomics WooCommerce plugin).
		if ($rbf) {
			$this->db->query("UPDATE ".DB_PREFIX."blockonomics_bitcoin_orders SET txid='".$this->db->escape($txid)."'".$where);
			$this->blockonomics->log('info', 'RBF transaction '.$txid.' to '.$addr.' is not used until it is confirmed.');
			return;
		}

		//Upate order info. Only one callback can change the status to 2 (confirmed).
		$this->db->query("UPDATE ".DB_PREFIX."blockonomics_bitcoin_orders SET status='".(int)$status."',txid='".$this->db->escape($txid)."',bits_payed=".(int)$value.$where);

		if ((int)$status === 2 && $this->db->countAffected() < 1) {
			$this->blockonomics->log('info', 'Callback ignored, payment to '.$addr.' was confirmed by another callback.');
			return;
		}

		$comment = "";
		$bits = $order['bits'];
		$expected = $bits / 1.0e8;
		$real_paid = $value / 1.0e8;
		$paid = $value / 1.0e8;
		$tx_url = $this->blockonomics->getTxUrl($txid, $addr);

		$underpayment_slack = $this->setting('underpayment_slack') / 100 * $bits;
		if ($value < $bits - $underpayment_slack) {
			$paid = $paid;
		} else {
			$paid = $expected;
		}

		switch ($status) {
			case 0:
				// Add the history only for the first unconfirmed callback, so that the customer gets only one e-mail
				if ((int)$order['status'] !== -1) {
					break;
				}
				$order_status_id = '1';
				$comment = "Waiting for Confirmation on Bitcoin network<br>" .
							"Bitcoin transaction id: $txid <br>" .
							"You can view the transaction at: <br>" .
							"<a href='$tx_url' target='_blank'>$tx_url</a>";
				// Progress the order status
				$this->model_checkout_order->addHistory((int)$order['id_order'], (int)$order_status_id, $comment, true);
				break;
			case 1:
				break;
			case 2:
				if ($paid < $expected) {
					$order_status_id = '7'; // 7 = Canceled
					$comment = "<b>Warning: Invoice canceled as Paid Amount was less than expected</b><br>";
				} else {
					$order_status_id = $this->setting('complete_status');
				}
				$comment .= "Bitcoin transaction id: $txid\r" .
						"Expected amount: $expected BTC\r" .
						"Paid amount: $real_paid BTC\r" .
						"You can view the transaction at:\r" .
						"<a href='$tx_url' target='_blank'>$tx_url</a>";
				// Progress the order status
				$this->model_checkout_order->addHistory((int)$order['id_order'], (int)$order_status_id, $comment, true);
				break;
			default:
				$this->response->redirect($this->url->link('checkout/checkout', $this->getLanguageArgs(), true));
				return;
		}

	}

	/**
	 * Returns the URL of a CSS or JavaScript file of this extension, with a version argument
	 *
	 * The version is the modification time of the file. When the file changes, the URL changes,
	 * so browsers, proxies and CDNs cannot use an old cached copy of the file.
	 *
	 * @param string $file Path in the extension folder (for example 'catalog/view/javascript/checkout.js')
	 * @return string
	 */
	private function getAssetUrl($file) {
		$mtime = @filemtime(DIR_EXTENSION . 'blockonomics/' . $file);

		return 'extension/blockonomics/' . $file . '?v=' . ($mtime ? $mtime : $this->blockonomics->version);
	}

	/**
	 * Returns the URL arguments that keep the language of the customer
	 *
	 * OpenCart 4.x uses the 'language' URL argument to select the store language.
	 *
	 * @return string
	 */
	private function getLanguageArgs() {
		return 'language=' . $this->config->get('config_language');
	}
}
