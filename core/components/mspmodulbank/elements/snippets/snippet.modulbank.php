<?php
/** @var modX $modx */
/** @var array $scriptProperties */
/** @var Modulbank $Modulbank */


// [[!Modulbank?&page=`result`]]

$miniShop2 = $modx->getService('minishop2');
$miniShop2->loadCustomClasses('payment');

if (!class_exists('Modulbank')) {
    exit('Error: could not load payment class "Modulbank".');
}


/* @var msPaymentInterface|Modulbank $handler */
$Modulbank = new Modulbank($modx->newObject(msPayment::class));

if (!$Modulbank) {
  return 'Could not load Modulbank class!';
}

$modx->lexicon->load('mspmodulbank:default');
$transactionId = $_GET['transaction_id'];


$paid = $Modulbank->getTransactionPaidStatus($transactionId);

$message = $modx->lexicon('mspmodulbank.payment_wait');
if ($paid) {
  $message = $modx->lexicon('mspmodulbank.payment_success');
}

$properties  = [
  'modulbank_payment_info' => $message,
];

return $modx->getChunk('tpl.Modulbank.payment.info', $properties);