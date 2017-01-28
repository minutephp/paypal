<?php
/**
 * User: Sanchit <dev@minutephp.com>
 * Date: 10/15/2016
 * Time: 4:35 AM
 */
namespace Minute\Api {

    use GuzzleHttp\Client;
    use Minute\Config\Config;
    use Minute\Crypto\JwtEx;
    use Minute\Error\PaymentError;
    use Minute\Http\HttpRequestEx;
    use Minute\Processor\Paypal;

    class PaypalApi {
        const PAYPAL_URL = 'https://www.paypal.com/cgi-bin/webscr';
        /**
         * @var Config
         */
        private $config;
        /**
         * @var JwtEx
         */
        private $jwt;

        /**
         * PaypalApi constructor.
         *
         * @param Config $config
         * @param JwtEx $jwt
         */
        public function __construct(Config $config, JwtEx $jwt) {
            $this->config = $config;
            $this->jwt    = $jwt;
        }

        public function decodePdt(HttpRequestEx $request) {
            if ($tx = $request->getParameter('tx')) {
                if ($at = $this->config->get(Paypal::PAYPAL_KEY . '/auth_token')) {
                    if ($this->debug()) {
                        $info   = [];
                        $status = 'SUCCESS';

                        foreach ($request->getParameters() as $key => $value) {
                            if (strpos($key, '_') === 0) {
                                $info[substr($key, 1)] = $value;
                            }
                        }
                    } else {
                        $client   = new Client(['verify' => $this->config->get('private/site/cert', false)]); //verify must point to a valid curl bundle
                        $query    = ['cmd' => '_notify-synch', 'tx' => $tx, 'at' => $at];
                        $response = $client->get(self::PAYPAL_URL, ['query' => $query]);
                        $body     = $response->getBody()->getContents();
                        $parts    = explode("\n", $body, 2);
                        $status   = trim($parts[0] ?? 'FAIL');
                        $info     = parse_ini_string($parts[1]);
                    }

                    if ($status === 'SUCCESS') {
                        return array_merge($info, ['amount' => $info['mc_gross']]);
                    }
                } else {
                    throw new PaymentError("Paypal authentication token is not defined");
                }
            } elseif ($hash = $request->getParameter('hash')) { //paypal doesn't return tx for free trials
                if ($details = $this->jwt->decode($hash)) {
                    return array_merge((array) $details, ['amount' => 0]);
                }
            }

            return false;
        }

        public function verifyIpn(HttpRequestEx $request) {
            if ($this->debug()) {
                return true;
            }

            $client   = new Client(['verify' => $this->config->get('private/site/cert', false)]); //verify must point to a valid curl bundle
            $query    = array_merge(['cmd' => '_notify-validate'], $request->getParameters());
            $response = $client->get(self::PAYPAL_URL, ['query' => $query]);
            $body     = $response->getBody()->getContents();

            return !empty($body) && ($body === 'VERIFIED');
        }

        private function debug() {
            return ($this->config->get(Paypal::PAYPAL_KEY . '/debug') === 'true');
        }
    }
}