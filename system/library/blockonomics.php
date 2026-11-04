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

  /** @var string $blockonomics_get_callback_url */
  public $blockonomics_get_callback_url;

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
    $this->blockonomics_price_url = $blockonomics_base_url.'/api/price?currency=';
    $this->blockonomics_get_callback_url = $blockonomics_base_url.'/api/address?&no_balance=true&only_xpub=true&get_callback=true';
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
   * Makes a callback URL comparable when only the secret or the method separator is different.
   *
   * Callback URLs made by OpenCart 4.0.0.0 - 4.0.1.x contain '|callback'.
   * Callback URLs made by OpenCart 4.0.2.0 and later contain '.callback'.
   * Both URLs go to the same callback method.
   *
   * @param string $url
   * @return string
   */
  public function normalizeCallbackUrl($url) {
    $url = str_replace(array('&amp;', '%7C', '%7c', '|'), array('&', '.', '.', '.'), (string)$url);

    return preg_replace('/([?&])secret=[^&]*/', '$1secret=', $url);
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

  /*
   * Make a request using curl
   */
  public function doCurlCall($url, $post_content = '')
  {
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, $url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      if ($post_content) {
          curl_setopt($ch, CURLOPT_POST, 1);
          curl_setopt($ch, CURLOPT_POSTFIELDS, $post_content);
      }
      curl_setopt($ch, CURLOPT_TIMEOUT, 60);
      curl_setopt(
          $ch,
          CURLOPT_HTTPHEADER,
          [
              'Authorization: Bearer ' . $this->setting('api_key'),
              'Content-type: application/x-www-form-urlencoded',
          ]
      );
      $data = curl_exec($ch);
      $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      curl_close($ch);

      $responseObj = new \stdClass();
      $responseObj->data = json_decode($data);
      $responseObj->response_code = $httpcode;
      return $responseObj;
  }

  public function getBTCPrice() {
    //Getting price
    $currency_code = $this->config->get('config_currency');
    $url = $this->blockonomics_price_url.$currency_code;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $data = curl_exec($ch);
    curl_close($ch);

    $responseObj = json_decode($data);
    if (!isset($responseObj) || !isset($responseObj->price)) {
      return '';
    }

    return $responseObj->price;
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

  /*
   * Get new address from Blockonomics Api
   */
  public function getNewAddress($reset = false)
  {
      $api_key = $this->setting('api_key');
      $callback_secret = $this->setting('callback_secret');

      if ($reset) {
          $get_params = "?match_callback=$callback_secret&reset=1";
      } else {
          $get_params = "?match_callback=$callback_secret";
      }

      $ch = curl_init();

      curl_setopt($ch, CURLOPT_URL, 'https://www.blockonomics.co/api/new_address' . $get_params);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');

      $header = 'Authorization: Bearer ' . $api_key;
      $headers = [];
      $headers[] = $header;
      curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

      $contents = curl_exec($ch);
      if (curl_errno($ch)) {
          exit('Error:' . curl_error($ch));
      }

      $responseObj = json_decode($contents);
      //Create response object if it does not exist
      if (!isset($responseObj)) {
          $responseObj = new \stdClass();
      }
      $responseObj->{'response_code'} = curl_getinfo($ch, CURLINFO_RESPONSE_CODE );
      curl_close($ch);
      return $responseObj;
  }

  public function get_callbacks(){
    $url =  $this->blockonomics_get_callback_url;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Authorization: Bearer '.$this->setting('api_key'),
      'Content-type: application/x-www-form-urlencoded'
    ));

    $callback_data = curl_exec($ch);
    $callback_httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $callback_responseObj = json_decode($callback_data);
    $response["httpcode"] = $callback_httpcode;
      foreach($callback_responseObj as $key => $value){
        if(isset($value->callback)){
          $response[$key] = new \stdClass();
          $response[$key]->callback = $value->callback;
          $response[$key]->address = $value->address;
        }
      }

    return $response;
  }

  public function testsetup()
  {
    $api_key = $this->setting('api_key');
    
    if (!isset($api_key) || strlen($api_key) == 0) {
      return 'api_key';
    }
    
    $xpub_fetch_url = 'https://www.blockonomics.co/api/address?&no_balance=true&only_xpub=true&get_callback=true';
    $set_callback_url = 'https://www.blockonomics.co/api/update_callback';
    $error_str = '';

    $response = $this->doCurlCall($xpub_fetch_url);

    $secret = $this->setting('callback_secret');
    $callback_url = htmlspecialchars_decode($this->setting('callback_url'));
    
    if (!isset($response->response_code)) {
        $error_str = 'blockedHttps';
    } elseif ($response->response_code == 401) {
        $error_str = 'incorrectApi';
    } elseif ($response->response_code != 200) {
        $error_str = $response->data;
    } elseif (!isset($response->data) || count($response->data) == 0) {
        $error_str = 'noXpub';
    } elseif (count($response->data) == 1) {
        if (!$response->data[0]->callback || $response->data[0]->callback == null) {
            //No callback URL set, set one
            $post_content = '{"callback": "' . $callback_url . '", "xpub": "' . $response->data[0]->address . '"}';
            $this->doCurlCall($set_callback_url, $post_content);
        } elseif ($response->data[0]->callback != $callback_url) {
            // Check if only secret (or the '|' / '.' method separator) differs
            if ($this->normalizeCallbackUrl($response->data[0]->callback) == $this->normalizeCallbackUrl($callback_url)) {
                //Looks like the user regenrated callback by mistake
                //Just force Update_callback on server
                $post_content = '{"callback": "' . $callback_url . '", "xpub": "' . $response->data[0]->address . '"}';
                $this->doCurlCall($set_callback_url, $post_content);
            } else {
                $error_str = 'existingCallbackUrl';
            }
        }
    } else {
        $error_str = 'multipleXpubs';

        foreach ($response->data as $resObj) {
            if ($resObj->callback == $callback_url) {
                // Matching callback URL found, set error back to empty
                $error_str = '';
            }
        }
    }

    if ($error_str == '') {
        // Test new address generation
        $new_addresss_response = $this->getNewAddress(true);
        if ($new_addresss_response->response_code != 200) {
            $error_str = isset($new_addresss_response->message) ? $new_addresss_response->message : 'Could not generate new address. HTTP status: ' . $new_addresss_response->response_code;
        }
    }

    return $error_str;

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
