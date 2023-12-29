<?php

const MODX_API_MODE = true;
require dirname(__FILE__, 4) . '/index.php';

/** @var modX $modx */
$modx->getService('error', 'error.modError');
$modx->setLogLevel(modX::LOG_LEVEL_ERROR);
$modx->setLogTarget('FILE');

/* @var miniShop2 $miniShop2 */
$miniShop2 = $modx->getService('minishop2');
$miniShop2->loadCustomClasses('payment');

if (!class_exists('Modulbank')) {
    exit('Error: could not load payment class "Modulbank".');
}


/* @var msPaymentInterface|Modulbank $handler */
$handler = new Modulbank($modx->newObject(msPayment::class));


if (!empty($_POST['state']) && $_POST['state'] === 'COMPLETE' || $_POST['state'] === 'AUTHORIZED') {
    $meta = json_decode($_POST['meta']);
    $paymentId = $meta->bill_id;
    $bill = $modx->getObject(mspModulbankBill::class, ['bill_id' => $paymentId]);
    /** @var msOrder $order */
    $order = $modx->getObject(msOrder::class, ['id' => $bill->order_id]);
    if ($order) {
        $handler->receive($order);
    } else {
        $modx->log(modX::LOG_LEVEL_ERROR, '[Modulbank] Could not retrieve order with id ' . $bill->order_id);
    }
}
