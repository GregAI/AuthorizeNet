<?php
ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);
date_default_timezone_set("America/New_York");

function pa($v=""){ echo "<pre>".print_r($v, 1)."</pre>"; }
require_once("authnet-funcs.php");
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;


//Sandbox Testing Only
if (1) {
	
	//Individual Credit Card Test
	$Amount = 1;
	$InvoiceDetails = Array("InvoiceNumber" => rand(1,100000000), "Description" => "Test Order");
	$CreditCard = Array("Number" => "4111111111111111", "ExpYear" => 2019, "ExpMonth" => 10, "CVV2" => "221");
	$BillingAddress = Array("FirstName" => "Test", "LastName" => "User", "Company" => "", "CustomerNumber" => "", "Email" => "test@test.com", "Address" => "123 Street", "City" => "Roswell", "State" => "GA", "Zip" => "30076", "Country" => "USA");
	//pa(AuthNet_ChargeCreditCard($CreditCard, $BillingAddress, $InvoiceDetails, $Amount));
	
	
	$CustomerID = "1557808090"; $PaymentProfileID = "1562924917";
	//pa(AuthNet_CreateCustomerProfile(4, "greg@appliedimagination.com"));
	//pa(AuthNet_GetCustomerProfile($CustomerID));
	//pa(AuthNet_UpdateCustomerProfile($CustomerID, "greg@appliedimagination.com", 2));
	
	//pa(AuthNet_CreateCustomerPaymentProfile($CustomerID, Array("Number" => "", "ExpYear" => 2020, "ExpMonth" => "03", "CVV2" => 195) ));
	//pa(AuthNet_GetCustomerPaymentProfile($CustomerID, $PaymentProfileID));
	//pa(AuthNet_VerifyCustomerPaymentProfile($CustomerID, $PaymentProfileID));
	//pa(AuthNet_UpdateCustomerPaymentProfile($CustomerID, $PaymentProfileID, Array("Number" => "4242424242424242", "ExpYear" => 2037, "ExpMonth" => 12, "CVV2" => 142)));
	//pa(AuthNet_DeleteCustomerPaymentProfile($CustomerID, $PaymentProfileID));
	//pa(AuthNet_DeleteCustomerPaymentProfile($CustomerID, $PaymentProfileID));
	
	$TransactionID = "40922489589";
	//pa(AuthNet_AuthorizeCustomerProfile($CustomerID, $PaymentProfileID, 1.00, Array("duplicateWindow" => 0)));
	//pa(AuthNet_CapturePayment($TransactionID));
	//pa(AuthNet_RefundPayment($TransactionID, 9.99)); //Wait 1 day
}

//Live Transactions Test
if (0)
{
	$CustomerID = "1527405081"; $PaymentProfileID = "1539270694";
	
	//pa(AuthNet_CreateCustomerProfile(1, "greg@appliedimagination.com"));
	if (0)
	{
		$Response = AuthNet_CreateCustomerPaymentProfile($CustomerID, Array("Number" => "", "ExpYear" => 2022, "ExpMonth" => "11", "CVV2" => "") );
		$PaymentProfileID = $Response["Response"];
		pa($Response);
	}
	pa(AuthNet_VerifyCustomerPaymentProfile($CustomerID, $PaymentProfileID));
	pa(AuthNet_DeleteCustomerPaymentProfile($CustomerID, $PaymentProfileID));
	
	//pa(AuthNet_AuthorizeCustomerProfile($CustomerID, $PaymentProfileID, 1.00, Array("duplicateWindow" => 0)));
}








//Create Customer Dup Error
//[Message] => E00039  A duplicate record with ID 1501614987 already exists.

//Create Customer Payment Dup Error
//[Message] => E00039  A duplicate customer payment profile already exists. #1501139918


?>FIN