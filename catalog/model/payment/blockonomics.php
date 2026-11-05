<?php
/**
 * Blockonomics Payment Model
 */
namespace Opencart\Catalog\Model\Extension\Blockonomics\Payment;

class Blockonomics extends \Opencart\System\Engine\Model {

	/** @var BlockonomicsLibrary $blockonomics */
	private $blockonomics;

	/**
	 * Blockonomics Payment Model Construct
	 * @param Registry $registry
	 */
	public function __construct($registry) {
		parent::__construct($registry);

		require_once(DIR_EXTENSION . 'blockonomics/system/library/blockonomics.php');

		$this->load->language('extension/blockonomics/payment/blockonomics');
		$this->blockonomics = new \Opencart\Extension\Blockonomics\System\Library\Blockonomics($registry);
	}

	/**
	 * Returns the Blockonomics Payment Method if available
	 *
	 * OpenCart 4.0.0.0 - 4.0.1.x call this method.
	 *
	 * @param  array $address Customer billing address
	 * @return array Blockonomics Payment Method if available, else an empty array
	 */
	public function getMethod($address = array()) {
		if (!$this->isAvailable($address)) {
			return array();
		}

		return array(
			'code'       => 'blockonomics',
			'title'      => $this->getTitle(),
			'terms'      => '',
			'sort_order' => $this->blockonomics->setting('sort_order')
		);
	}

	/**
	 * Returns the Blockonomics Payment Methods if available
	 *
	 * OpenCart 4.0.2.0 and later (4.1.x too) call this method.
	 * The selected option code must be '<extension code>.<option code>'.
	 *
	 * @param  array $address Customer billing address (empty if the store does not ask for a payment address)
	 * @return array Blockonomics Payment Methods if available, else an empty array
	 */
	public function getMethods($address = array()) {
		if (!$this->isAvailable($address)) {
			return array();
		}

		$title = $this->getTitle();

		$option_data = array();

		$option_data['blockonomics'] = array(
			'code' => 'blockonomics.blockonomics',
			'name' => $title
		);

		return array(
			'code'       => 'blockonomics',
			'name'       => $title,
			'option'     => $option_data,
			'sort_order' => $this->blockonomics->setting('sort_order')
		);
	}

	/**
	 * Returns the payment method title ('Title' setting for the customer language, or 'Bitcoin')
	 *
	 * @return string
	 */
	private function getTitle() {
		return $this->blockonomics->getTitle();
	}

	/**
	 * Checks if the payment method is available for the address
	 *
	 * @param  array $address Customer billing address
	 * @return bool
	 */
	private function isAvailable($address) {
		$geo_zone_id = (int)$this->blockonomics->setting('geo_zone_id');

		// All Geo Zones configured
		if (!$geo_zone_id) {
			return true;
		}

		// No address if the store does not ask for a payment address:
		// OpenCart 4.0.0.0 - 4.0.1.x send an address with 'country_id' = 0,
		// OpenCart 4.0.2.0 and later send an empty array.
		if (!is_array($address) || empty($address['country_id'])) {
			return true;
		}

		$zone_id = isset($address['zone_id']) ? (int)$address['zone_id'] : 0;

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . $geo_zone_id . "' AND `country_id` = '" . (int)$address['country_id'] . "' AND (`zone_id` = '" . $zone_id . "' OR `zone_id` = '0')");

		// Address is in configured Geo Zone
		return (bool)$query->num_rows;
	}
}
