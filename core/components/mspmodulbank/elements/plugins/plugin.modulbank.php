<?php
/** @var msOrder $order */
if ($modx->event->name != 'msOnChangeOrderStatus') {
    //Wrong event
    return;
}

$paymentId = $order->get('payment');
$payment   = $modx->getObject('msPayment', $paymentId);
if (!$payment || $payment->get('class') != 'Modulbank') {
    //Wrong payment
    return;
}

/** @var miniShop2 $miniShop2 */
if ($miniShop2 = $modx->getService('miniShop2')) {
    $miniShop2->loadCustomClasses('payment');
}

if (!class_exists('Modulbank')) {
    //Failed load class
    $modx->log(xPDO::LOG_LEVEL_ERROR, '[miniShop2:Modulbank] could not load payment class "Modulbank".');

    return;
}

$handler = new Modulbank($order);

if (!$modx->getOption('mspmodulbank_preauth', null, false)) {
    //Don't use two step payments
    return;
}
/** @var Modulbank $handler */

if (in_array($order->get('status'), $handler->config['status_for_confirm_id'])) {
    $handler->confirmPayment($order);
} elseif (in_array($order->get('status'), $handler->config['status_for_cancel_id'])) {
    $handler->cancelPayment($order);
}