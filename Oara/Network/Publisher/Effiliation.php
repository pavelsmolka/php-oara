<?php
/**
 * API Class
 *
 * @author     Carlos Morillo Merino
 * @category   Oara_Network_Publisher_Efiliation
 * @copyright  Fubra Limited
 * @version    Release: 01.00
 *
 */
class Oara_Network_Publisher_Effiliation extends Oara_Network {
	/**
	 * Export Credentials
	 * @var array
	 */
	private $_credentials = null;
	/**
	 * Constructor and Login
	 * @param $credentials
	 * @return Oara_Network_Publisher_Effiliation
	 */
	public function __construct($credentials) {

		$this->_credentials = $credentials;

	}
	/**
	 * Check the connection
	 */
	public function checkConnection() {
		$connection = false;

		$content = file_get_contents('http://api.effiliation.com/api/transaction.csv?key='.$this->_credentials["apiPassword"]);
		if (!preg_match("/bad credentials !/", $content, $matches)) {
			$connection = true;
		}
		return $connection;
	}

	/**
	 * (non-PHPdoc)
	 * @see library/Oara/Network/Oara_Network_Publisher_Interface#getMerchantList()
	 */
	public function getMerchantList() {
		$merchants = array();
		
		$content = @file_get_contents('http://api.effiliation.com/api/programmes.xml?key='.$this->_credentials["apiPassword"]);
		$xml = simplexml_load_string($content, null, LIBXML_NOERROR | LIBXML_NOWARNING);
		foreach ($xml->programme as $merchant) {
			if ((string) $merchant->etat == "inscrit") {
				$obj = array();
				$obj['cid'] = (string) $merchant->id_programme;
				$obj['name'] = (string) $merchant->nom;
				$obj['url'] = "";
				$merchants[] = $obj;
			}
		}
		return $merchants;
	}

	/**
	 * (non-PHPdoc)
	 * @see library/Oara/Network/Oara_Network_Publisher_Interface#getTransactionList($aMerchantIds, $dStartDate, $dEndDate)
	 */
	public function getTransactionList($merchantList = null, Zend_Date $dStartDate = null, Zend_Date $dEndDate = null, $merchantMap = null) {
		$totalTransactions = array();

		$content = file_get_contents('http://api.effiliation.com/api/transaction.csv?key='.$this->_credentials["apiPassword"].'&start='.$dStartDate->toString("dd/MM/yyyy").'&end='.$dEndDate->toString("dd/MM/yyyy").'&type=datetran');
		$exportData = str_getcsv($content, "\n");
		$num = count($exportData);
		for ($i = 1; $i < $num; $i++) {
			$transactionExportArray = str_getcsv($exportData[$i], "|");
			if (in_array((int) $transactionExportArray[2], $merchantList)) {
				
				$numFields = 0;
				foreach ($transactionExportArray as $fieldValue){
					if ($fieldValue == "Valide" || $fieldValue == "Attente" || $fieldValue == "Refusé"){
						break;
					}
					$numFields ++;
				}
				
				
				$transaction = Array();
				$merchantId = (int) $transactionExportArray[2];
				$transaction['merchantId'] = $merchantId;
				$transaction['date'] = $transactionExportArray[4];
				$transaction['unique_id'] = $transactionExportArray[$numFields+1];

				if ($transactionExportArray[0] != null) {
					$transaction['custom_id'] = $transactionExportArray[0];
				}

				if ($transactionExportArray[$numFields] == 'Valide') {
					$transaction['status'] = Oara_Utilities::STATUS_CONFIRMED;
				} else
					if ($transactionExportArray[$numFields] == 'Attente') {
						$transaction['status'] = Oara_Utilities::STATUS_PENDING;
					} else
						if ($transactionExportArray[$numFields] == 'Refusé') {
							$transaction['status'] = Oara_Utilities::STATUS_DECLINED;
						}
				$transaction['amount'] = Oara_Utilities::parseDouble($transactionExportArray[$numFields -2]);
				$transaction['commission'] = Oara_Utilities::parseDouble($transactionExportArray[$numFields -1]);
				$totalTransactions[] = $transaction;
			}
		}
		return $totalTransactions;
	}

}
