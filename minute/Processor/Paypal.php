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
    use Minute\File\TmpDir;
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
         * @var TmpDir
         */
        private $tmpDir;

        /**
         * Paypal constructor.
         *
         * @param Config $config
         * @param JwtEx $jwt
         * @param TmpDir $tmpDir
         */
        public function __construct(Config $config, JwtEx $jwt, TmpDir $tmpDir) {
            $this->config = $config;
            $this->jwt    = $jwt;
            $this->tmpDir = $tmpDir;
        }

        public function checkout(ProcessorPaymentEvent $event) {
            if ($event->getProcessor() === 'paypal') {
                $payment  = $event->getPayment();
                $item_id  = $event->getOrderId();
                $vars     = $this->getPaymentVars($payment);
                $config   = $this->config->get(self::PAYPAL_KEY);
                $host     = $this->config->getPublicVars('host');
                $debug    = $config['debug'] == 'true';
                $fallback = $this->jwt->encode((object) ['tx' => 'FREE-' . Str::random(8), 'item_number' => $item_id]);
                $urls     = [
                    'pdt' => "$host/processor/paypal/pdt?hash=$fallback&",
                    'ipn' => "$host/processor/paypal/ipn?item_number=$item_id&",
                    'cancel' => "$host/pricing"
                ];

                if (empty($config['email'])) {
                    throw new ProcessorError("Paypal email is not configured. Please configure this from admin first.");
                }

                $common = ['business' => $config['email'], 'currency_code' => 'USD', 'no_shipping' => '1', 'no_note' => '1', 'cpp_headerback_color' => 'FFFFFF',
                           'rm' => '0', 'notify_url' => $urls['ipn'], 'return' => $urls['pdt'], 'cancel_return' => $urls['cancel'],
                           'cpp_headerborder_color' => 'FFFFFF', 'submit' => 'Pay Using PayPal >>'];

                if ($headerImage = $config['header_image'] ?? '') {
                    $common['cpp_header_image'] = $headerImage;
                }

                if ($debug) {
                    $vars['notify_url'] = 'http://fac053e2.proxy.webhookapp.com/';
                    $vars['business']   = preg_replace('/@/', '-seller@', $config['email'], 1);
                    $vars['email']      = preg_replace('/@/', '-buyer@', $config['email'], 1);
                }

                $vars  = array_merge($common, $vars, ['item_name' => $event->getItemName(), 'item_number' => $item_id]);
                $redir = sprintf('https://www.%spaypal.com/cgi-bin/webscr?%s', $debug ? 'sandbox.' : '', http_build_query($vars));

                if ($debug) {
                    $file = sprintf('%s/%s (%s).php', $this->tmpDir->getTempDir('paypal'), $event->getItemName(), date('d-M-Y'));

                    file_put_contents($file, '<?' . "php\n\$pp = " . var_export($vars, true) . ";\n//IPN: https://webhookapp.com/hooks/fac053e2/requests");
                }

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