<?php
//https://developer.authorize.net/api/reference/index.html#customer-profiles-validate-customer-payment-profile
require dirname(__FILE__).'/authnet/autoload.php';
use net\authorize\api\contract\v1 as AnetAPI;
use net\authorize\api\controller as AnetController;

//SET SANDBOX MODE HERE
define("AUTHORIZENET_SANDBOX", true); //Manually set to false in authnet/lib/shared/AuthorizeNetRequest.php - line 17 - $sandbox var;

$AuthLogMax = 30; $AuthLogFilename = date("Y-n-j").".txt";
$AuthLogPath = dirname(__FILE__)."/authlogs/";

if (!AUTHORIZENET_SANDBOX)
{
	//PRODUCTION MODE
	$AuthNetConfig = Array(
		"LoginID" => "ZZZZZZ",
		"TransactionKey" => "XXXXXXXXXXXXXX",
		"Environment" => \net\authorize\api\constants\ANetEnvironment::PRODUCTION,
	);
} else {
	//SANDBOX MODE
	$AuthNetConfig = Array(
		"LoginID" => "2cPhA3NsG4M",
		"TransactionKey" => "24N686w42FZvMAjY",
		"Environment" => \net\authorize\api\constants\ANetEnvironment::SANDBOX,
	);
}

$merchantAuthentication = new AnetAPI\MerchantAuthenticationType();
$merchantAuthentication->setName($AuthNetConfig["LoginID"]);
$merchantAuthentication->setTransactionKey($AuthNetConfig["TransactionKey"]);


//Create new Customer Profile
function AuthNet_CreateCustomerProfile($ID, $Email)
{
	global $merchantAuthentication, $AuthNetConfig;
	$refId = 'ref' . time();

	$customerProfile = new AnetAPI\CustomerProfileType();
	//$customerProfile->setDescription("Customer 2 Test PHP");
	$customerProfile->setMerchantCustomerId($ID);
	$customerProfile->setEmail($Email);
	//$customerProfile->setpaymentProfiles($paymentProfiles);
	//$customerProfile->setShipToList($shippingProfiles);

	$request = new AnetAPI\CreateCustomerProfileRequest();
	$request->setMerchantAuthentication($merchantAuthentication);
	$request->setRefId($refId);
	$request->setProfile($customerProfile);

	$controller = new AnetController\CreateCustomerProfileController($request);
    $response = $controller->executeWithApiResponse($AuthNetConfig["Environment"]);

    if (($response != null) && ($response->getMessages()->getResultCode() == "Ok"))
    {
    	return AuthNet_ReturnSuccess($response->getCustomerProfileId());
    } else {
    	$errorMessages = $response->getMessages()->getMessage();
    	return AuthNet_ReturnFailure($response, $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText());
    }
}

//Get Customer Profile from ID
function AuthNet_GetCustomerProfile($CustomerID)
{
	global $merchantAuthentication, $AuthNetConfig;
	$refId = 'ref' . time();

	$request = new AnetAPI\GetCustomerProfileRequest();
	$request->setMerchantAuthentication($merchantAuthentication);
	$request->setCustomerProfileId($CustomerID);

	$controller = new AnetController\GetCustomerProfileController($request);
	$response = $controller->executeWithApiResponse($AuthNetConfig["Environment"]);
    if (($response != null) && ($response->getMessages()->getResultCode() == "Ok"))
    {
    	return AuthNet_ReturnSuccess($response->getProfile());
    } else {
    	$errorMessages = $response->getMessages()->getMessage();
    	return AuthNet_ReturnFailure($response, $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText());
    }
}

//Update Customer Profile
function AuthNet_UpdateCustomerProfile($CustomerID, $Email, $ID = 0)
{
	global $merchantAuthentication, $AuthNetConfig;
	$refId = 'ref' . time();

	$profile = new AnetAPI\CustomerProfileExType();
	$profile->setCustomerProfileId($CustomerID);
	$profile->setEmail($Email);
	if ($ID) $profile->setMerchantCustomerId($ID);

	$request = new AnetAPI\UpdateCustomerProfileRequest();
	$request->setMerchantAuthentication($merchantAuthentication);
	$request->setProfile($profile);

	$controller = new AnetController\UpdateCustomerProfileController($request);
	$response = $controller->executeWithApiResponse($AuthNetConfig["Environment"]);
    if (($response != null) && ($response->getMessages()->getResultCode() == "Ok"))
    {
    	return AuthNet_ReturnSuccess(AuthNet_GetCustomerProfile($CustomerID));
    } else {
    	$errorMessages = $response->getMessages()->getMessage();
    	return AuthNet_ReturnFailure($response, $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText());
    }
}



//Create Customer Payment Profile
function AuthNet_CreateCustomerPaymentProfile($CustomerID, $CreditCard = Array(), $BillingAddress = Array())
{
	global $merchantAuthentication, $AuthNetConfig;
	$refId = 'ref' . time();

	$Errors = Array();
	$CreditCard["Number"] = trim($CreditCard["Number"]);
	if (!is_numeric($CreditCard["Number"]) || strlen($CreditCard["Number"]) < 15 || strlen($CreditCard["Number"]) > 16)
		$Errors[] = "Please enter a valid Credit Card Number.";
	if (strlen($CreditCard["CVV2"]) < 3) $Errors[] = "Please enter a valid CCV Code.";


	if (count($Errors) > 0)
	{
		return AuthNet_ReturnFailure($Errors, implode("<br/>", $Errors));
	}


	$creditCard = new AnetAPI\CreditCardType();
	$creditCard->setCardNumber($CreditCard["Number"]);
	if (strlen($CreditCard["ExpYear"]) == 2) $CreditCard["ExpYear"] = "20".$CreditCard["ExpYear"];
	if (strlen($CreditCard["ExpMonth"]) == 1) $CreditCard["ExpMonth"] = "0".$CreditCard["ExpMonth"];
	$creditCard->setExpirationDate($CreditCard["ExpYear"]."-".$CreditCard["ExpMonth"]);
	if (strlen($CreditCard["CVV2"]) > 4) $CreditCard["CVV2"] = substr($CreditCard["CVV2"], 0, 4);
	$creditCard->setCardCode($CreditCard["CVV2"]);
	$paymentCreditCard = new AnetAPI\PaymentType();
	$paymentCreditCard->setCreditCard($creditCard);

	$billto = new AnetAPI\CustomerAddressType();
	$billto->setFirstName($BillingAddress["FirstName"]);
	$billto->setLastName($BillingAddress["LastName"]);
	$billto->setCompany($BillingAddress["Company"]);
	$billto->setAddress($BillingAddress["Address"]);
	$billto->setCity($BillingAddress["City"]);
	$billto->setState($BillingAddress["State"]);
	$billto->setZip($BillingAddress["Zip"]);
	$billto->setCountry($BillingAddress["Country"] ? $BillingAddress["Country"] : "USA");
	$billto->setPhoneNumber($BillingAddress["Phone"]);
	$billto->setfaxNumber($BillingAddress["Fax"]);

	$paymentprofile = new AnetAPI\CustomerPaymentProfileType();
	$paymentprofile->setCustomerType('individual');
	$paymentprofile->setBillTo($billto);
	$paymentprofile->setPayment($paymentCreditCard);
	$paymentprofile->setDefaultPaymentProfile(true);

	$paymentprofilerequest = new AnetAPI\CreateCustomerPaymentProfileRequest();
	$paymentprofilerequest->setMerchantAuthentication($merchantAuthentication);
	$paymentprofilerequest->setCustomerProfileId($CustomerID);
	$paymentprofilerequest->setPaymentProfile($paymentprofile);
	//$paymentprofilerequest->setValidationMode("liveMode");

	$controller = new AnetController\CreateCustomerPaymentProfileController($paymentprofilerequest);
	$response = $controller->executeWithApiResponse($AuthNetConfig["Environment"]);
    if (($response != null) && ($response->getMessages()->getResultCode() == "Ok"))
    {
    	return AuthNet_ReturnSuccess($response->getCustomerPaymentProfileId());
    } else {
    	$errorMessages = $response->getMessages()->getMessage();
    	return AuthNet_ReturnFailure($response, $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText()." #".$response->getCustomerPaymentProfileId());
    }
}

//Get the Customer Payment Profile
function AuthNet_GetCustomerPaymentProfile($CustomerID, $PaymentProfileID)
{
	global $merchantAuthentication, $AuthNetConfig;
	$refId = 'ref' . time();

	$request = new AnetAPI\GetCustomerPaymentProfileRequest();
	$request->setMerchantAuthentication($merchantAuthentication);
	$request->setRefId( $refId);
	$request->setCustomerProfileId($CustomerID);
	$request->setCustomerPaymentProfileId($PaymentProfileID);

	$controller = new AnetController\GetCustomerPaymentProfileController($request);
	$response = $controller->executeWithApiResponse($AuthNetConfig["Environment"]);
    if (($response != null) && ($response->getMessages()->getResultCode() == "Ok"))
    {

    	return AuthNet_ReturnSuccess($response->getPaymentProfile());
    } else {
    	$errorMessages = $response->getMessages()->getMessage();
    	return AuthNet_ReturnFailure($response, $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText());
    }
}

//Verifies a Customer Payment Profile is valid
function AuthNet_VerifyCustomerPaymentProfile($CustomerID, $PaymentProfileID)
{
	global $merchantAuthentication, $AuthNetConfig;
	$refId = 'ref' . time();
	$validationmode = "testMode";

	$request = new AnetAPI\ValidateCustomerPaymentProfileRequest();
	$request->setMerchantAuthentication($merchantAuthentication);
	$request->setCustomerProfileId($CustomerID);
	$request->setCustomerPaymentProfileId($PaymentProfileID);
	$request->setValidationMode($validationmode);

	$controller = new AnetController\ValidateCustomerPaymentProfileController($request);
	$response = $controller->executeWithApiResponse($AuthNetConfig["Environment"]);
    if (($response != null) && ($response->getMessages()->getResultCode() == "Ok"))
    {
    	$validationMessages = $response->getMessages()->getMessage();
    	return AuthNet_ReturnSuccess($validationMessages[0]->getCode(), $validationMessages[0]->getText());
    } else {
    	$errorMessages = $response->getMessages()->getMessage();
    	$Msg = $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText();
    	if (preg_match('#(The credit card number is invalid|The credit card has expired)#i', $Msg, $Match)) $Msg = $Match[1];
    	return AuthNet_ReturnFailure($response, $Msg);
    }
}

//Update Customer Payment Profile
function AuthNet_UpdateCustomerPaymentProfile($CustomerID, $PaymentProfileID, $CreditCard = Array(), $BillingAddress = Array())
{
	global $merchantAuthentication, $AuthNetConfig;
	$refId = 'ref' . time();

	$request = new AnetAPI\UpdateCustomerPaymentProfileRequest();
	$request->setMerchantAuthentication($merchantAuthentication);
	$request->setCustomerProfileId($CustomerID);
	$controller = new AnetController\GetCustomerProfileController($request);

	$creditCard = new AnetAPI\CreditCardType();
	$creditCard->setCardNumber($CreditCard["Number"]);
	if (strlen($CreditCard["ExpYear"]) == 2) $CreditCard["ExpYear"] = "20".$CreditCard["ExpYear"];
	if (strlen($CreditCard["ExpMonth"]) == 1) $CreditCard["ExpMonth"] = "0".$CreditCard["ExpMonth"];
	$creditCard->setExpirationDate($CreditCard["ExpYear"]."-".$CreditCard["ExpMonth"]);
	$creditCard->setCardCode($CreditCard["CVV2"]);
	$paymentCreditCard = new AnetAPI\PaymentType();
	$paymentCreditCard->setCreditCard($creditCard);

	$billto = new AnetAPI\CustomerAddressType();
	$billto->setFirstName($BillingAddress["FirstName"]);
	$billto->setLastName($BillingAddress["LastName"]);
	$billto->setCompany($BillingAddress["Company"]);
	$billto->setAddress($BillingAddress["Address"]);
	$billto->setCity($BillingAddress["City"]);
	$billto->setState($BillingAddress["State"]);
	$billto->setZip($BillingAddress["Zip"]);
	$billto->setCountry($BillingAddress["Country"] ? $BillingAddress["Country"] : "USA");
	$billto->setPhoneNumber($BillingAddress["Phone"]);
	$billto->setfaxNumber($BillingAddress["Fax"]);

	$paymentprofile = new AnetAPI\CustomerPaymentProfileExType();
	$paymentprofile->setCustomerPaymentProfileId($PaymentProfileID);
	$paymentprofile->setBillTo($billto);
	$paymentprofile->setPayment($paymentCreditCard);
	//$paymentprofile->setDefaultPaymentProfile(true);

	$request->setPaymentProfile($paymentprofile);

	$controller = new AnetController\UpdateCustomerPaymentProfileController($request);
	$response = $controller->executeWithApiResponse($AuthNetConfig["Environment"]);
    if (($response != null) && ($response->getMessages()->getResultCode() == "Ok"))
    {
    	return AuthNet_ReturnSuccess("");
    } else {
    	$errorMessages = $response->getMessages()->getMessage();
    	return AuthNet_ReturnFailure($response, $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText());
    }
}

//Deletes the Customer Payment Profile - Should likely not be called
function AuthNet_DeleteCustomerPaymentProfile($CustomerID, $PaymentProfileID)
{
	global $merchantAuthentication, $AuthNetConfig;
	$refId = 'ref' . time();

	$request = new AnetAPI\DeleteCustomerPaymentProfileRequest();
	$request->setMerchantAuthentication($merchantAuthentication);
	$request->setCustomerProfileId($CustomerID);
	$request->setCustomerPaymentProfileId($PaymentProfileID);

	$controller = new AnetController\DeleteCustomerPaymentProfileController($request);
	$response = $controller->executeWithApiResponse($AuthNetConfig["Environment"]);
    if (($response != null) && ($response->getMessages()->getResultCode() == "Ok"))
    {

    	return AuthNet_ReturnSuccess();
    } else {
    	$errorMessages = $response->getMessages()->getMessage();
    	return AuthNet_ReturnFailure($response, $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText());
    }
}


//Authorize a Credit Card - Customer Profile
function AuthNet_AuthorizeCustomerProfile($CustomerID, $PaymentProfileID, $Amount = 0, $Settings = Array())
{
	global $merchantAuthentication, $AuthNetConfig;
	$refId = 'ref' . time();
	//$Amount = 1;

	$profileToCharge = new AnetAPI\CustomerProfilePaymentType();
	$profileToCharge->setCustomerProfileId($CustomerID);
	$paymentProfile = new AnetAPI\PaymentProfileType();
	$paymentProfile->setPaymentProfileId($PaymentProfileID);
	$profileToCharge->setPaymentProfile($paymentProfile);

	$transactionRequestType = new AnetAPI\TransactionRequestType();
	$transactionRequestType->setTransactionType( "authOnlyTransaction");
	$transactionRequestType->setAmount($Amount);
	$transactionRequestType->setProfile($profileToCharge);

	if (count($Settings))
	{
		foreach($Settings AS $Name => $Value)
		{
			$settingType = new AnetAPI\SettingType();
			$settingType->setSettingName($Name);
			$settingType->setSettingValue($Value);
			$transactionRequestType->addToTransactionSettings($settingType);
		}
	}

	$request = new AnetAPI\CreateTransactionRequest();
	$request->setMerchantAuthentication($merchantAuthentication);
	$request->setRefId( $refId);
	$request->setTransactionRequest( $transactionRequestType);

	$controller = new AnetController\CreateTransactionController($request);
	$response = $controller->executeWithApiResponse($AuthNetConfig["Environment"]);
	if (($response != null) && ($response->getMessages()->getResultCode() == "Ok"))
	{
		$tresponse = $response->getTransactionResponse();
		if ($tresponse != null && $tresponse->getMessages() != null)
		{
			return AuthNet_ReturnSuccess(Array(
				"TransactionCode" => $tresponse->getResponseCode(),
				"AuthCode" => $tresponse->getAuthCode(),
				"TransactionID" => $tresponse->getTransId(),
				"Code" => $tresponse->getMessages()[0]->getCode(),
				"Description" => $tresponse->getMessages()[0]->getDescription(),
			));
		} else {
			$rtr = Array();
			if($tresponse->getErrors() != null)
			{
				$rtr["ErrorCode"] = $tresponse->getErrors()[0]->getErrorCode();
				$rtr["ErrorMessage"] = $tresponse->getErrors()[0]->getErrorText();
			} else {
				$errorMessages = $response->getMessages()->getMessage();
				$rtr["ErrorCode"] = $response;
				$rtr["ErrorMessage"] = $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText();
			}
			if ($rtr["ErrorCode"] == 2)
			{
				//pa($tresponse);
				//CAVV Check
				$cavvResultCode = $tresponse->getCavvResultCode();
				if (!in_array($cavvResultCode, Array("2", "8", "A", "B")))
				{
					$rtr["ErrorCode"] = "CVV2-MisMatch";
					$rtr["ErrorMessage"] = "The CVV2 or Expiration Date for the Credit Card was invalid.";
				} else {
					//AVS Check?
					$ErrorCode = $tresponse->getAvsResultCode();
					$rtr["ErrorCode"] .= $ErrorCode;

					switch($ErrorCode)
					{
						default: break;
						case "A": $rtr["ErrorMessage"] = "The street address matched, but the postal code did not."; break;
						case "B": $rtr["ErrorMessage"] = "No address information was provided."; break;
						case "E": $rtr["ErrorMessage"] = "The AVS check returned an error."; break;
						case "G": $rtr["ErrorMessage"] = "The card was issued by a bank outside the U.S. and does not support AVS."; break;
						case "N": $rtr["ErrorMessage"] = "Neither the street address nor postal code matched."; break;
						case "P": $rtr["ErrorMessage"] = "AVS is not applicable for this transaction."; break;
						case "R": $rtr["ErrorMessage"] = "Retry — AVS was unavailable or timed out."; break;
						case "S": $rtr["ErrorMessage"] = "AVS is not supported by card issuer."; break;
						case "U": $rtr["ErrorMessage"] = "Address information is unavailable."; break;
						case "W": $rtr["ErrorMessage"] = "The US ZIP+4 code matches, but the street address does not."; break;
						case "X": $rtr["ErrorMessage"] = "Both the street address and the US ZIP+4 code matched."; break;
						case "Y": $rtr["ErrorMessage"] = "The street address and postal code matched."; break;
						case "Z": $rtr["ErrorMessage"] = "The postal code matched, but the street address did not."; break;
					}
				}
			}

			return AuthNet_ReturnFailure($rtr["ErrorCode"], $rtr["ErrorMessage"]);
		}
    } else {
    	$tresponse = $response->getTransactionResponse();

		$rtr = Array();
		if($tresponse->getErrors() != null)
		{
			$rtr["ErrorCode"] = $tresponse->getErrors()[0]->getErrorCode();
			$rtr["ErrorMessage"] = $tresponse->getErrors()[0]->getErrorText();
		} else {
			$errorMessages = $response->getMessages()->getMessage();
			$rtr["ErrorCode"] = $response;
			$rtr["ErrorMessage"] = $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText();
		}

		return AuthNet_ReturnFailure($rtr["ErrorCode"], $rtr["ErrorMessage"]);
    }
}

//Capture a previously Authorized Payment
function AuthNet_CapturePayment($TransactionID, $Amount = 0)
{
	global $merchantAuthentication, $AuthNetConfig;
	$refId = 'ref' . time();
	//$Amount = 1;

	$transactionRequestType = new AnetAPI\TransactionRequestType();
	if ($Amount) $transactionRequestType->setAmount($Amount);
	$transactionRequestType->setTransactionType("priorAuthCaptureTransaction");
	$transactionRequestType->setRefTransId($TransactionID);

	$request = new AnetAPI\CreateTransactionRequest();
	$request->setMerchantAuthentication($merchantAuthentication);
	$request->setTransactionRequest( $transactionRequestType);

	$controller = new AnetController\CreateTransactionController($request);
	$response = $controller->executeWithApiResponse($AuthNetConfig["Environment"]);
	if (($response != null) && ($response->getMessages()->getResultCode() == "Ok"))
	{
		$tresponse = $response->getTransactionResponse();
		if ($tresponse != null && $tresponse->getMessages() != null)
		{
			return AuthNet_ReturnSuccess(Array(
				"TransactionCode" => $tresponse->getResponseCode(),
				//"AuthCode" => $tresponse->getAuthCode(),
				//"TransactionID" => $tresponse->getTransId(),
				"Code" => $tresponse->getMessages()[0]->getCode(),
				"Description" => $tresponse->getMessages()[0]->getDescription(),
			));
		} else {
			$rtr = Array();
			if($tresponse->getErrors() != null)
			{
				$rtr["ErrorCode"] = $tresponse->getErrors()[0]->getErrorCode();
				$rtr["ErrorMessage"] = $tresponse->getErrors()[0]->getErrorText();
			} else {
				$errorMessages = $response->getMessages()->getMessage();
				$rtr["ErrorCode"] = $response;
				$rtr["ErrorMessage"] = $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText();
			}

			return AuthNet_ReturnFailure($rtr["ErrorCode"], $rtr["ErrorMessage"]);
		}
    } else {
    	$tresponse = $response->getTransactionResponse();

		$rtr = Array();
		if($tresponse->getErrors() != null)
		{
			$rtr["ErrorCode"] = $tresponse->getErrors()[0]->getErrorCode();
			$rtr["ErrorMessage"] = $tresponse->getErrors()[0]->getErrorText();
		} else {
			$errorMessages = $response->getMessages()->getMessage();
			$rtr["ErrorCode"] = $response;
			$rtr["ErrorMessage"] = $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText();
		}

		return AuthNet_ReturnFailure($rtr["ErrorCode"], $rtr["ErrorMessage"]);
    }
}

//Refund a previously Captured Payment
function AuthNet_RefundPayment($TransactionID, $Amount = 0)
{
	global $merchantAuthentication, $AuthNetConfig;
	$refId = 'ref' . time();

	$TransactionDetails = getTransactionDetails($TransactionID);

	$Payment = $TransactionDetails["Response"]->getPayment();
	$creditCard = new AnetAPI\CreditCardType();
    $creditCard->setCardNumber(substr($Payment->getCreditCard()->getCardNumber(), -4, 4));
    $creditCard->setExpirationDate("XXXX");
    $paymentOne = new AnetAPI\PaymentType();
    $paymentOne->setCreditCard($creditCard);

	$transactionRequestType = new AnetAPI\TransactionRequestType();
	if ($Amount) $transactionRequestType->setAmount($Amount);
	$transactionRequestType->setTransactionType("refundTransaction");
	$transactionRequestType->setRefTransId($TransactionID);
	$transactionRequestType->setPayment($paymentOne);

	$request = new AnetAPI\CreateTransactionRequest();
	$request->setMerchantAuthentication($merchantAuthentication);
	$request->setTransactionRequest( $transactionRequestType);

	$controller = new AnetController\CreateTransactionController($request);
	$response = $controller->executeWithApiResponse($AuthNetConfig["Environment"]);
	if (($response != null) && ($response->getMessages()->getResultCode() == "Ok"))
	{
		$tresponse = $response->getTransactionResponse();
		if ($tresponse != null && $tresponse->getMessages() != null)
		{
			return AuthNet_ReturnSuccess(Array(
				"TransactionCode" => $tresponse->getResponseCode(),
				//"AuthCode" => $tresponse->getAuthCode(),
				//"TransactionID" => $tresponse->getTransId(),
				"Code" => $tresponse->getMessages()[0]->getCode(),
				"Description" => $tresponse->getMessages()[0]->getDescription(),
			));
		} else {
			$rtr = Array();
			if($tresponse->getErrors() != null)
			{
				$rtr["ErrorCode"] = $tresponse->getErrors()[0]->getErrorCode();
				$rtr["ErrorMessage"] = $tresponse->getErrors()[0]->getErrorText();
			} else {
				$errorMessages = $response->getMessages()->getMessage();
				$rtr["ErrorCode"] = $response;
				$rtr["ErrorMessage"] = $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText();
			}

			return AuthNet_ReturnFailure($rtr["ErrorCode"], $rtr["ErrorMessage"]);
		}
    } else {
    	$tresponse = $response->getTransactionResponse();

		$rtr = Array();
		if($tresponse && $tresponse->getErrors() != null)
		{
			$rtr["ErrorCode"] = $tresponse->getErrors()[0]->getErrorCode();
			$rtr["ErrorMessage"] = $tresponse->getErrors()[0]->getErrorText();
		} else {
			$errorMessages = $response->getMessages()->getMessage();
			$rtr["ErrorCode"] = $response;
			$rtr["ErrorMessage"] = $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText();
		}

		return AuthNet_ReturnFailure($rtr["ErrorCode"], $rtr["ErrorMessage"]);
    }
}

//Charge a Credit Card without needing a Customer/Payment ID
function AuthNet_ChargeCreditCard($CreditCard, $BillingAddress, $InvoiceDetails, $Amount)
{
	global $merchantAuthentication, $AuthNetConfig;
    $refId = 'ref' . time();

	$Errors = Array();
	$CreditCard["Number"] = trim($CreditCard["Number"]);
	if (!is_numeric($CreditCard["Number"]) || strlen($CreditCard["Number"]) < 15 || strlen($CreditCard["Number"]) > 16)
		$Errors[] = "Please enter a valid Credit Card Number.";
	if (strlen($CreditCard["CVV2"]) < 3) $Errors[] = "Please enter a valid CCV Code.";
	
	if (count($Errors) > 0)
	{
		return AuthNet_ReturnFailure($Errors, implode("<br/>", $Errors));
	}
	
	
	//Create Credit Card Object
	$creditCard = new AnetAPI\CreditCardType();
	$creditCard->setCardNumber($CreditCard["Number"]);
	if (strlen($CreditCard["ExpYear"]) == 2) $CreditCard["ExpYear"] = "20".$CreditCard["ExpYear"];
	if (strlen($CreditCard["ExpMonth"]) == 1) $CreditCard["ExpMonth"] = "0".$CreditCard["ExpMonth"];
	$creditCard->setExpirationDate($CreditCard["ExpYear"]."-".$CreditCard["ExpMonth"]);
	if (strlen($CreditCard["CVV2"]) > 4) $CreditCard["CVV2"] = substr($CreditCard["CVV2"], 0, 4);
	$creditCard->setCardCode($CreditCard["CVV2"]);

    // Add the payment data to a paymentType object
    $paymentOne = new AnetAPI\PaymentType();
    $paymentOne->setCreditCard($creditCard);

    // Create order information
    $order = new AnetAPI\OrderType();
    $order->setInvoiceNumber($InvoiceDetails["InvoiceNumber"]);
    $order->setDescription($InvoiceDetails["Description"]);

    // Set the customer's Bill To address
    $customerAddress = new AnetAPI\CustomerAddressType();
    $customerAddress->setFirstName($BillingAddress["FirstName"]);
    $customerAddress->setLastName($BillingAddress["LastName"]);
    $customerAddress->setCompany($BillingAddress["Company"]);
    $customerAddress->setAddress($BillingAddress["Address"]);
    $customerAddress->setCity($BillingAddress["City"]);
    $customerAddress->setState($BillingAddress["State"]);
    $customerAddress->setZip($BillingAddress["Zip"]);
    $customerAddress->setCountry($BillingAddress["Country"]);

    // Set the customer's identifying information
    $customerData = new AnetAPI\CustomerDataType();
    $customerData->setType("individual");
    $customerData->setId($BillingAddress["CustomerNumber"]);
    $customerData->setEmail($BillingAddress["Email"]);

    // Add values for transaction settings
    $duplicateWindowSetting = new AnetAPI\SettingType();
    $duplicateWindowSetting->setSettingName("duplicateWindow");
    $duplicateWindowSetting->setSettingValue("15");

    // Create a TransactionRequestType object and add the previous objects to it
    $transactionRequestType = new AnetAPI\TransactionRequestType();
    $transactionRequestType->setTransactionType("authCaptureTransaction");
    $transactionRequestType->setAmount($Amount);
    $transactionRequestType->setOrder($order);
    $transactionRequestType->setPayment($paymentOne);
    $transactionRequestType->setBillTo($customerAddress);
    $transactionRequestType->setCustomer($customerData);
    $transactionRequestType->addToTransactionSettings($duplicateWindowSetting);

    // Assemble the complete transaction request
    $request = new AnetAPI\CreateTransactionRequest();
    $request->setMerchantAuthentication($merchantAuthentication);
    $request->setRefId($refId);
    $request->setTransactionRequest($transactionRequestType);

    // Create the controller and get the response
    try
    {
	    $controller = new AnetController\CreateTransactionController($request);
	    $response = $controller->executeWithApiResponse($AuthNetConfig["Environment"]);
    } catch(exception $e) {
    	$Errors = [$e->getMessage()];
    	return AuthNet_ReturnFailure($Errors, implode("<br/>", $Errors));
    }
    if ($response != null) {
        // Check to see if the API request was successfully received and acted upon
        if ($response->getMessages()->getResultCode() == "Ok") {
            // Since the API request was successful, look for a transaction response
            // and parse it to display the results of authorizing the card
            $tresponse = $response->getTransactionResponse();
        	
            if ($tresponse != null && $tresponse->getMessages() != null) {
            	return AuthNet_ReturnSuccess(Array(
            			"TransactionCode" => $tresponse->getTransId(),
						"AuthCode" => $tresponse->getAuthCode(),
						"TransactionID" => $tresponse->getTransId(),
            			"Code" => $tresponse->getMessages()[0]->getCode(),
            			"Description" => $tresponse->getMessages()[0]->getDescription(),
            		));
            } else {
            	$rtr = Array();
            	$rtr["ErrorCode"] = $tresponse->getErrors()[0]->getErrorCode();
            	$rtr["ErrorMessage"] = $tresponse->getErrors()[0]->getErrorText();
            	
            	return AuthNet_ReturnFailure($rtr["ErrorCode"], $rtr["ErrorMessage"]);
            }
            // Or, print errors if the API request wasn't successful
        } else {
        	$rtr = Array();
            $tresponse = $response->getTransactionResponse();
        
            if ($tresponse != null && $tresponse->getErrors() != null) {
                $rtr["ErrorCode"] = $tresponse->getErrors()[0]->getErrorCode();
                $rtr["ErrorMessage"] = $tresponse->getErrors()[0]->getErrorText();
            } else {
                $rtr["ErrorCode"] = $response->getMessages()->getMessage()[0]->getCode();
                $rtr["ErrorMessage"] = $response->getMessages()->getMessage()[0]->getText();
            }
            
            return AuthNet_ReturnFailure($rtr["ErrorCode"], $rtr["ErrorMessage"]);
        }
    } else {
    	
    	return AuthNet_ReturnFailure("", "No Transaction Response Returned");
    }

    return $response;
}


//Get the Transaction Details
function getTransactionDetails($TransactionID)
{
	global $merchantAuthentication, $AuthNetConfig;
	$refId = 'ref' . time();

	$request = new AnetAPI\GetTransactionDetailsRequest();
	$request->setMerchantAuthentication($merchantAuthentication);
	$request->setTransId($TransactionID);

	$controller = new AnetController\GetTransactionDetailsController($request);
	$response = $controller->executeWithApiResponse($AuthNetConfig["Environment"]);
	if (($response != null) && ($response->getMessages()->getResultCode() == "Ok"))
	{
		return AuthNet_ReturnSuccess($response->getTransaction());
	} else {
    	$tresponse = $response->getTransactionResponse();

		$rtr = Array();
		if($tresponse->getErrors() != null)
		{
			$rtr["ErrorCode"] = $tresponse->getErrors()[0]->getErrorCode();
			$rtr["ErrorMessage"] = $tresponse->getErrors()[0]->getErrorText();
		} else {
			$errorMessages = $response->getMessages()->getMessage();
			$rtr["ErrorCode"] = $response;
			$rtr["ErrorMessage"] = $errorMessages[0]->getCode() . "  " .$errorMessages[0]->getText();
		}

		return AuthNet_ReturnFailure($rtr["ErrorCode"], $rtr["ErrorMessage"]);
	}
}




//Auth Net Returns
function AuthNet_ReturnSuccess($Response = "", $Message = "")
{
	return AuthNet_Return("Success", $Response, $Message);
}
function AuthNet_ReturnFailure($Response = "", $Message = "")
{
	global $AuthLogMax, $AuthLogFilename, $AuthLogPath;
	$Backtrace = debug_backtrace();//DEBUG_BACKTRACE_IGNORE_ARGS
	$Backtrace = $Backtrace[1];
	
	$LogMsg = date("n/j/Y g:i a")." | ".$Backtrace["function"]." | ".$Backtrace["file"]." - ".$Backtrace["line"]."\r\n";
	$LogMsg .= "----------------------------------------\r\n\r\n";
	$LogMsg .= print_r($Backtrace["args"], 1)."\r\n\r\n";
	if (isset($_SESSION)) $LogMsg .= print_r($_SESSION, 1)."\r\n\r\n";
	$LogMsg .= print_r($Response, 1)."\r\n\r\n\r\n";
	$LogMsg .= "========================================\r\n\r\n\r\n";
	
	@mkdir($AuthLogPath, 0755, true);
	$fp = @fopen($AuthLogPath.$AuthLogFilename, 'a+');
	if ($fp)
	{
		@fwrite($fp, $LogMsg);
		@fclose($fp);
	} else {
		$Message .= "\r\nError Log Not Writable";
	}
	
	$files = @scandir($AuthLogPath);
	if (!is_array($files)) $files = Array();
	foreach($files AS $file)
	{
		if (!preg_match('#\.txt$#i', $file)) continue;
		
		if (strtotime(preg_replace('#\.txt#i', '', $file)) < mktime(0, 0, 0, date("n"), date("j")-$AuthLogMax)) @unlink($AuthLogPath.$file);
	}
	
	return AuthNet_Return("Failure", $Response, $Message);
}
function AuthNet_Return($Type = "Success", $Response = "", $Message = "")
{
	return Array("Status" => $Type, "Response" => $Response, "Message" => $Message);
}

?>