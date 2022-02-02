<?php

namespace FiberPay;

class FiberPayClient {

    private $version = '1.0';
    private $apiUrl;

    protected $apiKey;
    protected $apiSecret;

    public function __construct($apiKey, $apiSecret, $testServer = false) {
        $this->apiUrl = $testServer ? 'https://apitest.fiberpay.pl' : 'https://api.fiberpay.pl';

        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
    }

    private function call($httpMethod, $uri, $data = null){
        $headers = $this->createHeaders($httpMethod, $uri, $data);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_URL, $this->apiUrl . $uri);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);

        if ($httpMethod === 'post'){
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
        } else if ($httpMethod === 'put') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
        }

        $response = curl_exec($curl);

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if($httpCode >= 500) {
            $errorMsg = curl_error($curl);
            throw new \Exception($errorMsg);
        }

        if($response === false) {
            return  curl_error($curl);
        }

        return $response;
    }

    private function createHeaders($httpMethod, $uri, $data = null){
        $nonce = $this->nonce();

        $route = implode(' ', [strtoupper($httpMethod), $uri]);

        $data = empty($data) ? '' : json_encode($data);
        $signature = $this->signature($route, $nonce, $this->apiKey, $data, $this->apiSecret);

        $headers = [
            "Accept: application/json",
            "Content-Type: application/json",
            "X-API-Key: $this->apiKey",
            "X-API-Nonce: $nonce",
            "X-API-Route: $route",
            "X-API-Signature: $signature",
        ];

        return $headers;
    }

    protected function nonce() {
        $nonce = explode(' ', microtime());
        $nonce = $nonce[1] . substr($nonce[0], 2);
        return $nonce;
    }

    private function signature($route, $nonce, $apiKey, $data, $apiSecret) {
        $toBeSigned = implode('', [$route, $nonce, $apiKey, $data]);
        return hash_hmac('sha512', $toBeSigned, $apiSecret);
    }

    private function addCallbackData(array $data, string $callbackUrl = null, $callbackParams = null) {
        if(!empty($callbackUrl)) {
            $data['callbackUrl'] = $callbackUrl;
            if(!empty($callbackParams))
                $data['callbackParams'] = $callbackParams;
        }

        return $data;
    }

    private function addMetadata(array $data, string $metadata = null){
        if(!empty($metadata)){
            $data['metadata'] = $metadata;
        }

        return $data;
    }

    private function addOptionalParameter(array $data, string $name, $value = null){
        if(!empty($value)){
            $data[$name] = $value;
        }

        return $data;
    }

    public function setApiUrl($apiUrl) {
        $this->apiUrl = $apiUrl;
    }

    //FiberSplit methods

    public function createSplit($currency = 'PLN', $metadata = null) {
        $data['currency'] = $currency;

        $data = $this->addMetadata($data, $metadata);

        $uri = "/$this->version/orders/split";

        return $this->call('post', $uri, $data);
    }

    public function addSplitItem($orderCode, $toName, $toIban, $description, $amount,
                                 $currency = 'PLN', $callbackUrl = null, $callbackParams = null, 
                                 $metadata = null) {

        $data = [
            'amount' => $amount,
            'currency' => $currency,
            'parentCode' => $orderCode,
            'toName' => $toName,
            'toIban' => $toIban,
            'description' => $description,
        ];

        $data = $this->addCallbackData($data, $callbackUrl, $callbackParams);
        $data = $this->addMetadata($data, $metadata);

        $uri = "/$this->version/orders/split/item";

        return $this->call('post', $uri, $data);
    }

    public function endDefinitionOfSplit($orderCode) {
        $uri = "/$this->version/orders/split/$orderCode/define";

        return $this->call('put', $uri);
    }

    public function getSplit($orderCode) {
        $uri = "/$this->version/orders/split/$orderCode";

        return $this->call('get', $uri);
    }

    public function getSplitItem($orderItemCode) {
        $uri = "/$this->version/orders/split/item/$orderItemCode";

        return $this->call('get', $uri);
    }

    //FiberCollect methods

    public function createCollect($toName, $toIban, $currency = 'PLN', $metadata = null) {
        $data = [
            'currency' => $currency,
            'toName' => $toName,
            'toIban' => $toIban,
        ];

        $data = $this->addMetadata($data, $metadata);

        $uri = "/$this->version/orders/collect";

        return $this->call('post', $uri, $data);
    }

    public function addCollectItem($orderCode, $description, $amount, $currency = 'PLN',
                                   $callbackUrl = null, $callbackParams = null, 
                                   $metadata = null, $redirectUrl = null) {
        $data = [
            'amount' => $amount,
            'currency' => $currency,
            'description' => $description,
            'parentCode' => $orderCode,
        ];

        $data = $this->addCallbackData($data, $callbackUrl, $callbackParams);
        $data = $this->addMetadata($data, $metadata);

        $data = $this->addOptionalParameter($data, 'redirectUrl', $redirectUrl);


        $uri = "/$this->version/orders/collect/item";

        return $this->call('post', $uri, $data);
    }

    public function getCollectOrderInfo($orderCode) {
        $uri = "/$this->version/orders/collect/$orderCode";

        return $this->call('get', $uri);
    }

    public function getCollectOrderItemInfo($orderItemCode) {
        $uri = "/$this->version/orders/collect/item/$orderItemCode";

        return $this->call('get', $uri);
    }

    public function deleteCollectOrderItem($orderItemCode) {
        $uri = "/$this->version/orders/collect/item/$orderItemCode";

        return $this->call('delete', $uri);
    }

    //FiberDirect methods

    public function createDirect($toName, $toIban, $description, $amount,
                                 $currency = 'PLN', $callbackUrl = null, $callbackParams = null,
                                 $metadata = null) {
        $data = [
            'amount' => $amount,
            'currency' => $currency,
            'toName' => $toName,
            'toIban' => $toIban,
            'description' => $description,
        ];

        $data = $this->addCallbackData($data, $callbackUrl, $callbackParams);
        $data = $this->addMetadata($data, $metadata);

        $uri = "/$this->version/orders/direct";

        return $this->call('post', $uri, $data);
    }

    public function getDirectOrderInfo($orderCode) {
        $uri = "/$this->version/orders/direct/$orderCode";

        return $this->call('get', $uri);
    }

    public function deleteDirectOrder($orderCode) {
        $uri = "/$this->version/orders/direct/$orderCode";

        return $this->call('delete', $uri);
    }

    //FiberForward methods

    public function createForward($targetName, $targetIban, $brokerName, $brokerIban, 
                                 $description, $sourceAmount, $targetAmount,
                                 $currency = 'PLN', $callbackUrl = null, 
                                 $callbackParams = null, $metadata = null,
                                 $redirectUrl = null, $beforePaymentInfo = null, 
                                 $afterPaymentInfo = null) {
        $data = [
            'sourceAmount' => $sourceAmount,
            'targetAmount' => $targetAmount,
            'currency' => $currency,
            'targetName' => $targetName,
            'targetIban' => $targetIban,
            'brokerName' => $brokerName,
            'brokerIban' => $brokerIban,
            'description' => $description,
        ];

        $data = $this->addCallbackData($data, $callbackUrl, $callbackParams);
        $data = $this->addMetadata($data, $metadata);
        $data = $this->addOptionalParameter($data, 'redirectUrl', $redirectUrl);
        $data = $this->addOptionalParameter($data, 'beforePaymentInfo', $beforePaymentInfo);
        $data = $this->addOptionalParameter($data, 'afterPaymentInfo', $afterPaymentInfo);

        $uri = "/$this->version/orders/forward";

        return $this->call('post', $uri, $data);
    }

    public function getForwardOrderInfo($orderCode) {
        $uri = "/$this->version/orders/forward/$orderCode";

        return $this->call('get', $uri);
    }

    //Settlements methods

    public function getSettlements(){
        $uri = "/$this->version/settlements";

        return $this->call('get', $uri);
    }

    public function getSettlement($settlementCode){
        $uri = "/$this->version/settlements/$settlementCode";

        return $this->call('get', $uri);
    }

}