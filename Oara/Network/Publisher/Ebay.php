<?php
namespace Oara\Network\Publisher;
/**
 * The goal of the Open Affiliate Report Aggregator (OARA) is to develop a set
 * of PHP classes that can download affiliate reports from a number of affiliate networks, and store the data in a common format.
 *
 * Copyright (C) 2016  Fubra Limited
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Contact
 * ------------
 * Fubra Limited <support@fubra.com> , +44 (0)1252 367 200
 **/

use Oara\Curl\Parameter;

/**
 * Export Class
 *
 * @author     Carlos Morillo Merino
 * @category   Ebay
 * @copyright  Fubra Limited
 * @version    Release: 01.00
 *
 */
class Ebay extends \Oara\Network
{
	/** @var array */
	protected $_credentials;

	/** @var \Oara\Curl\Access */
	private $_client = null;

	/** @var array */
	protected $_sitesAllowed = array();

	/**
	 * @param $credentials
	 */
	public function login($credentials)
	{
		$this->_credentials = $credentials;
		$this->_client = new \Oara\Curl\Access($credentials);

		$valuesLogin = array(
				new \Oara\Curl\Parameter('email', $this->_credentials['user']),
				new \Oara\Curl\Parameter('password', $this->_credentials['password']),
		);
		$loginUrl = 'https://epn.ebay.com/login';

		// TODO the login does not seem to work properly
		$urls = array();
		$urls[] = new \Oara\Curl\Request($loginUrl, $valuesLogin);
		$this->_client->post($urls);
	}

	/**
	 * @return array
	 */
	public function getNeededCredentials()
	{
		$credentials = array();

		$parameter = array();
		$parameter["description"] = "User Log in";
		$parameter["required"] = true;
		$parameter["name"] = "User";
		$credentials["user"] = $parameter;

		$parameter = array();
		$parameter["description"] = "Password to Log in";
		$parameter["required"] = true;
		$parameter["name"] = "Password";
		$credentials["password"] = $parameter;

		$parameter = array();
		$parameter["description"] = "Token to download the report file";
		$parameter["required"] = true;
		$parameter["name"] = "Token";
		$credentials["token"] = $parameter;

		return $credentials;
	}

	/**
	 * @return bool
	 */
	public function checkConnection()
	{
		$yesterday = new \DateTime();
		$yesterday->sub(new \DateInterval('P2D'));

		$urls = array(
				new \Oara\Curl\Request('https://api.epn.ebay.com/rpt/events/v1/detail/tdr', array(
					// new Parameter('csrf', ''), // CSRF is not required, which makes things much easier
						new Parameter('customDateRange', $yesterday->format("m/d/Y") . ' - ' . $yesterday->format("m/d/Y")),
						new Parameter('postDate', null),
						new Parameter('startPostDate', $yesterday->format("Y-m-d")), // Such as '2017-02-21'
						new Parameter('endPostDate', $yesterday->format("Y-m-d")), // Such as '2017-02-21'
						new Parameter('programs', null),
						new Parameter('fileFormat', 'txtheader'),
						new Parameter('username', $this->_credentials['user']),
						new Parameter('password', $this->_credentials['token']),
						new Parameter('isEnc', 'true'),
						new Parameter('eventType', 'all'),
				)),
		);

		try {
			$exportReport = $this->_client->post($urls, 5);
			return is_array($exportReport) && (0 === strpos($exportReport[0], 'Event Date'));
		} catch (\Exception $e) {
			return false;
		}

	}

	/**
	 * @return array
	 */
	public function getMerchantList()
	{
		$merchants = array();

		$obj = array();
		$obj['cid'] = "1";
		$obj['name'] = "Ebay";
		$obj['url'] = "https://publisher.ebaypartnernetwork.com";
		$merchants[] = $obj;

		return $merchants;
	}

	/**
	 * @param null $merchantList
	 * @param \DateTime|null $dStartDate
	 * @param \DateTime|null $dEndDate
	 * @return array
	 */
	public function getTransactionList($merchantList = null, \DateTime $dStartDate = null, \DateTime $dEndDate = null)
	{
		$totalTransactions = array();
		$urls = array(
				new \Oara\Curl\Request('https://api.epn.ebay.com/rpt/events/v1/detail/tdr', array(
					// new Parameter('csrf', ''), // CSRF is not required, which makes things much easier
						new Parameter('customDateRange', $dStartDate->format("m/d/Y") . ' - ' . $dEndDate->format("m/d/Y")),
						new Parameter('postDate', null),
						new Parameter('startPostDate', $dStartDate->format("Y-m-d")), // Such as '2017-02-03'
						new Parameter('endPostDate', $dEndDate->format("Y-m-d")),
						new Parameter('programs', null),
						new Parameter('fileFormat', 'txtheader'),
						new Parameter('username', $this->_credentials['user']),
						new Parameter('password', $this->_credentials['token']),
						new Parameter('isEnc', 'true'),
						new Parameter('eventType', 'all'),
				)),
		);
		$exportData = array();
		try {
			$exportReport = $this->_client->post($urls, 5);
			$exportData = \str_getcsv($exportReport[0], "\n");
		} catch (\Exception $e) {

		}
		$num = \count($exportData);
		for ($i = 1; $i < $num; $i++) {
			$transactionExportArray = \str_getcsv($exportData[$i], "\t");

			if ($transactionExportArray[2] == "Winning Bid (Revenue)" && (empty($this->_sitesAllowed) || \in_array($transactionExportArray[5], $this->_sitesAllowed))) {

				$transaction = Array();
				$transaction['merchantId'] = 1;
				$transactionDate = \DateTime::createFromFormat("Y-m-d", $transactionExportArray[1]);
				$transaction['date'] = $transactionDate->format("Y-m-d H:i:s");
				unset($transactionDate);
				if ($transactionExportArray[10] != null) {
					$transaction['custom_id'] = $transactionExportArray[10];
				}

				$transaction['status'] = \Oara\Utilities::STATUS_CONFIRMED;

				$transaction['amount'] = \Oara\Utilities::parseDouble($transactionExportArray[3]);
				$transaction['commission'] = \Oara\Utilities::parseDouble($transactionExportArray[20]);
				$totalTransactions[] = $transaction;
			}
		}
		return $totalTransactions;
	}

}
