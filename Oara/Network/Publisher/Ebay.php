<?php
/**
 The goal of the Open Affiliate Report Aggregator (OARA) is to develop a set
 of PHP classes that can download affiliate reports from a number of affiliate networks, and store the data in a common format.

 Copyright (C) 2014  Fubra Limited
 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License as published by
 the Free Software Foundation, either version 3 of the License, or any later version.
 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.
 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.

 Contact
 ------------
 Fubra Limited <support@fubra.com> , +44 (0)1252 367 200
 **/
/**
 * Export Class
 *
 * @author     Carlos Morillo Merino
 * @category   Oara_Network_Publisher_Ebay
 * @copyright  Fubra Limited
 * @version    Release: 01.00
 *
 */
class Oara_Network_Publisher_Ebay extends Oara_Network {

	private $_credentials = null;
	/**
	 * Client
	 * @var unknown_type
	 */
	private $_client = null;

    protected $_sitesAllowed = array();

    /**
     * @param $credentials
     */
	public function __construct($credentials) {
		$this->_credentials = $credentials;

		// The credentials need to be URL-encoded, because they might be used as URL parameters
		$this->_credentials['user'] = urlencode($this->_credentials['user']);
		$this->_credentials['password'] = urlencode($this->_credentials['password']);

        $valuesLogin = array(
            new Oara_Curl_Parameter('login_username', $this->_credentials['user']),
            new Oara_Curl_Parameter('login_password', $this->_credentials['password']),
            new Oara_Curl_Parameter('submit_btn', 'GO'),
            new Oara_Curl_Parameter('hubpage', 'y')
        );

        $loginUrl = 'https://ebaypartnernetwork.com/PublisherLogin?hubpage=y&lang=en-US?';
        $this->_client = new Oara_Curl_Access($loginUrl, $valuesLogin, $this->_credentials);
	}

	/**
	 * Check the connection
	 */
    public function checkConnection() {
        //If not login properly the construct launch an exception
        $connection = true;
        $yesterday = new \Zend_Date ();
        $yesterday->subDay(2);
        $urls = array();
        $urls[] = new Oara_Curl_Request("https://publisher.ebaypartnernetwork.com/PublisherReportsTx?pt=2&start_date={$yesterday->toSTring("M/d/yyyy")}&end_date={$yesterday->toSTring("M/d/yyyy")}&user_name={$this->_credentials['user']}&user_password={$this->_credentials['password']}&advIdProgIdCombo=&tx_fmt=2&submit_tx=Download", array());
        $exportReport = $this->_client->get($urls);

        if (preg_match("/DOCTYPE html PUBLIC/", $exportReport[0])){
            $connection = false;
        }
        return $connection;
    }
	/**
	 * (non-PHPdoc)
	 * @see library/Oara/Network/Oara_Network_Publisher_Interface#getMerchantList()
	 */
	public function getMerchantList() {
		$merchants = array();

		$obj = array();
		$obj['cid'] = "1";
		$obj['name'] = "Ebay";
		$obj['url'] = "https://publisher.ebaypartnernetwork.com";
		$merchants[] = $obj;

		return $merchants;
	}

    /**
     * (non-PHPdoc)
     * @see library/Oara/Network/Oara_Network_Publisher_Interface#getTransactionList($aMerchantIds, $dStartDate, $dEndDate, $sTransactionStatus)
     */
    public function getTransactionList($merchantList = null, Zend_Date $dStartDate = null, Zend_Date $dEndDate = null, $merchantMap = null) {
        $totalTransactions = array();
        $urls = array();
        $urls[] = new Oara_Curl_Request("https://publisher.ebaypartnernetwork.com/PublisherReportsTx?pt=2&start_date={$dStartDate->toSTring("M/d/yyyy")}&end_date={$dEndDate->toSTring("M/d/yyyy")}&user_name={$this->_credentials['user']}&user_password={$this->_credentials['password']}&advIdProgIdCombo=&tx_fmt=3&submit_tx=Download", array());
        $exportData = array();
        try{
            $exportReport = $this->_client->get($urls, 'content', 5);
            $exportData = str_getcsv($exportReport[0], "\n");
        } catch (Exception $e){

        }
        $num = count($exportData);
        for ($i = 1; $i < $num; $i++) {
            $transactionExportArray = str_getcsv($exportData[$i], "\t");

            if ($transactionExportArray[2] == "Winning Bid (Revenue)" && (empty($this->_sitesAllowed) || in_array($transactionExportArray[5], $this->_sitesAllowed))){


                $transaction = Array();
                $transaction['merchantId'] = 1;
                $transactionDate = new Zend_Date($transactionExportArray[1], 'yyyy-MM-dd', 'en');
                $transaction['date'] = $transactionDate->toString("yyyy-MM-dd HH:mm:ss");
                unset($transactionDate);
                if ($transactionExportArray[10] != null) {
                    $transaction['custom_id'] = $transactionExportArray[10];
                }

                $transaction['status'] = Oara_Utilities::STATUS_CONFIRMED;

                $transaction['amount'] = Oara_Utilities::parseDouble($transactionExportArray[3]);
                $transaction['commission'] = Oara_Utilities::parseDouble($transactionExportArray[20]);
                $totalTransactions[] = $transaction;
            }
        }
        return $totalTransactions;
    }

	/**
	 * (non-PHPdoc)
	 * @see Oara/Network/Oara_Network_Publisher_Base#getPaymentHistory()
	 */
	public function getPaymentHistory() {
		$paymentHistory = array();

		return $paymentHistory;
	}

}
