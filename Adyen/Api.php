<?php

namespace Ruudk\Payment\AdyenBundle\Adyen;

use JMS\Payment\CoreBundle\Plugin\Exception\CommunicationException;
use Symfony\Component\HttpFoundation\Request;
use Buzz;

class Api
{
    /**
     * @var \Symfony\Component\HttpFoundation\Request $request
     */
    private $request;

    private $merchantAccount;
    private $skinCode;
    private $secretKey;
    private $host;
    private $timeout;

    /**
     * @param string  $merchantAccount
     * @param string  $skinCode
     * @param string  $secretKey
     * @param boolean $test
     * @param integer $timeout
     */
    public function __construct($merchantAccount, $skinCode, $secretKey, $test, $timeout)
    {
        $this->merchantAccount = $merchantAccount;
        $this->skinCode = $skinCode;
        $this->secretKey = $secretKey;
        $this->host = sprintf('https://%s.adyen.com', $test ? 'test' : 'live');
        $this->timeout = $timeout;
    }

    public function setRequest(Request $request = null)
    {
        $this->request = $request;
    }

    /**
     * @return array
     */
    public function getBankList()
    {
        $request = new Buzz\Message\Request('GET', '/hpp/idealbanklist.shtml', $this->host);
        $response = new Buzz\Message\Response();

        $client = new Buzz\Client\Curl();
        $client->setTimeout($this->timeout);
        $client->send($request, $response);

        if($response->getStatusCode() !== 200) {
            throw new CommunicationException("Can't load idealbanklist from Adyen.");
        }

        $banks = array();
        $xml = new \SimpleXMLElement($response->getContent());
        foreach($xml->bank as $bank) {
            $banks[(int) $bank->bank_id] = (string) $bank->bank_name;
        }

        return $banks;
    }

    public function start($id, $amount, $currency, $returnUrl, $method, $bank = null)
    {
        if($method === 'adyen_ideal') {
            return $this->startIdeal($id, $amount, $currency, $returnUrl, $bank);
        }

        return $this->startHostedPaymentPages($id, $amount, $currency, $returnUrl, $method);
    }

    /**
     * @param int    $id
     * @param string $bank_id
     * @param string $amount
     * @param string $currency
     * @param string $returnUrl
     * @return string
     */
    public function startIdeal($id, $amount, $currency, $returnUrl, $bank)
    {
        $parameters = array();
        $parameters['paymentAmount']     = $amount * 100;
        $parameters['currencyCode']      = $currency;
        $parameters['shipBeforeDate']    = (new \DateTime('+1 hour'))->format('Y-m-d');
        $parameters['merchantReference'] = $id;
        $parameters['skinCode']          = $this->skinCode;
        $parameters['merchantAccount']   = $this->merchantAccount;
        $parameters['sessionValidity']   = (new \DateTime('+1 hour'))->format(DATE_ATOM);
        $parameters['skipSelection']     = 'true';
        $parameters['brandCode']         = 'ideal';
        $parameters['idealIssuerId']     = $bank;
        $parameters['resURL']            = $returnUrl;
        $parameters['merchantSig']       = $this->signRequest($parameters);

        return sprintf('%s/hpp/redirectIdeal.shtml?%s',
            $this->host,
            http_build_query($parameters)
        );
    }

    /**
     * @param int    $id
     * @param string $amount
     * @param string $currency
     * @param string $returnUrl
     * @param string $method
     * @return string
     */
    public function startHostedPaymentPages($id, $amount, $currency, $returnUrl, $method)
    {
        $parameters = array();
        $parameters['paymentAmount']       = $amount * 100;
        $parameters['currencyCode']        = $currency;
        $parameters['shipBeforeDate']      = (new \DateTime('+1 hour'))->format('Y-m-d');
        $parameters['merchantReference']   = $id;
        $parameters['skinCode']            = $this->skinCode;
        $parameters['merchantAccount']     = $this->merchantAccount;
        $parameters['sessionValidity']     = (new \DateTime('+1 hour'))->format(DATE_ATOM);
        $parameters['shopperReference']    = '1';
        $parameters['recurringContract']   = null;
        $parameters['allowedMethods']      = null;
        $parameters['blockedMethods']      = null;
        $parameters['shopperStatement']    = null;
        $parameters['merchantReturnData']  = null;
        $parameters['billingAddressType']  = null;
        $parameters['deliveryAddressType'] = null;
        $parameters['offset']              = null;
        $parameters['countryCode']         = null;
        $parameters['skipSelection']       = 'true';
        $parameters['resURL']              = $returnUrl;

        /**
         * Convert method to adyen methodname
         */
        switch($method) {
            case 'adyen_mister_cash':
                $parameters['allowedMethods'] = 'bcmc';
                $parameters['countryCode'] = 'BE';
                break;

            case 'adyen_direct_ebanking':
                $parameters['allowedMethods'] = 'directEbanking';
                $parameters['countryCode'] = 'DE';
                break;

            case 'adyen_giropay':
                $parameters['allowedMethods'] = 'giropay';
                $parameters['countryCode'] = 'DE';
                break;

            case 'adyen_credit_card':
                $parameters['allowedMethods'] = 'amex,visa,mc';
                break;
        }

        /**
         * Sign the request
         */
        $parameters['merchantSig'] = $this->signRequest($parameters);

        return sprintf('%s/hpp/select.shtml?%s',
            $this->host,
            http_build_query($parameters)
        );
    }


    /**
     * @param array $parameters
     * @return string
     */
    protected function signRequest(array $parameters)
    {
        $keys = array('paymentAmount', 'currencyCode', 'shipBeforeDate', 'merchantReference', 'skinCode',
            'merchantAccount', 'sessionValidity', 'shopperEmail', 'shopperReference', 'recurringContract',
            'allowedMethods', 'blockedMethods', 'shopperStatement', 'merchantReturnData', 'billingAddressType',
            'deliveryAddressType', 'offset');

        $data = array();
        foreach($keys AS $key) {
            if(isset($parameters[$key])) {
                $data[] = $parameters[$key];
            }
        }
        
        return base64_encode(hash_hmac('sha1', implode($data), $this->secretKey, true));
    }

    /**
     * @return Notification|bool
     */
    public function getNotification()
    {
        if($this->request->getMethod() === 'POST') {
            return Notification::createFromRequest($this->request->request);
        }

        return Notification::createFromQuery($this->request->query, $this->secretKey);
    }
}
