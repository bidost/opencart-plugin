<?php

/**
 * Blockonomics Library for OpenCart
 */

namespace Opencart\Extension\Blockonomics\System\Library;

class Blockonomics {

	/** @var string $version */
	public $version = '4.1.0';

	/** @var Registry $registry */
	private $registry;

	/** @var Log $logger */
	public $logger;

  /** @var string $blockonomics_websocket_url */
  public $blockonomics_websocket_url;

  /** @var string $blockonomics_new_address_url */
  public $blockonomics_new_address_url;

  /** @var string $blockonomics_price_url */
  public $blockonomics_price_url;

  /** @var string $blockonomics_stores_url */
  public $blockonomics_stores_url;

	/**
	 * Blockonomics Library constructor
	 * @param Registry $registry
	 */
	public function __construct($registry) {
		$this->registry = $registry;

    // Note: Do not use \Opencart\System\Library\Encryption here.
    // OpenCart 4.1.0.0 and later do not have this class.

    // Setup logging
		$this->logger = new \Opencart\System\Library\Log('blockonomics.log');

    $blockonomics_base_url = 'https://www.blockonomics.co';
    $this->blockonomics_websocket_url = 'wss://www.blockonomics.co';
    //$blockonomics_base_url = 'http://localhost:8080';
    //$this->blockonomics_websocket_url = 'ws://localhost:8080';
    $this->blockonomics_new_address_url = $blockonomics_base_url.'/api/new_address';
    $this->blockonomics_price_url = $blockonomics_base_url.'/api/price';
    $this->blockonomics_stores_url = $blockonomics_base_url.'/api/v2/stores?wallets=true';
    $this->setting('debug', 0);
	}

  /**
   * Returns the character that separates the controller route and the method name.
   *
   * OpenCart 4.0.0.0 - 4.0.1.x use '|' (for example 'extension/x/payment/y|confirm').
   * OpenCart 4.0.2.0 and later (4.1.x too) use '.' (for example 'extension/x/payment/y.confirm').
   *
   * @return string
   */
  public function getMethodSeparator() {
    if (defined('VERSION') && version_compare(VERSION, '4.0.2.0', '>=')) {
      return '.';
    }

    return '|';
  }

  /**
   * Makes a route that calls a method of a controller, with the correct separator
   * for the installed OpenCart version.
   *
   * @param string $route  Controller route (for example 'extension/blockonomics/payment/blockonomics')
   * @param string $method Method name (for example 'callback')
   * @return string
   */
  public function route($route, $method) {
    return $route . $this->getMethodSeparator() . $method;
  }

	/**
	 * Magic getter for Registry items
	 *
	 * Allows use of $this->db instead of $this->registry->get('db') for example
	 *
	 * @return mixed
	 */
	public function __get($name) {
		return $this->registry->get($name);
	}

  /**
   * Sends a request to the Blockonomics API
   *
   * The request format is the same as in the current Blockonomics WooCommerce plugin:
   * JSON content type, 'Authorization: Bearer <API key>', short timeouts.
   *
   * @param string $method 'GET' or 'POST'
   * @param string $url Full URL with the query string
   * @param bool $auth Send the API key
   * @return \stdClass response_code (0 if the request failed), body, data (decoded JSON or null), curl_error
   */
  private function request($method, $url, $auth = true) {
    $headers = array('Content-Type: application/json');

    if ($auth) {
      $headers[] = 'Authorization: Bearer ' . $this->setting('api_key');
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method == 'POST') {
      curl_setopt($ch, CURLOPT_POST, true);
      curl_setopt($ch, CURLOPT_POSTFIELDS, '');
    }

    $body = curl_exec($ch);

    $response = new \stdClass();
    $response->response_code = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $response->curl_error = ($body === false) ? curl_error($ch) : '';
    $response->body = ($body === false) ? '' : (string)$body;
    $response->data = ($response->body !== '') ? json_decode($response->body) : null;

    // Note: curl_close() is not used. It does nothing since PHP 8.0 and it is deprecated in PHP 8.5.
    unset($ch);

    if ($response->curl_error !== '') {
      $response->response_code = 0;
      $this->log('error', 'Request to ' . strtok($url, '?') . ' failed: ' . $response->curl_error);
    }

    return $response;
  }

  /**
   * Returns the error message in an API response ('message' or 'error.message'), or an empty string
   *
   * @param \stdClass $response Response from request()
   * @return string
   */
  private function getApiErrorMessage($response) {
    if (isset($response->data->message) && is_string($response->data->message)) {
      return $response->data->message;
    }

    if (isset($response->data->error->message) && is_string($response->data->error->message)) {
      return $response->data->error->message;
    }

    return '';
  }

  /**
   * Gets the price of 1 BTC in a fiat currency
   *
   * GET /api/price?currency=<fiat>&crypto=BTC
   * The API returns {"price": null} for a currency that Blockonomics does not support.
   *
   * @param string $currency_code Fiat currency code (for example 'USD')
   * @return \stdClass price (number, or '' on error) and error (message, or '')
   */
  public function fetchBTCPrice($currency_code) {
    $result = new \stdClass();
    $result->price = '';
    $result->error = '';

    $url = $this->blockonomics_price_url . '?' . http_build_query(array('currency' => $currency_code, 'crypto' => 'BTC'));
    $response = $this->request('GET', $url, false);

    if ($response->response_code == 0) {
      $result->error = 'blockedHttps';
    } elseif (is_object($response->data) && property_exists($response->data, 'price') && $response->data->price === null) {
      $result->error = 'Currency ' . $currency_code . ' is not supported by Blockonomics';
    } elseif ($response->response_code != 200 || !isset($response->data->price) || !is_numeric($response->data->price) || $response->data->price <= 0) {
      $message = $this->getApiErrorMessage($response);
      $result->error = ($message !== '') ? $message : 'Could not get the BTC price. HTTP status: ' . $response->response_code;
    } else {
      $result->price = $response->data->price;
    }

    if ($result->error !== '') {
      $this->log('error', 'Price API: ' . $result->error);
    }

    return $result;
  }

  /**
   * Gets the price of 1 BTC in the default store currency
   *
   * @return float|string Price, or '' on error
   */
  public function getBTCPrice() {
    return $this->fetchBTCPrice($this->config->get('config_currency'))->price;
  }

  /**
   * Logs with an arbitrary level.
   * @param string $level The type of log.
   *						Should be 'error', 'warn', 'info', 'debug', 'trace'
   *						In normal mode, 'error' and 'warn' are logged
   *						In debug mode, all are logged
   * @param string $message The message of the log
   * @param int $depth How deep to go to find the calling function
   * @return void
   */
  public function log($level, $message, $depth = 0) {
    $level = strtoupper($level);
    $prefix = '[' . $level . ']';

    if ($this->setting('debug') === '1') {
      $depth += 1;
      $prefix .= '{';
      $backtrace = debug_backtrace();
      if (isset($backtrace[$depth]['class'])) {
        $class = preg_replace('/[a-z]/', '', $backtrace[$depth]['class']);
        $prefix .= $class . $backtrace[$depth]['type'];
      }
      if (isset($backtrace[$depth]['function'])) {
        $prefix .= $backtrace[$depth]['function'];
      }
      $prefix .= '}';
    }

    if ('ERROR' === $level || 'WARN' === $level || $this->setting('debug') === '1') {
      $this->logger->write($prefix . ' ' . $message);
    }
  }

  /**
   * Gets a new BTC address from the Blockonomics API
   *
   * POST /api/new_address?match_callback=secret%3D<secret>&crypto=BTC[&reset=1]
   *
   * 'match_callback' is a part of the HTTP callback URL of the Blockonomics store.
   * Blockonomics uses it to find the correct store if the account has more than one store.
   * The current WooCommerce plugin sends 'secret=<callback secret>'. An empty value is not sent,
   * because an empty value matches all stores.
   *
   * @param bool $reset Send 'reset=1' (used by Test Setup)
   * @return \stdClass response_code (0 if the request failed), address ('' on error), message (error message from the API)
   */
  public function getNewAddress($reset = false)
  {
      $params = array();

      $callback_secret = (string)$this->setting('callback_secret');

      if ($callback_secret !== '') {
          $params['match_callback'] = 'secret=' . $callback_secret;
      }

      $params['crypto'] = 'BTC';

      if ($reset) {
          $params['reset'] = 1;
      }

      $response = $this->request('POST', $this->blockonomics_new_address_url . '?' . http_build_query($params));

      $result = new \stdClass();
      $result->response_code = $response->response_code;
      $result->address = (isset($response->data->address) && is_string($response->data->address)) ? $response->data->address : '';
      $result->message = $this->getApiErrorMessage($response);

      if ($result->response_code != 200 || $result->address === '') {
          $this->log('error', 'New address API: HTTP status ' . $result->response_code . ' ' . ($result->message !== '' ? $result->message : $response->body));
      }

      return $result;
  }

  /**
   * Finds the Blockonomics store whose HTTP callback URL has this callback secret
   *
   * Same logic as the current WooCommerce plugin: compare the 'secret' argument of the callback URL.
   * If more than one store matches, use the store that has wallets.
   *
   * @param array $stores Stores from GET /api/v2/stores?wallets=true
   * @param string $secret Callback secret
   * @return object|null
   */
  private function findMatchingStore($stores, $secret) {
    $best_store = null;

    foreach ($stores as $store) {
      if (!is_object($store) || !isset($store->http_callback) || !is_string($store->http_callback)) {
        continue;
      }

      $query = parse_url(html_entity_decode($store->http_callback), PHP_URL_QUERY);

      if (!is_string($query)) {
        continue;
      }

      parse_str($query, $args);

      if (isset($args['secret']) && $args['secret'] === $secret) {
        if (!$best_store || (!empty($store->wallets) && empty($best_store->wallets))) {
          $best_store = $store;
        }
      }
    }

    return $best_store;
  }

  /**
   * Checks if a Blockonomics store has a wallet for a cryptocurrency
   *
   * @param object $store Store from GET /api/v2/stores?wallets=true
   * @param string $crypto For example 'BTC'
   * @return bool
   */
  private function storeHasCrypto($store, $crypto) {
    if (empty($store->wallets) || !is_array($store->wallets)) {
      return false;
    }

    foreach ($store->wallets as $wallet) {
      if (isset($wallet->crypto) && is_string($wallet->crypto) && strtoupper($wallet->crypto) === strtoupper($crypto)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Tests the Blockonomics setup
   *
   * Same steps as the Test Setup of the current WooCommerce plugin:
   * 1. GET /api/v2/stores?wallets=true (API key is correct, the account has stores)
   * 2. Find the store whose HTTP callback URL has this callback secret
   * 3. The store has a BTC wallet
   * 4. The price API supports the store currency
   * 5. POST /api/new_address can make a BTC address
   *
   * Note: The WooCommerce plugin stopped using the old '/api/address?only_xpub=true&get_callback=true'
   * and '/api/update_callback' endpoints in 2024, when Blockonomics changed to stores.
   * The store owner adds the callback URL to a store on the Blockonomics dashboard.
   *
   * @return string Empty string if the setup is correct, else an error key (see the admin language file) or an error message
   */
  public function testsetup()
  {
    $api_key = (string)$this->setting('api_key');

    if ($api_key === '') {
      return 'api_key';
    }

    $secret = (string)$this->setting('callback_secret');

    if ($secret === '') {
      return 'noSecret';
    }

    // 1. Stores
    $response = $this->request('GET', $this->blockonomics_stores_url);

    if ($response->response_code == 0) {
      return 'blockedHttps';
    }

    if ($response->response_code == 401) {
      return 'incorrectApi';
    }

    if ($response->response_code != 200) {
      $message = $this->getApiErrorMessage($response);

      return ($message !== '') ? $message : 'API error. HTTP status: ' . $response->response_code;
    }

    if (!isset($response->data->data) || !is_array($response->data->data)) {
      return 'invalidResponse';
    }

    if (empty($response->data->data)) {
      return 'noStore';
    }

    // 2. Store with this callback URL
    $store = $this->findMatchingStore($response->data->data, $secret);

    if (!$store) {
      return 'noMatchingStore';
    }

    // 3. BTC wallet on the store
    if (!$this->storeHasCrypto($store, 'BTC')) {
      return 'noBtcWallet';
    }

    // 4. Price for the store currency
    $price = $this->fetchBTCPrice($this->config->get('config_currency'));

    if ($price->error !== '') {
      return $price->error;
    }

    // 5. New address
    $new_address = $this->getNewAddress(true);

    if ($new_address->response_code == 0) {
      return 'blockedHttps';
    }

    if ($new_address->response_code != 200 || $new_address->address === '') {
      return ($new_address->message !== '') ? $new_address->message : 'Could not generate new address. HTTP status: ' . $new_address->response_code;
    }

    return '';
  }

	/**
	 * Constructs some helpful diagnostic info.
	 * @return string
	 */
	public function getServerInfo() {
		$gmp	= extension_loaded('gmp') ? 'enabled' : 'missing';
		$bcmath = extension_loaded('bcmath') ? 'enabled' : 'missing';
		$info   = "<pre><strong>Server Information:</strong>\n" .
					"PHP: " . phpversion() . "\n" .
					"PHP-GMP: " . $gmp . "\n" .
					"PHP-BCMATH: " . $bcmath . "\n" .
					"OpenCart: " . VERSION . "\n" .
					"Blockonomics Plugin: " . $this->version . "\n" .
					"Blockonomics Lib: v2.2.20\n";
		return $info;
	}

  /**
	 * Better setting method for blockonomics settings
	 *
	 * Automatically persists to database on set and combines getting and setting into one method
	 * Assumes blockonomics_ prefix
	 *
	 * @param string $key Setting key
	 * @param string $value Setting value if setting the value
	 * @return string|null|void Setting value, or void if setting the value
	 */
	public function setting($key, $value = null) {
		// Normalize key
		$code = 'payment_blockonomics';
		$key = $code.'_' . $key;

		// Set the setting
		if (func_num_args() === 2) {
			if (!is_array($value)) {
				$this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape($value) . "', serialized = '0' WHERE `code` = '".$code."' AND `key` = '" . $this->db->escape($key) . "' AND store_id = '0'");
			} else {
				$this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape(serialize($value)) . "', serialized = '1' code `group` = 'blockonomics' AND `key` = '" . $this->db->escape($key) . "' AND store_id = '0'");
			}
			return $this->config->set($key, $value);
		}

		// Get the setting
		return $this->config->get($key);
	}

  /**
   * Returns the Blockonomics page of a BTC transaction
   *
   * The WooCommerce plugin uses this format since 2024. The old '/api/tx?txid=' link is used only for BCH there.
   *
   * @param string $txid
   * @param string $addr
   * @return string
   */
  public function getTxUrl($txid, $addr) {
    return 'https://www.blockonomics.co/#/search?' . http_build_query(array('q' => $txid, 'addr' => $addr));
  }

  public function get_crypto_rate_from_params($value, $satoshi) {
    // Crypto Rate is re-calculated here and may slightly differ from the rate provided by Blockonomics
    // This is required to be recalculated as the rate is not stored anywhere in $order, only the converted satoshi amount is.
    // This method also helps in having a constant conversion and formatting for both JS and NoJS Templates avoiding the scientific notations.
    return number_format($value*1.0e8/$satoshi, 2, '.', '');
  }

  public function fix_displaying_small_values($satoshi){
    if ($satoshi < 10000){
      return rtrim(number_format($satoshi/1.0e8, 8),0);
    } else {
      return $satoshi/1.0e8;
    }
  }
}
