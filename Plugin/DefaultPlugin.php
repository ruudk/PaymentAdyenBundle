<?php

namespace Ruudk\Payment\AdyenBundle\Plugin;

use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Model\PaymentInstructionInterface;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use JMS\Payment\CoreBundle\Plugin\ErrorBuilder;
use JMS\Payment\CoreBundle\Plugin\Exception\BlockedException;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use Monolog\Logger;
use Ruudk\Payment\AdyenBundle\Adyen\Api;

class DefaultPlugin extends AbstractPlugin
{
    /**
     * @var \Ruudk\Payment\AdyenBundle\Adyen\Api
     */
    protected $api;

    /**
     * @var \Monolog\Logger
     */
    protected $logger;

    public function __construct(Api $api)
    {
        $this->api = $api;
    }

    /**
     * @param Logger $logger
     */
    public function setLogger(Logger $logger = null)
    {
        $this->logger = $logger;
    }

    public function processes($name)
    {
        return $name !== 'adyen_ideal' && preg_match('/^adyen_/', $name);
    }

    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        if($transaction->getState() === FinancialTransactionInterface::STATE_NEW) {
            if($this->logger) {
                $this->logger->info('Create a new redirect exception.');
            }

            throw $this->createAdyenRedirectActionException($transaction);
        }

        if(false === $notification = $this->api->getNotification()) {
            if($this->logger) {
                $this->logger->info('No notification received!');
            }

            /**
             * Wait for the notification to come in!
             */
            throw new BlockedException("Waiting for notification from Adyen.");
        }

        if($notification->isPaymentSuccessful()) {
            $transaction->setReferenceNumber($notification->getPaymentReference());
            $transaction->setProcessedAmount($transaction->getRequestedAmount());
            $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
            $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);

            if($this->logger) {
                $this->logger->info('Payment is successful.');
            }

            return;
        }

        if($notification->isPaymentRefused() === TRUE) {
            $ex = new FinancialException('Payment refused.');
            $ex->setFinancialTransaction($transaction);
            $transaction->setResponseCode('REFUSED');
            $transaction->setReasonCode('REFUSED');
            $transaction->setState(FinancialTransactionInterface::STATE_FAILED);

            if($this->logger) {
                $this->logger->info('Payment is refused.');
            }

            throw $ex;
        }

        if($notification->isPaymentCancelled() === TRUE) {
            $ex = new FinancialException('Payment cancelled.');
            $ex->setFinancialTransaction($transaction);
            $transaction->setResponseCode('CANCELLED');
            $transaction->setReasonCode('CANCELLED');
            $transaction->setState(FinancialTransactionInterface::STATE_CANCELED);

            if($this->logger) {
                $this->logger->info('Payment is cancelled.');
            }

            throw $ex;
        }

        if($notification->isPaymentPending() === TRUE) {
            $ex = new FinancialException('Payment pending.');
            $ex->setFinancialTransaction($transaction);
            $transaction->setResponseCode('PENDING');
            $transaction->setReasonCode('PENDING');
            $transaction->setState(FinancialTransactionInterface::STATE_FAILED);

            if($this->logger) {
                $this->logger->info('Payment is pending.');
            }

            throw $ex;
        }

        if($this->logger) {
            $this->logger->info('Waiting for notification from Adyen.');
        }

        throw new BlockedException("Waiting for notification from Adyen.");
    }

    protected function createAdyenRedirectActionException(FinancialTransactionInterface $transaction)
    {
        /**
         * @var \JMS\Payment\CoreBundle\Model\PaymentInterface $payment
         */
        $payment = $transaction->getPayment();

        /**
         * @var \JMS\Payment\CoreBundle\Model\PaymentInstructionInterface $paymentInstruction
         */
        $paymentInstruction = $payment->getPaymentInstruction();

        /**
         * @var \JMS\Payment\CoreBundle\Model\ExtendedDataInterface $data
         */
        $data = $transaction->getExtendedData();

        $url = $this->api->startHostedPaymentPages(
            $payment->getId(),
            $payment->getTargetAmount(),
            $paymentInstruction->getCurrency(),
            $data->get('return_url'),
            $paymentInstruction->getPaymentSystemName()
        );

        $actionRequest = new ActionRequiredException('Redirect the user to Adyen.');
        $actionRequest->setFinancialTransaction($transaction);
        $actionRequest->setAction(new VisitUrl($url));

        return $actionRequest;
    }
}