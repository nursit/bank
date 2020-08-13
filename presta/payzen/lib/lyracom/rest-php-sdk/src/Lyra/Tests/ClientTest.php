<?php
namespace Lyra\Tests;

use PHPUnit_Framework_TestCase;
use Lyra\Client;
use Lyra\Constants;

/**
 * ./vendor/bin/phpunit src/Lyra/Tests/ClientTest.php
 */
class ClientTest extends PHPUnit_Framework_TestCase
{
    private function getCredentials()
    {
        $credentials = array();
        $credentials["username"] = "69876357";
        $credentials["password"] = "testpassword_DEMOPRIVATEKEY23G4475zXZQ2UA5x7M";
        $credentials["endpoint"] = "https://api.payzen.eu";
        $credentials["publicKey"] = "69876357:testpublickey_DEMOPUBLICKEY95me92597fd28tGD4r5";
        $credentials["sha256Key"] = "38453613e7f44dc58732bad3dca2bca3";

        return $credentials;
    }

    private function fakePostData($hashKey, $escaped=FALSE)
    {
        if ($hashKey == "sha256_hmac_php53") {
            $_POST['kr-hash'] = "4d57a308d7d8a89a989e8dc54613fe5f7d353a6c749a9769f5e9b9073d5720af";
            $_POST['kr-hash-key'] = "sha256_hmac";
            $_POST['kr-hash-algorithm'] = "sha256_hmac";
            $_POST['kr-answer-type'] = "V4/Payment";
            $_POST['kr-answer'] = '{"shopId":"69876357","orderCycle":"CLOSED","orderStatus":"PAID","serverDate":"2018-12-11T19:03:46+00:00","orderDetails":{"orderTotalAmount":250,"orderCurrency":"EUR","mode":"TEST","orderId":null,"_type":"V4\/OrderDetails"},"customer":{"billingDetails":{"address":null,"category":null,"cellPhoneNumber":null,"city":null,"country":null,"district":null,"firstName":null,"identityCode":null,"language":"EN","lastName":null,"phoneNumber":null,"state":null,"streetNumber":null,"title":null,"zipCode":null,"_type":"V4\/Customer\/BillingDetails"},"email":"sample@example.com","reference":null,"shippingDetails":{"address":null,"address2":null,"category":null,"city":null,"country":null,"deliveryCompanyName":null,"district":null,"firstName":null,"identityCode":null,"lastName":null,"legalName":null,"phoneNumber":null,"shippingMethod":null,"shippingSpeed":null,"state":null,"streetNumber":null,"zipCode":null,"_type":"V4\/Customer\/ShippingDetails"},"extraDetails":{"browserAccept":null,"fingerPrintId":null,"ipAddress":"90.71.64.161","browserUserAgent":"Mozilla\/5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit\/537.36 (KHTML, like Gecko) Chrome\/70.0.3538.110 Safari\/537.36","_type":"V4\/Customer\/ExtraDetails"},"shoppingCart":{"insuranceAmount":null,"shippingAmount":null,"taxAmount":null,"cartItemInfo":null,"_type":"V4\/Customer\/ShoppingCart"},"_type":"V4\/Customer\/Customer"},"transactions":[{"shopId":"69876357","uuid":"c97b7e6f3dbc4c239baf283654f34710","amount":250,"currency":"EUR","paymentMethodType":"CARD","paymentMethodToken":null,"status":"PAID","detailedStatus":"AUTHORISED","operationType":"DEBIT","effectiveStrongAuthentication":"DISABLED","creationDate":"2018-12-11T19:03:45+00:00","errorCode":null,"errorMessage":null,"detailedErrorCode":null,"detailedErrorMessage":null,"metadata":null,"transactionDetails":{"liabilityShift":"NO","effectiveAmount":250,"effectiveCurrency":"EUR","creationContext":"CHARGE","cardDetails":{"paymentSource":"EC","manualValidation":"NO","expectedCaptureDate":"2018-12-11T19:03:45+00:00","effectiveBrand":"CB","pan":"497010XXXXXX0055","expiryMonth":11,"expiryYear":2021,"country":"FR","emisorCode":null,"effectiveProductCode":"F","legacyTransId":"908733","legacyTransDate":"2018-12-11T19:03:38+00:00","paymentMethodSource":"NEW","authorizationResponse":{"amount":250,"currency":"EUR","authorizationDate":"2018-12-11T19:03:45+00:00","authorizationNumber":"3fbbc6","authorizationResult":"0","authorizationMode":"FULL","_type":"V4\/PaymentMethod\/Details\/Cards\/CardAuthorizationResponse"},"captureResponse":{"refundAmount":null,"captureDate":null,"captureFileNumber":null,"refundCurrency":null,"_type":"V4\/PaymentMethod\/Details\/Cards\/CardCaptureResponse"},"threeDSResponse":{"authenticationResultData":{"transactionCondition":"COND_3D_ERROR","enrolled":"UNKNOWN","status":"UNKNOWN","eci":null,"xid":null,"cavvAlgorithm":null,"cavv":null,"signValid":null,"brand":"VISA","_type":"V4\/PaymentMethod\/Details\/Cards\/CardAuthenticationResponse"},"_type":"V4\/PaymentMethod\/Details\/Cards\/ThreeDSResponse"},"markAuthorizationResponse":{"amount":null,"currency":null,"authorizationDate":null,"authorizationNumber":null,"authorizationResult":null,"_type":"V4\/PaymentMethod\/Details\/Cards\/MarkAuthorizationResponse"},"_type":"V4\/PaymentMethod\/Details\/CardDetails"},"parentTransactionUuid":null,"mid":"6969696","sequenceNumber":1,"additionalFields":{"installmentNumber":null,"_type":"V4\/PaymentMethod\/Details\/AdditionalFields"},"_type":"V4\/TransactionDetails"},"_type":"V4\/PaymentTransaction"}],"_type":"V4\/Payment"}';
        } elseif ($hashKey == "sha256_hmac") {
            $_POST['kr-hash'] = "4d57a308d7d8a89a989e8dc54613fe5f7d353a6c749a9769f5e9b9073d5720af";
            $_POST['kr-hash-key'] = "sha256_hmac";
            $_POST['kr-hash-algorithm'] = "sha256_hmac";
            $_POST['kr-answer-type'] = "V4/Payment";
            $_POST['kr-answer'] = '{"shopId":"69876357","orderCycle":"CLOSED","orderStatus":"PAID","serverDate":"2018-12-11T19:03:46+00:00","orderDetails":{"orderTotalAmount":250,"orderCurrency":"EUR","mode":"TEST","orderId":null,"_type":"V4/OrderDetails"},"customer":{"billingDetails":{"address":null,"category":null,"cellPhoneNumber":null,"city":null,"country":null,"district":null,"firstName":null,"identityCode":null,"language":"EN","lastName":null,"phoneNumber":null,"state":null,"streetNumber":null,"title":null,"zipCode":null,"_type":"V4/Customer/BillingDetails"},"email":"sample@example.com","reference":null,"shippingDetails":{"address":null,"address2":null,"category":null,"city":null,"country":null,"deliveryCompanyName":null,"district":null,"firstName":null,"identityCode":null,"lastName":null,"legalName":null,"phoneNumber":null,"shippingMethod":null,"shippingSpeed":null,"state":null,"streetNumber":null,"zipCode":null,"_type":"V4/Customer/ShippingDetails"},"extraDetails":{"browserAccept":null,"fingerPrintId":null,"ipAddress":"90.71.64.161","browserUserAgent":"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.110 Safari/537.36","_type":"V4/Customer/ExtraDetails"},"shoppingCart":{"insuranceAmount":null,"shippingAmount":null,"taxAmount":null,"cartItemInfo":null,"_type":"V4/Customer/ShoppingCart"},"_type":"V4/Customer/Customer"},"transactions":[{"shopId":"69876357","uuid":"c97b7e6f3dbc4c239baf283654f34710","amount":250,"currency":"EUR","paymentMethodType":"CARD","paymentMethodToken":null,"status":"PAID","detailedStatus":"AUTHORISED","operationType":"DEBIT","effectiveStrongAuthentication":"DISABLED","creationDate":"2018-12-11T19:03:45+00:00","errorCode":null,"errorMessage":null,"detailedErrorCode":null,"detailedErrorMessage":null,"metadata":null,"transactionDetails":{"liabilityShift":"NO","effectiveAmount":250,"effectiveCurrency":"EUR","creationContext":"CHARGE","cardDetails":{"paymentSource":"EC","manualValidation":"NO","expectedCaptureDate":"2018-12-11T19:03:45+00:00","effectiveBrand":"CB","pan":"497010XXXXXX0055","expiryMonth":11,"expiryYear":2021,"country":"FR","emisorCode":null,"effectiveProductCode":"F","legacyTransId":"908733","legacyTransDate":"2018-12-11T19:03:38+00:00","paymentMethodSource":"NEW","authorizationResponse":{"amount":250,"currency":"EUR","authorizationDate":"2018-12-11T19:03:45+00:00","authorizationNumber":"3fbbc6","authorizationResult":"0","authorizationMode":"FULL","_type":"V4/PaymentMethod/Details/Cards/CardAuthorizationResponse"},"captureResponse":{"refundAmount":null,"captureDate":null,"captureFileNumber":null,"refundCurrency":null,"_type":"V4/PaymentMethod/Details/Cards/CardCaptureResponse"},"threeDSResponse":{"authenticationResultData":{"transactionCondition":"COND_3D_ERROR","enrolled":"UNKNOWN","status":"UNKNOWN","eci":null,"xid":null,"cavvAlgorithm":null,"cavv":null,"signValid":null,"brand":"VISA","_type":"V4/PaymentMethod/Details/Cards/CardAuthenticationResponse"},"_type":"V4/PaymentMethod/Details/Cards/ThreeDSResponse"},"markAuthorizationResponse":{"amount":null,"currency":null,"authorizationDate":null,"authorizationNumber":null,"authorizationResult":null,"_type":"V4/PaymentMethod/Details/Cards/MarkAuthorizationResponse"},"_type":"V4/PaymentMethod/Details/CardDetails"},"parentTransactionUuid":null,"mid":"6969696","sequenceNumber":1,"additionalFields":{"installmentNumber":null,"_type":"V4/PaymentMethod/Details/AdditionalFields"},"_type":"V4/TransactionDetails"},"_type":"V4/PaymentTransaction"}],"_type":"V4/Payment"}';
        } elseif ($hashKey == "password") {
            $_POST['kr-hash'] = "88fb7b4838a73c2674ca00ee545454d78dd4b1b733a07c855a1d4460d4417f85";
            $_POST['kr-hash-key'] = "password";
            $_POST['kr-hash-algorithm'] = "sha256_hmac";
            $_POST['kr-answer-type'] = "V4/Payment";
            $_POST['kr-answer'] = '{"shopId":"69876357","orderCycle":"CLOSED","orderStatus":"PAID","serverDate":"2018-12-11T19:09:32+00:00","orderDetails":{"orderTotalAmount":990,"orderCurrency":"EUR","mode":"TEST","orderId":"myOrderId-618776","_type":"V4/OrderDetails"},"customer":{"billingDetails":{"address":null,"category":null,"cellPhoneNumber":null,"city":null,"country":null,"district":null,"firstName":null,"identityCode":null,"language":"FR","lastName":null,"phoneNumber":null,"state":null,"streetNumber":null,"title":null,"zipCode":null,"_type":"V4/Customer/BillingDetails"},"email":"sample@example.com","reference":null,"shippingDetails":{"address":null,"address2":null,"category":null,"city":null,"country":null,"deliveryCompanyName":null,"district":null,"firstName":null,"identityCode":null,"lastName":null,"legalName":null,"phoneNumber":null,"shippingMethod":null,"shippingSpeed":null,"state":null,"streetNumber":null,"zipCode":null,"_type":"V4/Customer/ShippingDetails"},"extraDetails":{"browserAccept":null,"fingerPrintId":null,"ipAddress":"90.71.64.161","browserUserAgent":"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_13_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/70.0.3538.110 Safari/537.36","_type":"V4/Customer/ExtraDetails"},"shoppingCart":{"insuranceAmount":null,"shippingAmount":null,"taxAmount":null,"cartItemInfo":null,"_type":"V4/Customer/ShoppingCart"},"_type":"V4/Customer/Customer"},"transactions":[{"shopId":"69876357","uuid":"017574bda28e4f1d8413b97fc65c8197","amount":990,"currency":"EUR","paymentMethodType":"CARD","paymentMethodToken":null,"status":"PAID","detailedStatus":"AUTHORISED","operationType":"DEBIT","effectiveStrongAuthentication":"DISABLED","creationDate":"2018-12-11T19:09:32+00:00","errorCode":null,"errorMessage":null,"detailedErrorCode":null,"detailedErrorMessage":null,"metadata":null,"transactionDetails":{"liabilityShift":"NO","effectiveAmount":990,"effectiveCurrency":"EUR","creationContext":"CHARGE","cardDetails":{"paymentSource":"EC","manualValidation":"NO","expectedCaptureDate":"2018-12-11T19:09:32+00:00","effectiveBrand":"CB","pan":"497010XXXXXX0055","expiryMonth":11,"expiryYear":2021,"country":"FR","emisorCode":null,"effectiveProductCode":"F","legacyTransId":"908967","legacyTransDate":"2018-12-11T19:09:26+00:00","paymentMethodSource":"NEW","authorizationResponse":{"amount":990,"currency":"EUR","authorizationDate":"2018-12-11T19:09:32+00:00","authorizationNumber":"3fd0e3","authorizationResult":"0","authorizationMode":"FULL","_type":"V4/PaymentMethod/Details/Cards/CardAuthorizationResponse"},"captureResponse":{"refundAmount":null,"captureDate":null,"captureFileNumber":null,"refundCurrency":null,"_type":"V4/PaymentMethod/Details/Cards/CardCaptureResponse"},"threeDSResponse":{"authenticationResultData":{"transactionCondition":"COND_3D_ERROR","enrolled":"UNKNOWN","status":"UNKNOWN","eci":null,"xid":null,"cavvAlgorithm":null,"cavv":null,"signValid":null,"brand":"VISA","_type":"V4/PaymentMethod/Details/Cards/CardAuthenticationResponse"},"_type":"V4/PaymentMethod/Details/Cards/ThreeDSResponse"},"markAuthorizationResponse":{"amount":null,"currency":null,"authorizationDate":null,"authorizationNumber":null,"authorizationResult":null,"_type":"V4/PaymentMethod/Details/Cards/MarkAuthorizationResponse"},"_type":"V4/PaymentMethod/Details/CardDetails"},"fraudManagement":null,"parentTransactionUuid":null,"mid":"6969696","sequenceNumber":1,"additionalFields":{"installmentNumber":null,"_type":"V4/PaymentMethod/Details/AdditionalFields"},"_type":"V4/TransactionDetails"},"_type":"V4/PaymentTransaction"}],"_type":"V4/Payment"}';
        }

        if (!is_null($escaped)) $_POST['kr-answer'] = str_replace('"', '\"', $_POST['kr-answer']);
    }

    /**
     * ./vendor/bin/phpunit --filter testClientValidCall src/Lyra/Tests/ClientTest.php
     */
    public function testClientValidCall()
    {
        $credentials = $this->getCredentials();
        $store = array("value" => "sdk test string value");
        
        $client = new Client();
        $client->setUsername($credentials['username']);
        $client->setPassword($credentials['password']);
        $client->setEndpoint($credentials['endpoint']);
        $response = $client->post('V4/Charge/SDKTest', $store);

        $this->assertEquals("SUCCESS", $response["status"]);
        $this->assertEquals("V4", $response["version"]);
        $this->assertEquals($store["value"], $response["answer"]["value"]);
    }

    /**
     * ./vendor/bin/phpunit --filter testClientUsernamePasswordValidCall src/Lyra/Tests/ClientTest.php
     */
    public function testClientUsernamePasswordValidCall()
    {
        $client = new Client();
        $credentials = $this->getCredentials();
        $store = array("value" => "sdk test string value");

        $client->setUsername($credentials['username']);
        $client->setPassword($credentials['password']);
        $client->setEndpoint($credentials['endpoint']);
        $response = $client->post('V4/Charge/SDKTest', $store);

        $this->assertEquals("SUCCESS", $response["status"]);
        $this->assertEquals($store["value"], $response["answer"]["value"]);
    }

    /**
     * ./vendor/bin/phpunit --filter testFileGetContentsPrivateKeyClientValidCall src/Lyra/Tests/ClientTest.php
     */
    public function testFileGetContentsPrivateKeyClientValidCall()
    {
        $credentials = $this->getCredentials();
        $client = new Client();
        $store = array("value" => "sdk test string value");

        $client->setUsername($credentials['username']);
        $client->setPassword($credentials['password']);
        $client->setEndpoint($credentials['endpoint']);
        $response = $client->postWithFileGetContents('V4/Charge/SDKTest', $store);

        $this->assertEquals("SUCCESS", $response["status"]);
        $this->assertEquals($store["value"], $response["answer"]["value"]);
    }

    /**
     * ./vendor/bin/phpunit --filter testFileGetContentsClientValidCall src/Lyra/Tests/ClientTest.php
     */
    public function testFileGetContentsClientValidCall()
    {
        $credentials = $this->getCredentials();
        $client = new Client();
        $store = array("value" => "sdk test string value");

        $client->setUsername($credentials['username']);
        $client->setPassword($credentials['password']);
        $client->setEndpoint($credentials['endpoint']);
        $response = $client->postWithFileGetContents('V4/Charge/SDKTest', $store);

        $this->assertEquals("SUCCESS", $response["status"]);
        $this->assertEquals($store["value"], $response["answer"]["value"]);
    }

    /**
     * ./vendor/bin/phpunit --filter testDoubleSlash src/Lyra/Tests/ClientTest.php
     */
    public function testDoubleSlash()
    {
        $credentials = $this->getCredentials();
        $store = array("value" => "sdk test string value");
        
        $client = new Client();
        $client->setUsername($credentials['username']);
        $client->setPassword($credentials['password']);
        $client->setEndpoint($credentials['endpoint']);
        $response = $client->post('V4/Charge/SDKTest', $store);

        $this->assertEquals("SUCCESS", $response["status"]);
        $this->assertEquals($store["value"], $response["answer"]["value"]);
    }

    /**
     * ./vendor/bin/phpunit --filter testGetUrlFromTarget src/Lyra/Tests/ClientTest.php
     */
    public function testGetUrlFromTarget()
    {
        $credentials = $this->getCredentials();
        $store = array("value" => "sdk test string value");
        
        $client = new Client();
        $client->setUsername($credentials['username']);
        $client->setPassword($credentials['password']);

        $client->setEndpoint($credentials['endpoint']);
        $this->assertEquals($credentials['endpoint'] . "/api-payment/V4/Charge/Get", $client->getUrlFromTarget("V4/Charge/Get"));

        $client->setEndpoint($credentials['endpoint']);
        $this->assertEquals($credentials['endpoint'] . "/api-payment/V4/Charge/Get", $client->getUrlFromTarget("V4/Charge/Get"));
    }

    /**
     * ./vendor/bin/phpunit --filter testNoPrivateKey src/Lyra/Tests/ClientTest.php
     *
     * @expectedException Lyra\Exceptions\LyraException
     */
    public function testNoPrivateKey()
    {
        $client = new Client();
        $client->post("A", array());
    }

    /**
     * ./vendor/bin/phpunit --filter testNoUsername src/Lyra/Tests/ClientTest.php
     *
     * @expectedException Lyra\Exceptions\LyraException
     */
    public function testNoUsername()
    {
        $client = new Client();
        $credentials = $this->getCredentials();

        $client->setPassword($credentials['password']);
        $client->post("A", array());
    }

    /**
     * ./vendor/bin/phpunit --filter testNoPassword src/Lyra/Tests/ClientTest.php
     *
     * @expectedException Lyra\Exceptions\LyraException
     */
    public function testNoPassword()
    {
        $client = new Client();
        $credentials = $this->getCredentials();

        $client->setUsername($credentials['username']);
        $client->post("A", array());
    }

    /**
     * ./vendor/bin/phpunit --filter testNoEndpoint src/Lyra/Tests/ClientTest.php
     *
     * @expectedException Lyra\Exceptions\LyraException
     */
    public function testNoEndpoint()
    {
        $client = new Client();
        $credentials = $this->getCredentials();

        $client->setUsername("A");
        $client->setPassword("B");
        $client->post("A", array());
    }

    /**
     * ./vendor/bin/phpunit --filter testInvalidKey src/Lyra/Tests/ClientTest.php
     */
    public function testInvalidKey()
    {
        $credentials = $this->getCredentials();
        $store = array("value" => "sdk test string value");
        
        $client = new Client();
        $client->setUsername($credentials['username']);
        $client->setPassword("69876357:testprivatekey_FAKE");
        $client->setEndpoint($credentials['endpoint']);
        $response = $client->post('V4/Charge/SDKTest', $store);

        $this->assertEquals("ERROR", $response["status"]);
        $this->assertEquals("INT_905", $response["answer"]["errorCode"]);
    }

    /**
     * ./vendor/bin/phpunit --filter testFileGetContentsInvalidKey src/Lyra/Tests/ClientTest.php
     */
    public function testFileGetContentsInvalidKey()
    {
        $credentials = $this->getCredentials();
        $store = array("value" => "sdk test string value");
        
        $client = new Client();
        $client->setUsername($credentials['username']);
        $client->setPassword("69876357:testprivatekey_FAKE");
        $client->setEndpoint($credentials['endpoint']);
        $response = $client->postWithFileGetContents('V4/Charge/SDKTest', $store);

        $this->assertEquals("ERROR", $response["status"]);
        $this->assertEquals("INT_905", $response["answer"]["errorCode"]);
    }

    /**
     * ./vendor/bin/phpunit --filter testClientConfiguration src/Lyra/Tests/ClientTest.php
     */
    public function testClientConfiguration()
    {
        $client = new Client();
        $credentials = $this->getCredentials();

        $client->setUsername($credentials['username']);
        $client->setPassword("testprivatekey_FAKE");
        $client->setEndpoint($credentials['endpoint']);
        $client->setPublickey($credentials["publicKey"]);

        $this->assertEquals(Constants::SDK_VERSION, $client->getVersion());
        $this->assertEquals($credentials['publicKey'], $client->getPublicKey());
        $this->assertEquals($credentials['endpoint'], $client->getEndpoint());
        $this->assertEquals($credentials['endpoint'], $client->getClientEndpoint());

        $client->setClientEndpoint("https://url.client");
        $this->assertEquals($credentials['endpoint'], $client->getEndpoint());
        $this->assertEquals("https://url.client", $client->getClientEndpoint());
    }

    /**
     * ./vendor/bin/phpunit --filter testDefaultClientConfiguration src/Lyra/Tests/ClientTest.php
     */
    public function testDefaultClientConfiguration()
    {
        $credentials = $this->getCredentials();

        Client::setDefaultUsername($credentials['username']);
        Client::setDefaultPassword($credentials['password']);
        Client::setDefaultEndpoint($credentials['endpoint']);
        Client::setDefaultPublicKey($credentials['publicKey']);
        Client::setDefaultClientEndpoint("https://url.client");
        Client::setdefaultSHA256Key($credentials['sha256Key']);

        $client = new Client();
        $store = array("value" => "sdk test string value");
        $response = $client->post('V4/Charge/SDKTest', $store);

        $this->assertEquals("SUCCESS", $response["status"]);
        $this->assertEquals($store["value"], $response["answer"]["value"]);

        $this->assertEquals(Constants::SDK_VERSION, $client->getVersion());
        $this->assertEquals($credentials['publicKey'], $client->getPublicKey());
        $this->assertEquals($credentials['username'], $client->getUsername());
        $this->assertEquals($credentials['password'], $client->getPassword());
        $this->assertEquals($credentials['endpoint'], $client->getEndpoint());
        $this->assertEquals("https://url.client", $client->getClientEndpoint());
        $this->assertEquals($credentials['sha256Key'], $client->getSHA256Key());
        $this->assertEquals(null, $client->getProxyHost());
        $this->assertEquals(null, $client->getProxyPort());

        Client::setDefaultProxy("simple.host", "1234");
        $client2 = new Client();
        $this->assertEquals($credentials['username'], $client2->getUsername());
        $this->assertEquals("simple.host", $client2->getProxyHost());
        $this->assertEquals("1234", $client2->getProxyPort());

        Client::resetDefaultConfiguration();
        $client = new Client();
        $this->assertEquals(null, $client->getPublicKey());
        $this->assertEquals(null, $client->getUsername());
        $this->assertEquals(null, $client->getPassword());
        $this->assertEquals(null, $client->getEndpoint());
        $this->assertEquals(null, $client->getClientEndpoint());
        $this->assertEquals(null, $client->getSHA256Key());
        $this->assertEquals(null, $client->getProxyHost());
        $this->assertEquals(null, $client->getProxyPort());
    }

    /**
     * ./vendor/bin/phpunit --filter testFakeProxy src/Lyra/Tests/ClientTest.php
     *
     * @expectedException Lyra\Exceptions\LyraException
     */
    public function testFakeProxy()
    {
        $client = new Client();
        $credentials = $this->getCredentials();

        $client->setUsername($credentials['username']);
        $client->setPassword($credentials['password']);
        $client->setEndpoint($credentials['endpoint']);
        $client->setTimeOuts(1,1);
        $client->setProxy('fake.host', 1234);

        $store = array("value" => "sdk test string value");
        $response = $client->post('V4/Charge/SDKTest', $store);
        $this->assertEquals("fake.host", $client->getProxyHost());
        $this->assertEquals("1234", $client->getProxyPort());
    }

    /**
     * ./vendor/bin/phpunit --filter testInvalidAnswer src/Lyra/Tests/ClientTest.php
     */
    public function testInvalidAnswer()
    {
        $client = new Client();
        $credentials = $this->getCredentials();

        $client->setUsername($credentials['username']);
        $client->setPassword($credentials['password']);

        $client->setEndpoint($credentials['endpoint']);

        $store = "FAKE";
        $response = $client->post('V4/Charge/SDKTest', $store);

        $this->assertEquals("ERROR", $response["status"]);
        $this->assertEquals("INT_902", $response["answer"]["errorCode"]);
    }

    /**
     * ./vendor/bin/phpunit --filter testGetParsedFormAnswer src/Lyra/Tests/ClientTest.php
     */
    public function testGetParsedFormAnswer($escaped=FALSE)
    {
        $client = new Client();
        $this->fakePostData('sha256_hmac_php53', $escaped);
        $answer = $client->getParsedFormAnswer();

        $this->assertEquals($_POST['kr-hash'], $answer['kr-hash']);
        $this->assertEquals($_POST['kr-hash-algorithm'], $answer['kr-hash-algorithm']);
        $this->assertEquals($_POST['kr-answer-type'], $answer['kr-answer-type']);

        $rebuild_string_answer = json_encode($answer['kr-answer']);
        /* php 5.3.3 does not support JSON_UNESCAPED_SLASHES */

        if (is_null($escaped)) {
            $this->assertEquals($_POST['kr-answer'], $rebuild_string_answer);
        }
        $this->assertEquals("array", gettype($answer['kr-answer']));
    }

    /**
     * ./vendor/bin/phpunit --filter testEscapedGetParsedFormAnswer src/Lyra/Tests/ClientTest.php
     */
    public function testEscapedGetParsedFormAnswer()
    {
        return $this->testGetParsedFormAnswer(TRUE);
    }

    /**
     * ./vendor/bin/phpunit --filter testErrorGetParsedFormAnswer src/Lyra/Tests/ClientTest.php
     * @expectedException Lyra\Exceptions\LyraException
     */
    public function testErrorGetParsedFormAnswer()
    {
        $client = new Client();
        $this->fakePostData('sha256_hmac');
        $_POST['kr-answer'] = "{BAD}}";

        $answer = $client->getParsedFormAnswer();
    }

    /**
     * ./vendor/bin/phpunit --filter testCheckManualHash256HMAC src/Lyra/Tests/ClientTest.php
     */
    public function testCheckManualHash256HMAC($escaped=FALSE)
    {
        $client = new Client();
        $credentials = $this->getCredentials();

        $this->assertNull($client->getLastCalculatedHash());

        $client->setSHA256Key($credentials["sha256Key"]);
        $this->fakePostData('sha256_hmac', $escaped);
        $isValid = $client->checkHash($client->getSHA256Key());
        $this->assertTrue($isValid);
        $this->assertNotNull($client->getLastCalculatedHash());

        $client->setPassword($credentials["password"]);
        $this->fakePostData('password', $escaped);
        $isValid = $client->checkHash($client->getPassword());
        $this->assertTrue($isValid);
        $this->assertNotNull($client->getLastCalculatedHash());
    }

    /**
     * ./vendor/bin/phpunit --filter testCheckAutoHash256HMAC src/Lyra/Tests/ClientTest.php
     */
    public function testCheckAutoHash256HMAC($escaped=FALSE)
    {
        $client = new Client();
        $credentials = $this->getCredentials();
        $client->setSHA256Key($credentials["sha256Key"]);
        $client->setPassword($credentials['password']);

        /* check browser POST data hash */
        $this->fakePostData('sha256_hmac', $escaped);
        $this->assertNull($client->getLastCalculatedHash());
        $isValid = $client->checkHash();
        $this->assertTrue($isValid);
        $this->assertNotNull($client->getLastCalculatedHash());

        /* check IPN POST data hash */
        $this->fakePostData('password', $escaped);
        $isValid = $client->checkHash();
        $this->assertTrue($isValid);
        $this->assertNotNull($client->getLastCalculatedHash());
    }

     /**
     * ./vendor/bin/phpunit --filter testEscapedCheckHash256HMAC src/Lyra/Tests/ClientTest.php
     */
    public function testEscapedCheckHash256HMAC()
    {
        $this->testCheckAutoHash256HMAC(TRUE);
    }
}