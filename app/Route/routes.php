<?php

/** @var Router $router */
use Minute\Model\Permission;
use Minute\Routing\Router;

$router->get('/processor/paypal/pdt', 'Paypal/Pdt', false)->setDefault('_noView', true);
$router->post('/processor/paypal/ipn', 'Paypal/Ipn', false);