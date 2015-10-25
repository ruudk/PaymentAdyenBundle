<?php

namespace Ruudk\Payment\AdyenBundle\Adyen;

use Symfony\Component\HttpFoundation\ParameterBag;

class Notification
{
    protected $merchantReference;
    protected $paymentMethod;
    protected $authResult;
    protected $pspReference;

    /**
     * @param string $merchantReference
     * @param string $skinCode
     * @param string $authResult
     * @param string $pspReference
     */
    public function __construct($merchantReference, $paymentMethod, $authResult, $pspReference)
    {
        $this->merchantReference = $merchantReference;
        $this->paymentMethod = $paymentMethod;
        $this->authResult = strtoupper($authResult);
        $this->pspReference = $pspReference;
    }

    /**
     * @param ParameterBag $parameters
     * @param string       $secretKey
     * @return bool|Notification
     */
    static public function createFromQuery(ParameterBag $parameters, $secretKey)
    {
        $expectedSignature = base64_encode(hash_hmac('sha1', implode(array(
            $parameters->get('authResult'),
            $parameters->get('pspReference'),
            $parameters->get('merchantReference'),
            $parameters->get('skinCode'),
        )), $secretKey, true));

        if($parameters->get('merchantSig') !== $expectedSignature) {
            return false;
        }

        return new self(
            $parameters->get('merchantReference'),
            $parameters->get('paymentMethod'),
            $parameters->get('authResult'),
            $parameters->get('pspReference')
        );
    }

    /**
     * @param ParameterBag $parameters
     * @return Notification
     */
    static public function createFromRequest(ParameterBag $parameters)
    {
        $authResult = 'UNKNOWN';

        if($parameters->get('eventCode') === 'AUTHORISATION') {
            if($parameters->get('success') === 'true') {
                $authResult = 'AUTHORISED';
            } else {
                $reason = $parameters->get('reason');
                if(!empty($reason)) {
                    $authResult = 'REFUSED';
                }
            }
        }

        return new self(
            $parameters->get('merchantReference'),
            $parameters->get('paymentMethod'),
            $authResult,
            $parameters->get('pspReference')
        );
    }

    /**
     * @return string
     */
    public function getMerchantReference()
    {
        return $this->merchantReference;
    }

    /**
     * @return string
     */
    public function getPaymentReference()
    {
        return $this->pspReference;
    }

    /**
     * @return bool
     */
    public function isPaymentRefused()
    {
        if($this->authResult == 'REFUSED') {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isPaymentCancelled()
    {
        if($this->authResult == 'CANCELLED') {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isPaymentPending()
    {
        if($this->authResult == 'PENDING') {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isPaymentSuccessful()
    {
        if($this->authResult == 'AUTHORISED') {
            return true;
        }

        return false;
    }
}