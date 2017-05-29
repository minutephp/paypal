<?php
/**
 * Created by: MinutePHP framework
 */
namespace App\Controller\Paypal {

    use Minute\Api\PaypalApi;
    use Minute\Error\PaymentError;
    use Minute\Event\Dispatcher;
    use Minute\Event\PaymentNotificationEvent;
    use Minute\Http\HttpRequestEx;
    use Minute\View\Redirection;

    class Pdt {
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
         */
        public function __construct(PaypalApi $api, Dispatcher $dispatcher) {
            $this->api        = $api;
            $this->dispatcher = $dispatcher;
        }

        public function index(HttpRequestEx $request) {
            if ($details = $this->api->decodePdt($request)) {
                $event = new PaymentNotificationEvent($details['subscr_id'], $details['txn_id'], $details['item_number'], 0, $details); //amount is always 0 for PDT
                $this->dispatcher->fire(PaymentNotificationEvent::PAYMENT_PROCESSING, $event);

                if ($url = $event->getRedirect()) {
                    return new Redirection($url);
                }

                throw new PaymentError("Wallet plugin is not installed or disabled (it should redirected before reaching here).");
            }

            throw new PaymentError("Payment link in invalid. Our support team has been notified.", PaymentError::CRITICAL);
        }
    }
}