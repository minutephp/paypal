<?php

/** @var Binding $binding */
use Minute\Event\Binding;
use Minute\Event\ProcessorConfigEvent;
use Minute\Event\ProcessorPaymentEvent;
use Minute\Event\TodoEvent;
use Minute\Info\PaypalInfo;
use Minute\Processor\Paypal;
use Minute\Todo\PaypalTodo;

$binding->addMultiple([
    //static event listeners go here
    ['event' => ProcessorPaymentEvent::PROCESSOR_CHECKOUT_URL, 'handler' => [Paypal::class, 'checkout']],
    ['event' => ProcessorConfigEvent::PROCESSOR_GET_FIELDS, 'handler' => [PaypalInfo::class, 'getFields']],

    //tasks
    ['event' => TodoEvent::IMPORT_TODO_ADMIN, 'handler' => [PaypalTodo::class, 'getTodoList']],
]);