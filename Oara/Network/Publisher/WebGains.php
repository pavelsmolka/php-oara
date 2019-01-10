<?php

namespace Oara\Network\Publisher;

use Oara\SanitizingSoapClient;

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

/**
 * Api Class
 *
 * @author     Carlos Morillo Merino
 * @category   Wg
 * @copyright  Fubra Limited
 * @version    Release: 01.00
 *
 */
class WebGains extends \Oara\Network
{

	private $_soapClient = null;
	private $_server = null;
	private $_campaignMap = array();
	protected $_sitesAllowed = array();

	/**
	 * @param $credentials
	 */
	public function login($credentials)
	{
		$this->_user = $credentials['user'];
		$this->_password = $credentials['password'];
		$this->_client = new \Oara\Curl\Access($credentials, $this->_proxies);
        $proxy = ($this->getProxy('http')) ? $this->getProxy('http')->asSoapOptions() : [];

		$wsdlUrl = 'http://ws.webgains.com/aws.php';
		//Setting the client.
		$this->_soapClient = new SanitizingSoapClient($wsdlUrl, array('login' => $this->_user,
				'encoding' => 'UTF-8',
				'password' => $this->_password,
				'compression' => SOAP_COMPRESSION_ACCEPT | SOAP_COMPRESSION_GZIP | SOAP_COMPRESSION_DEFLATE,
				'soap_version' => SOAP_1_1) + $proxy);

		$serverArray = array();
		$serverArray["uk"] = 'www.webgains.com';
		$serverArray["fr"] = 'www.webgains.fr';
		$serverArray["us"] = 'us.webgains.com';
		$serverArray["de"] = 'www.webgains.de';
		$serverArray["fr"] = 'www.webgains.fr';
		$serverArray["nl"] = 'www.webgains.nl';
		$serverArray["dk"] = 'www.webgains.dk';
		$serverArray["se"] = 'www.webgains.se';
		$serverArray["es"] = 'www.webgains.es';
		$serverArray["ie"] = 'www.webgains.ie';
		$serverArray["it"] = 'www.webgains.it';

		$loginUrlArray = array();
		$loginUrlArray["uk"] = 'http://www.webgains.com/loginform.html?action=login';
		$loginUrlArray["fr"] = 'http://www.webgains.fr/loginform.html?action=login';
		$loginUrlArray["us"] = 'http://us.webgains.com/loginform.html?action=login';
		$loginUrlArray["de"] = 'http://www.webgains.de/loginform.html?action=login';
		$loginUrlArray["fr"] = 'http://www.webgains.fr/loginform.html?action=login';
		$loginUrlArray["nl"] = 'http://www.webgains.nl/loginform.html?action=login';
		$loginUrlArray["dk"] = 'http://www.webgains.dk/loginform.html?action=login';
		$loginUrlArray["se"] = 'http://www.webgains.se/loginform.html?action=login';
		$loginUrlArray["es"] = 'http://www.webgains.es/loginform.html?action=login';
		$loginUrlArray["ie"] = 'http://www.webgains.ie/loginform.html?action=login';
		$loginUrlArray["it"] = 'http://www.webgains.it/loginform.html?action=login';

		$valuesLogin = array(
				new \Oara\Curl\Parameter('user_type', 'affiliateuser'),
				new \Oara\Curl\Parameter('username', $this->_user),
				new \Oara\Curl\Parameter('password', $this->_password)
		);

		foreach ($loginUrlArray as $country => $url) {

			$urls = array();
			$urls[] = new \Oara\Curl\Request($url, $valuesLogin);
			$exportReport = $this->_client->post($urls);
			if (\preg_match("/logout.html/", $exportReport[0])) {
				$this->_server = $serverArray[$country];
				$this->_campaignMap = self::getCampaignMap($exportReport[0]);
				break;
			}
		}

	}

	/**
	 * @param $html
	 * @return array
	 */
	private function getCampaignMap($html)
	{
		$campaingMap = array();

		$doc = new \DOMDocument();
		@$doc->loadHTML($html);
		$xpath = new \DOMXPath($doc);
		$results = $xpath->query('//select[@name="campaignswitchid"]');
		$merchantLines = $results->item(0)->childNodes;
        for ($i = 0; $i < $merchantLines->length; $i++) {
            $line = $merchantLines->item($i);
            if ($line->attributes) {
                $cid = $line->attributes->getNamedItem("value")->nodeValue;
                $name = $line->nodeValue;
                if (\count($this->_sitesAllowed) == 0 || \in_array($name, $this->_sitesAllowed)) {
                    if (\is_numeric($cid)) {
                        $campaingMap[$cid] = $line->nodeValue;
                    }
                }
            }

        }
		return $campaingMap;
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

		return $credentials;
	}

	/**
	 * @return bool
	 */
	public function checkConnection()
	{
		$connection = false;
		if ($this->_server != null) {
			$connection = true;
		}
		return $connection;
	}

	/**
	 * @return array
	 */
	public function getMerchantList()
	{
		$merchantList = Array();
		foreach ($this->_campaignMap as $campaignKey => $campaignValue) {
			$merchants = $this->_soapClient->getProgramsWithMembershipStatus($this->_user, $this->_password, $campaignKey);
			foreach ($merchants as $merchant) {
				if ($merchant->programMembershipStatusName == 'Live' || $merchant->programMembershipStatusName == 'Joined') {
					$merchantList[$merchant->programID]["cid"] = $merchant->programID;
					$merchantList[$merchant->programID]["name"] = $merchant->programName;
				}

			}
		}
		return $merchantList;
	}


	/**
	 * @param null $merchantList
	 * @param \DateTime|null $dStartDate
	 * @param \DateTime|null $dEndDate
	 * @return array
	 * @throws Exception
	 */
	public function getTransactionList($merchantList = null, \DateTime $dStartDate = null, \DateTime $dEndDate = null)
	{
		$totalTransactions = Array();

		$merchantListIdList = \Oara\Utilities::getMerchantIdMapFromMerchantList($merchantList);

		foreach ($this->_campaignMap as $campaignKey => $campaignValue) {
			try{
				$transactionList = $this->_soapClient->getFullEarningsWithCurrency($dStartDate->format("Y-m-d\TH:i:s"), $dEndDate->format("Y-m-d\TH:i:s"), $campaignKey, $this->_user, $this->_password);
			} catch(\Exception $e){
				if (preg_match("/60 requests/", $e->getMessage())){
					sleep(60);
					$transactionList = $this->_soapClient->getFullEarningsWithCurrency($dStartDate->format("Y-m-d\TH:i:s"), $dEndDate->format("Y-m-d\TH:i:s"), $campaignKey, $this->_user, $this->_password);
				}
			}
			foreach ($transactionList as $transactionObject) {
				if (isset($merchantListIdList[$transactionObject->programID])) {

					$transactionDate = \DateTime::createFromFormat("Y-m-d\TH:i:s", $transactionObject->date);

					// There is an issue with WebGains SOAP endpoint - it returns transactions
					// which are out of the requested date range. We need to check whether
					// the transaction date is actually in the requested boundaries, and skip
					// it in case it is not.
					if ($transactionDate > $dEndDate || $transactionDate < $dStartDate) {
						// Transaction is not in the requested range
						continue;
					}

					$transaction = array();
					$transaction['merchantId'] = $transactionObject->programID;
					$transaction["date"] = $transactionDate->format("Y-m-d H:i:s");
					$transaction['unique_id'] = $transactionObject->transactionID;
					if ($transactionObject->clickRef != null) {
						$transaction['custom_id'] = $transactionObject->clickRef;
					}

					$transaction['amount'] = $transactionObject->saleValue;
					$transaction['commission'] = $transactionObject->commission;
					$transaction['status'] = $this->getTransactionStatus($transactionObject);
					$transaction['currency'] = $transactionObject->currency;

					$totalTransactions[] = $transaction;
				}
			}
		}
		return $totalTransactions;
	}

	/**
	 * Determines the transaction status based on the SOAP response object.
	 *
	 * Transaction Statuses for WebGains:
	 * Paid - Confirmed
	 * Cleared for Payment - Confirmed
	 * Adjusted - Cleared for Payment - Confirmed
	 * Adjusted - Awaiting Payment - Confirmed
	 * Invoiced - Awaiting Payment - Confirmed
	 * Delayed - Pending
	 * Awaiting Invoice - Pending
	 * In Recall Period - Pending
	 * Adjusted - Awaiting Invoice - Pending
	 * Cancelled - Rejected
	 *
	 * @param \stdClass $transactionObject
	 * @return string One of STATUS_CONFIRMED, STATUS_DECLINED, STATUS_PENDING, STATUS_PAID
	 * @throws \Exception
	 */
	protected function getTransactionStatus(\stdClass $transactionObject) {

		// The "status" field gives us a better visibility
		switch($transactionObject->status) {
			case 'confirmed':
			case 'cleared':
			case 'paid':
				return \Oara\Utilities::STATUS_CONFIRMED;
			case 'delayed':
			case 'notcleared':
				return \Oara\Utilities::STATUS_PENDING;
			case 'cancelled':
				return \Oara\Utilities::STATUS_DECLINED;
			default:
				throw new \Exception('Unexpected transaction status '. $transactionObject->paymentStatus);
		}

	}
}
