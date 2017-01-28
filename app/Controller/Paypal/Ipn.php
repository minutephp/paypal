<?php
/**
 * Created by: MinutePHP framework
 */
namespace App\Controller\Paypal {

    use App\Model\MWalletLog;
    use Minute\Api\PaypalApi;
    use Minute\Config\Config;
    use Minute\Error\PaymentError;
    use Minute\Event\Dispatcher;
    use Minute\Event\PaymentNotificationEvent;
    use Minute\Event\PaymentUserDataUpdateEvent;
    use Minute\Event\UserUpdateDataEvent;
    use Minute\Http\HttpRequestEx;
    use Minute\Routing\RouteEx;
    use Minute\View\Helper;
    use Minute\View\View;
    use Minute\Wallet\WalletManager;

    class Ipn {
        /**
         * @var PaypalApi
         */
        private $api;
        /**
         * @var Dispatcher
         */
        private $dispatcher;

        /**
         * Pdt constructor.
         *
         * @param PaypalApi $api
         * @param Dispatcher $dispatcher
         * @param WalletManager $walletManager
         */
        public function __construct(PaypalApi $api, Dispatcher $dispatcher, WalletManager $walletManager) {
            $this->api        = $api;
            $this->dispatcher = $dispatcher;
        }

        public function index(HttpRequestEx $request) {
            if ($this->api->verifyIpn($request)) {
                $params     = $request->getParameters();
                $item_id    = $params['item_number'] ?? 0;
                $txn_type   = $params['txn_type'] ?? '';
                $txn_status = $params['payment_status'] ?? '';
                $txn_id     = $params['txn_id'] ?? '';
                $subscr_id  = $params['subscr_id'] ?? '';
                $amount     = ($params['mc_gross'] ?? 0) ?: 0;
                $verified   = $params['payer_status'] === 'verified';

                $event = new PaymentNotificationEvent($subscr_id, $txn_id, $item_id, $amount, $params);

                if (preg_match('/Refunded|Reversed/', $txn_status)) {
                    $event->setAmount(-1 * (abs($amount) + abs($params['mc_fee'] ?? 0)));
                    $this->dispatcher->fire(PaymentNotificationEvent::PAYMENT_REFUND, $event);
                } elseif (preg_match('/subscr_signup/', $txn_type)) {
                    if ($params['mc_amount1'] === "0.00") { //free trial
                        $event->setAmount(0);
                        $this->dispatcher->fire(PaymentNotificationEvent::PAYMENT_FREE_TRIAL, $event);
                    }

                    $updates = ['contact_email' => $params['payer_email'], 'first_name' => $params['first_name'], 'last_name' => $params['last_name'], 'country' => $params['residence_country'],
                                'city' => $params['address_city'], 'state' => $params['address_state'] ?? '', 'zip' => $params['address_zip'] ?? '', 'phone' => $params['contact_phone'] ?? '',
                                'verified' => $verified ? 'true' : 'false'];

                    $updateEvent = new PaymentUserDataUpdateEvent($item_id, $updates);
                    $this->dispatcher->fire(PaymentUserDataUpdateEvent::PAYMENT_USER_DATA_UPDATE, $updateEvent);
                } elseif (preg_match('/subscr_payment|web_accept/', $txn_type)) {
                    if (preg_match('/Completed|Processed/ ', $txn_status)) {
                        $this->dispatcher->fire(PaymentNotificationEvent::PAYMENT_CONFIRMED, $event);
                    } elseif (preg_match('/Created|Pending/', $txn_status)) {
                        $this->dispatcher->fire(PaymentNotificationEvent::PAYMENT_PROCESSING, $event);
                    }
                } elseif (preg_match('/subscr_failed/', $txn_type)) {
                    $this->dispatcher->fire(PaymentNotificationEvent::PAYMENT_FAIL, $event);
                } elseif (preg_match('/subscr_eot|subscr_cancel/', $txn_type)) {
                    $this->dispatcher->fire(PaymentNotificationEvent::PAYMENT_CANCEL, $event);
                } elseif ($txn_status === 'Canceled_Reversal') {
                    $event->setTransactionId($txn_id . '-reverse');
                    $event->setAmount($amount + ($params['mc_fee'] ?? 0));
                    $this->dispatcher->fire(PaymentNotificationEvent::PAYMENT_CONFIRMED, $event);
                } else {
                    throw new PaymentError("Payment IPN in invalid.", PaymentError::CRITICAL);
                }
            }
        }
    }
}