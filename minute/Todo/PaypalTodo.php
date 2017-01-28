<?php
/**
 * User: Sanchit <dev@minutephp.com>
 * Date: 11/5/2016
 * Time: 11:04 AM
 */
namespace Minute\Todo {

    use Minute\Config\Config;
    use Minute\Event\ImportEvent;

    class PaypalTodo {
        /**
         * @var TodoMaker
         */
        private $todoMaker;
        /**
         * @var Config
         */
        private $config;

        /**
         * MailerTodo constructor.
         *
         * @param TodoMaker $todoMaker - This class is only called by TodoEvent (so we assume TodoMaker is be available)
         * @param Config $config
         */
        public function __construct(TodoMaker $todoMaker, Config $config) {
            $this->todoMaker = $todoMaker;
            $this->config    = $config;
        }

        public function getTodoList(ImportEvent $event) {
            $todos[] = ['name' => 'Setup paypal configuration', 'description' => 'Setup paypal details',
                        'status' => $this->config->get('wallet/processors/paypal/auth_token') ? 'complete' : 'incomplete', 'link' => '/admin/wallet/processors'];

            $todos[] = $this->todoMaker->createManualItem("check-test-payment-from-paypal", "Check test payment from Paypal", 'Do a test transaction and see if the account is upgrading');

            $event->addContent(['Paypal' => $todos]);
        }
    }
}