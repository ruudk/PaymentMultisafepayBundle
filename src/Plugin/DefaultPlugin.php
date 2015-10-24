<?php

namespace Ruudk\Payment\MultisafepayBundle\Plugin;

use JMS\Payment\CoreBundle\Plugin\AbstractPlugin;
use JMS\Payment\CoreBundle\Model\FinancialTransactionInterface;
use JMS\Payment\CoreBundle\Plugin\Exception\Action\VisitUrl;
use JMS\Payment\CoreBundle\Plugin\Exception\ActionRequiredException;
use JMS\Payment\CoreBundle\Plugin\Exception\BlockedException;
use JMS\Payment\CoreBundle\Plugin\Exception\FinancialException;
use JMS\Payment\CoreBundle\Plugin\PluginInterface;
use Omnipay\MultiSafepay\Gateway;
use Psr\Log\LoggerInterface;

class DefaultPlugin extends AbstractPlugin
{
    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Omnipay\MultiSafepay\Gateway
     */
    protected $gateway;

    /**
     * @var string
     */
    protected $reportUrl;

    public function __construct(Gateway $gateway, $reportUrl)
    {
        $this->gateway = $gateway;
        $this->reportUrl = $reportUrl;
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger = null)
    {
        $this->logger = $logger;
    }

    public function processes($name)
    {
        return $name !== 'multisafepay_ideal' && preg_match('/^multisafepay_/', $name);
    }

    public function approveAndDeposit(FinancialTransactionInterface $transaction, $retry)
    {
        if($transaction->getState() === FinancialTransactionInterface::STATE_NEW) {
            throw $this->createRedirectActionException($transaction);
        }

        if(null !== $trackingId = $transaction->getTrackingId()) {
            /**
             * @var \Omnipay\MultiSafepay\Message\CompletePurchaseRequest $completePurchaseRequest
             */
            $completePurchaseRequest = $this->gateway->completePurchase(array(
                'transactionId' => $trackingId
            ));

            $status = $completePurchaseRequest->send();
            $rawData = $status->getData();

            if($this->logger) {
                $this->logger->info('TransactionStatus: Paid=' . $status->isSuccessful() . ", Status=" . $status->getPaymentStatus() . ", TransactionId=" . $status->getTransactionReference() . ", ID=" . (string) $rawData->ewallet->id);
            }

            if($status->isSuccessful()) {
                $transaction->setReferenceNumber((string) $rawData->ewallet->id);
                $transaction->setProcessedAmount((string) $rawData->customer->amount / 100);
                $transaction->setResponseCode(PluginInterface::RESPONSE_CODE_SUCCESS);
                $transaction->setReasonCode(PluginInterface::REASON_CODE_SUCCESS);

                if("IDEAL" === (string) $rawData->paymentdetails->type) {
                    $transaction->getExtendedData()->set('consumer_name', (string) $rawData->paymentdetails->accountholdername);
                    $transaction->getExtendedData()->set('consumer_account_number', (string) $rawData->paymentdetails->accountiban);
                }

                if($this->logger) {
                    $this->logger->info(sprintf(
                        'Payment is successful for transaction "%s".',
                        $transaction->getTrackingId()
                    ));
                }

                return;
            }

            if($status->isCanceled()) {
                $ex = new FinancialException('Payment cancelled.');
                $ex->setFinancialTransaction($transaction);
                $transaction->setResponseCode('CANCELLED');
                $transaction->setReasonCode('CANCELLED');
                $transaction->setState(FinancialTransactionInterface::STATE_CANCELED);

                if($this->logger) {
                    $this->logger->info(sprintf(
                        'Payment cancelled for transaction "%s".',
                        $transaction->getTrackingId()
                    ));
                }

                throw $ex;
            }

            if($status->isRejected()) {
                $ex = new FinancialException('Payment failed.');
                $ex->setFinancialTransaction($transaction);
                $transaction->setResponseCode('FAILED');
                $transaction->setReasonCode('FAILED');
                $transaction->setState(FinancialTransactionInterface::STATE_FAILED);

                if($this->logger) {
                    $this->logger->info(sprintf(
                        'Payment failed for transaction "%s".',
                        $transaction->getTrackingId()
                    ));
                }

                throw $ex;
            }

            if($status->isExpired()) {
                $ex = new FinancialException('Payment expired.');
                $ex->setFinancialTransaction($transaction);
                $transaction->setResponseCode('EXPIRED');
                $transaction->setReasonCode('EXPIRED');
                $transaction->setState(FinancialTransactionInterface::STATE_FAILED);

                if($this->logger) {
                    $this->logger->info(sprintf(
                        'Payment is expired for transaction "%s".',
                        $transaction->getTrackingId()
                    ));
                }

                throw $ex;
            }

            if($this->logger) {
                $this->logger->info(sprintf(
                    'Waiting for notification from MultiSafepay for transaction "%s".',
                    $transaction->getTrackingId()
                ));
            }

            throw new BlockedException("Waiting for notification from MultiSafepay.");
        }
    }

    public function createRedirectActionException(FinancialTransactionInterface $transaction)
    {
        $parameters = $this->getPurchaseParameters($transaction);

        /**
         * @var \Omnipay\MultiSafepay\Message\PurchaseRequest $purchaseRequest
         */
        $purchaseRequest = $this->gateway->purchase($parameters);

        /**
         * @var \Omnipay\MultiSafepay\Message\PurchaseResponse $purchaseResponse
         */
        $purchaseResponse = $purchaseRequest->send();

        if($this->logger) {
            $this->logger->info($purchaseRequest->getData()->asXML());
            $this->logger->info($purchaseResponse->getData()->asXML());
        }

        $url = $purchaseResponse->getRedirectUrl();
        if(empty($url)) {
            $ex = new FinancialException('Payment failed.');
            $ex->setFinancialTransaction($transaction);
            $transaction->setResponseCode('FAILED');
            $transaction->setReasonCode('FAILED');
            $transaction->setState(FinancialTransactionInterface::STATE_FAILED);

            if($this->logger) {
                $this->logger->info(sprintf(
                    'Payment failed for transaction "%s" with reason: ',
                    $transaction->getTrackingId(),
                    $purchaseResponse->getMessage()
                ));
            }

            throw $ex;
        }

        $actionRequest = new ActionRequiredException('Redirect the user to MultiSafepay.');
        $actionRequest->setFinancialTransaction($transaction);
        $actionRequest->setAction(new VisitUrl($purchaseResponse->getRedirectUrl()));

        if($this->logger) {
            $this->logger->info(sprintf(
                'Create a new redirect exception for transaction "%s".',
                $purchaseResponse->getTransactionReference()
            ));
        }

        return $actionRequest;
    }

    /**
     * @param FinancialTransactionInterface $transaction
     * @return array
     */
    protected function getPurchaseParameters(FinancialTransactionInterface $transaction)
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

        $transaction->setTrackingId($payment->getId());

        $card = new \Omnipay\Common\CreditCard();
        $parameters = array(
            'transactionId' => $transaction->getTrackingId(),
            'amount'        => $payment->getTargetAmount(),
            'currency'      => $paymentInstruction->getCurrency(),
            'description'   => $data->has('description') ? $data->get('description') : 'Transaction ' . $payment->getId(),
            'clientIp'      => $data->get('client_ip'),
            'gateway'       => $this->getGateway($transaction),
            'card'          => $card,
            'notifyUrl'     => $this->reportUrl,
            'cancelUrl'     => $data->get('cancel_url'),
            'returnUrl'     => $data->get('return_url'),
        );

        return $parameters;
    }

    protected function getGateway(FinancialTransactionInterface $transaction)
    {
        $gateways = array(
            'ideal'           => 'IDEAL',
            'mister_cash'     => 'MISTERCASH',
            'visa'            => 'VISA',
            'mastercard'      => 'MASTERCARD',
            'direct_ebanking' => 'DIRECTBANK',
            'giropay'         => 'GIROPAY',
            'maestro'         => 'MAESTRO',
            'bank_transfer'   => 'BANKTRANS',
            'direct_debit'    => 'DIRDEB',
        );

        $name = substr($transaction->getPayment()->getPaymentInstruction()->getPaymentSystemName(), 13);

        return isset($gateways[$name]) ? $gateways[$name] : null;
    }
}
