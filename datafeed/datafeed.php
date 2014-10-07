<?php

/*
 * 
 * This datafeed page for the Magento AsiaPay payment module updates the order status to 'processing', 
 * 
 * sends an email, and creates an invoice automatically if the online transaction is successful.
 * 
 */


// Include Magento application
require_once ( "../app/Mage.php" );
umask(0);

//load Magento application base "default" folder
$app = Mage::app();
Mage::log($_POST,null,'asiapay.log');
//Receive POSTed variables from the gateway
$src = $_POST['src'];
$prc = $_POST['prc'];
$ord = $_POST['Ord'];
$holder = $_POST['Holder'];
$successCode = $_POST['successcode'];
$ref = $_POST['Ref'];
$payRef = $_POST['PayRef'];
$amt = $_POST['Amt'];
$cur = $_POST['Cur'];
$remark = $_POST['remark'];
$authId = $_POST['AuthId'];
$eci = $_POST['eci'];
$payerAuth = $_POST['payerAuth'];
$sourceIp = $_POST['sourceIp'];
$ipCountry = $_POST['ipCountry'];

if(isset($_POST['secureHash'])){
	$secureHash = $_POST['secureHash'];
}else{
	$secureHash = "";
}

//confirmation sent to the gateway to explain that the variables have been sent
echo "OK! " . "Order Ref. No.: ". $ref . " | ";

//explode reference number and get the value only
$flag = preg_match("/-/", $ref);
	
if ($flag == 1){
	$orderId = explode("-",$ref);
	$orderNumber = $orderId[1];
}else{
	$orderNumber = $ref;
}

//Instantiate Mage_Sales_Model_Order class and load the order ID
//Note: increment ID is the system generated number per order by Magento 
$order_object = Mage::getSingleton('sales/order');
$order_object->loadByIncrementId($orderNumber);

////get currency type from Magento's sales order data for this order id (for comparison with the gateway's POSTed currency)
//$dbCurrency = $order_object->getData('order_currency_code');
$dbCurrency = $order_object->getBaseCurrencyCode();

/* convert currency type into numerical ISO code start*/
function getIsoCurrCode($magento_currency_code) {
	switch($magento_currency_code){
	case 'HKD':
		$cur = '344';
		break;
	case 'USD':
		$cur = '840';
		break;
	case 'SGD':
		$cur = '702';
		break;
	case 'CNY':
		$cur = '156';
		break;
	case 'JPY':
		$cur = '392';
		break;		
	case 'TWD':
		$cur = '901';
		break;
	case 'AUD':
		$cur = '036';
		break;
	case 'EUR':
		$cur = '978';
		break;
	case 'GBP':
		$cur = '826';
		break;
	case 'CAD':
		$cur = '124';
		break;
	case 'MOP':
		$cur = '446';
		break;
	case 'PHP':
		$cur = '608';
		break;
	case 'THB':
		$cur = '901';
		break;
	case 'MYR':
		$cur = '458';
		break;
	case 'IDR':
		$cur = '360';
		break;
	case 'KRW':
		$cur = '410';
		break;
	case 'SAR':
		$cur = '682';
		break;
	case 'NZD':
		$cur = '554';
		break;
	case 'AED':
		$cur = '784';
		break;
	case 'BND':
		$cur = '096';
		break;
	default:
		$cur = '344';
	}	
	return $cur;
}
$dbCurrencyIso = getIsoCurrCode($dbCurrency);
/* convert currency type into numerical ISO code end*/
	
//get grand total amount from Magento's sales order data for this order id (for comparison with the gateway's POSTed amount)
//$dbAmount = $order_object->getData('base_grand_total');
$dbAmount = sprintf('%.2f', $order_object->getBaseGrandTotal());


/* secureHash validation start*/ 
function verifyPaymentDatafeed($src, $prc, $successCode, $merchantReferenceNumber, $paydollarReferenceNumber, $currencyCode, $amount, $payerAuthenticationStatus, $secureHashSecret, $secureHash) {
	$buffer = $src . '|' . $prc . '|' . $successCode . '|' . $merchantReferenceNumber . '|' . $paydollarReferenceNumber . '|' . $currencyCode . '|' . $amount . '|' . $payerAuthenticationStatus . '|' . $secureHashSecret;
	$verifyData = sha1($buffer);
	if ($secureHash == $verifyData) { return true; }
	return false;
}
$secureHashSecret = Mage::getStoreConfig('payment/pdcptb/secure_hash_secret');
if(trim($secureHashSecret) != ""){
	if(verifyPaymentDatafeed($src, $prc, $successCode, $ref, $payRef, $cur, $amt, $payerAuth, $secureHashSecret, $secureHash) == false){
		exit("Secure Hash Validation Failed");
	}
}
/* secureHash validation end*/ 



if ($successCode == 0 && $prc == 0 && $src == 0){
	if ($dbAmount == $amt && $dbCurrencyIso == $cur){		
		$error;
		try {	
			//order status is updated
			$comment = "Received through online card payment: " . $dbCurrency . $dbAmount;
			$order_object->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, $comment, 1)->save(); 		
			$order_object->sendOrderUpdateEmail(true, $comment);	//for sending order email update to customer
		
			//invoice is created
			//Instantiate Mage_Sales_Model_Service_Order class and prepare the invoice
			$invoice_object = Mage::getSingleton('sales/service_order', $order_object)->prepareInvoice();
	 
			if (!$invoice_object->getTotalQty()) {
				Mage::throwException(Mage::helper('core')->__('Cannot create an invoice without products.'));
			}
	 					
			$invoice_object->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);
			$invoice_object->register();
			
			$invoice_object->setEmailSent(true);
			$invoice_object->getOrder()->setIsInProcess(true);
			
			
			$payment = Mage::getSingleton('sales/order_payment')
					->setMethod('ppcptb')
					->setTransactionId($payRef)
					->setIsTransactionClosed(true);
        	$order_object->setPayment($payment);
			$payment->addTransaction(Mage_Sales_Model_Order_Payment_Transaction::TYPE_AUTH);
						
			//Instantiate Mage_Core_Model_Resource_Transaction and perform a transaction
			$transaction_object = Mage::getSingleton('core/resource_transaction')
					->addObject($invoice_object)->addObject($invoice_object->getOrder());	 
			$transaction_object->save();
						
			$invoice_object->sendEmail(true, $comment);
		}
		catch (Mage_Core_Exception $e) {
			$error = $e;
			//print_r($e);
			Mage::log($error);
			Mage::logException($e);
		}
		
		if (!$error){
			echo "Order status (processing) update successful";
		}
		
	}else{
		if (($dbAmount != $amt)){  
			echo "Amount value: DB " . (($dbAmount == '') ? 'NULL' : $dbAmount) . " is not equal to POSTed " . $amt . " | ";
			echo "Possible tamper - Update failed";
		}else if (($dbCurrencyIso != $cur)){
			echo "Currency value: DB " . (($dbCurrency == '') ? 'NULL' : $dbCurrency) . " (".dbCurrencyIso.") is not equal to POSTed " . $cur . " | ";
			echo "Possible tamper - Update failed";
		}else{
			echo "Other unknown error - Update failed";
		}
	}
	
}else{
	$comment = "Order cancelled";	
	$order_object->setState(Mage_Sales_Model_Order::STATE_CANCELED, true, $comment, 0)->save();	
	$order_object->cancel()->save();
	echo "Order Status (cancelled) update successful";
	echo "Transaction Rejected / Failed.";
}

?>