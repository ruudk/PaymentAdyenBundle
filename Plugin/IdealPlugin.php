<?php

namespace Ruudk\Payment\AdyenBundle\Plugin;

use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Model\PaymentInstructionInterface;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use JMS\Payment\CoreBundle\Plugin\ErrorBuilder;

class IdealPlugin extends DefaultPlugin
{
    public function processes($name)
    {
        return "adyen_ideal" === $name;
    }

    public function checkPaymentInstruction(PaymentInstructionInterface $instruction)
    {
        $errorBuilder = new ErrorBuilder();

        /**
         * @var \JMS\Payment\CoreBundle\Entity\ExtendedData $data
         */
        $data = $instruction->getExtendedData();
        if(!$data->get('bank')) {
            $errorBuilder->addDataError('bank', 'form.error.bank_required');
        }

        if ($errorBuilder->hasErrors()) {
            throw $errorBuilder->getException();
        }
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

        $url = $this->api->startIdeal(
            $payment->getId(),
            $payment->getTargetAmount(),
            $paymentInstruction->getCurrency(),
            $data->get('return_url'),
            $data->get('bank')
        );

        $actionRequest = new ActionRequiredException('Redirect the user to Adyen.');
        $actionRequest->setFinancialTransaction($transaction);
        $actionRequest->setAction(new VisitUrl($url));

        return $actionRequest;
    }
}