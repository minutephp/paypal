<?php
/**
 * User: Sanchit <dev@minutephp.com>
 * Date: 10/15/2016
 * Time: 3:07 AM
 */
namespace Minute\Processor {

    use Illuminate\Support\Str;
    use Minute\Config\Config;
    use Minute\Crypto\JwtEx;
    use Minute\Error\ProcessorError;
    use Minute\Event\ProcessorPaymentEvent;
    use Minute\Interfaces\PaymentProcessor;

    class Paypal implements PaymentProcessor {
        const NAME       = 'Paypal';
        const PAYPAL_KEY = 'wallet/processors/paypal';
        /**
         * @var Config
         */
        private $config;
        /**
         * @var JwtEx
         */
        private $jwt;

        /**
         * Paypal constructor.
         *
         * @param Config $config
         * @param JwtEx $jwt
         */
        public function __construct(Config $config, JwtEx $jwt) {
            $this->config = $config;
            $this->jwt    = $jwt;
        }

        public function checkout(ProcessorPaymentEvent $event) {
            if ($event->getProcessor() === 'paypal') {
                $payment = $event->getPayment();
                $item_id = $event->getOrderId();
                $vars    = $this->getPaymentVars($payment);
                $config  = $this->config->get(self::PAYPAL_KEY);
                $urls    = ['pdt' => '/processor/paypal/pdt', 'ipn' => '/processor/paypal/ipn'];

                if (empty($config['email'])) {
                    throw new ProcessorError("Paypal email is not configured. Please configure this from admin first.");
                }

                if (isset($vars['a1']) && ($vars['a1'] == 0) && !empty($vars['p1']) && !empty($vars['t1'])) { //work around for free trial (since paypal doesn't return tx)
                    $urls['pdt'] = sprintf('%s?hash=%s', $urls['pdt'], $this->jwt->encode((object) ['tx' => 'FREE-' . Str::random(8), 'item_number' => $item_id]));
                }

                $common = ['business' => $config['email'], 'currency_code' => 'USD', 'no_shipping' => '1', 'no_note' => '1', 'cpp_headerback_color' => 'FFFFFF',
                           'rm' => '0', 'notify_url' => $urls['ipn'], 'return' => $urls['pdt'], 'cancel_return' => $urls['cancel'] ?? '/pricing',
                           'cpp_headerborder_color' => 'FFFFFF', 'submit' => 'Pay Using PayPal >>'];

                if ($headerImage = $config['header_image'] ?? '') {
                    $common['cpp_header_image'] = $headerImage;
                }

                $vars  = array_merge($common, $vars, ['item_name' => $event->getItemName(), 'item_number' => $item_id]);
                $redir = sprintf('https://www.paypal.com/cgi-bin/webscr?%s', http_build_query($vars));

                //die($redir);
                $event->setUrl($redir);
            }
        }

        private function getPaymentVars(array $payment) {
            if (($payment['setup_amount'] > 0) && (empty($payment['rebill_amount']) || empty($payment['rebill_time']))) {
                $vars = ['cmd' => '_xclick', 'amount' => sprintf("%.02f", $payment['setup_amount'])];
            } else {
                $vars = ['cmd' => '_xclick-subscriptions', 'src' => '1', 'sra' => '1'];

                if (!empty($payment['setup_time'])) {
                    if (preg_match('/(\d+)(\w)$/', $payment['setup_time'], $matches)) {
                        $vars = array_merge($vars, ['a1' => sprintf("%.02f", $payment['setup_amount']), 'p1' => $matches[1], 't1' => strtoupper($matches[2])]);
                    }
                }

                if (!empty($payment['rebill_time'])) {
                    if (preg_match('/(\d+)(\w)$/', $payment['rebill_time'], $matches)) {
                        $vars = array_merge($vars, ['a3' => sprintf("%.02f", $payment['rebill_amount']), 'p3' => $matches[1], 't3' => strtoupper($matches[2])]);
                    }
                }
            }

            return $vars;
        }
    }
}