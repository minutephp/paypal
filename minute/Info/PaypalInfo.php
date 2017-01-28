<?php
/**
 * User: Sanchit <dev@minutephp.com>
 * Date: 10/15/2016
 * Time: 4:33 AM
 */
namespace Minute\Info {

    use Minute\Event\ProcessorConfigEvent;

    class PaypalInfo {
        public function getFields(ProcessorConfigEvent $event) {
            if ($event->getProcessor() === 'paypal') {
                $event->setFields([
                    'email' => ['type' => 'email', 'label' => 'Paypal email'],
                    'auth_token' => ['label' => 'Authorization token', 'hint' => "You can find the auth_token in Paypal's merchant settings"],
                    'header_image' => ['type' => 'url', 'label' => 'Header image', 'hint' => 'Image shown on top of checkout page (on paypal.com)'],
                    'debug' => ['label' => 'Debug mode', 'hint' => "Type 'true' (without quotes) to enable debug mode (only for testing)"],
                ]);
            }
        }
    }
}