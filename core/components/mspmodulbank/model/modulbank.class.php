<?php

$newBasePaymentHandler = MODX_CORE_PATH . 'components/minishop2/handlers/mspaymenthandler.class.php';
$oldBasePaymentHandler = MODX_CORE_PATH . 'components/minishop2/model/minishop2/mspaymenthandler.class.php';

require_once __DIR__ . '/lib/modulbank/ModulbankHelper.php';
require_once __DIR__ . '/lib/modulbank/ModulbankReceipt.php';

if (!class_exists('msPaymentInterface')) {
	if (file_exists($newBasePaymentHandler)) {
		require_once $newBasePaymentHandler;
	} else {
		require_once $oldBasePaymentHandler;
	}
}

class Modulbank extends msPaymentHandler implements msPaymentInterface
{
	public $config;
	/** @var modX */
	public $modx;

	private $callbackUrl = '';

	const LOG_NAME = '[Modulbank]';

	public function __construct(xPDOObject $object, $config = [])
	{
		parent::__construct($object, $config);


		$this->modx = $object->xpdo;

		$this->modx->addPackage('mspmodulbank', MODX_CORE_PATH . 'components/mspmodulbank/model/');
		$this->modx->lexicon->load('mspmodulbank:default');

		$siteUrl           = $this->modx->getOption('site_url');
		$assetsUrl         = $this->modx->getOption('assets_url') . 'components/mspmodulbank/';
		$this->callbackUrl = $siteUrl . substr($assetsUrl, 1) . 'modulbank_callback.php';

		$this->config = array_merge([
			'merchant'       => $this->modx->getOption('mspmodulbank_merchant'),
			'secret'         => $this->modx->getOption('mspmodulbank_secret'),
			'test_secret'    => $this->modx->getOption('mspmodulbank_test_secret'),
			'debug'          => $this->modx->getOption('mspmodulbank_debug', null, false),
			'tax_mode'          => $this->modx->getOption('mspmodulbank_tax_mode', null, 'usn_income_outcome'),
			'payment_method' => $this->modx->getOption('mspmodulbank_payment_method', null, 'full_prepayment'),
			'payment_object' => $this->modx->getOption('mspmodulbank_payment_object', null, 'commodity'),
			'tax'            => $this->modx->getOption('mspmodulbank_tax', null, 'none'),
			'tax_delivery'   => $this->modx->getOption('mspmodulbank_tax_delivery', null, 'none'),
			'preauth'        => $this->modx->getOption('mspmodulbank_preauth', null, false),
			'test_mode'      => $this->modx->getOption('mspmodulbank_test_mode', null, false),
			'success_id'    => $this->modx->getOption('mspmodulbank_success_id', null, 0),
			'status_for_confirm_id' => $this->modx->getOption('mspmodulbank_status_for_confirm_id', null, 3),
            'status_for_cancel_id'  => $this->modx->getOption('mspmodulbank_status_for_cancel_id', null, 4),
		], $config);
	}

	/* @inheritdoc} */
	public function send(msOrder $order)
	{
		$link = $this->getModulePaymentLink($order);

		if ($link) {
			return $this->success('', ['redirect' => $link]);
		}

		return $this->error('', []);
	}

	/**
	 * Метод получения ссылки на оплату
	 * @param msOrder $order
	 * @return string
	 */
	public function getModulePaymentLink(msOrder $order)
	{
		$id        = $order->get('id');
		$sum       = $order->get('cost');
		$receipt   = $this->getReceipt($order);
		$profile   = $order->getOne('UserProfile');
		$successUrl =  MODX_SITE_URL;
		if ($this->config['success_id']) {
			$successUrl = $this->modx->makeUrl($this->config['success_id'], '', ['order_id' => $id], 'full');
		}
		$request   = [
			'merchant'        => $this->config['merchant'],
			'amount'          => $sum,
			'custom_order_id' => $id,
			'description'     => $this->modx->lexicon('mspmodulbank.payment_message') . $id,
			'testing'         => $this->config['test_mode'] ? 1 : 0,
			'receipt_items'   => $receipt,
			'receipt_contact' => $profile->get('email'),
			'unix_timestamp'  => time(),
			'callback_url'    => $this->callbackUrl,
			'success_url'     => $successUrl,
			'salt'            => \ModulbankHelper::getSalt(),
			'preauth'         => $this->config['preauth'] ? 1 : 0
		];

		$this->log($request);

		$key = $this->config['test_mode'] ? $this->config['test_secret'] : $this->config['secret'];

		$response = \ModulbankHelper::createBill($request, $key);

		$this->log($response);

		$result = json_decode($response);
		if (!$result) {
			$this->modx->log(1, 'Error Api Call ' . $response);
			return false;
		}
		if ($result->status !== 'ok') {
			$this->modx->log(1, $result->message);
			return false;
		}

		$this->saveOrder(
			[
				'order_id' => $id,
				'bill_id' => $result->bill->id,
			]
		);

		return $result->bill->url;
	}

	/* @inheritdoc} */
	public function receive(msOrder $order)
	{
		$id   = $order->get('id');
		$key = $this->config['test_mode'] ? $this->config['test_secret'] : $this->config['secret'];
		$signature = \ModulbankHelper::calcSignature($key, $_POST);

		if ($signature === $_POST['signature']) {
			$statusPaid = $this->modx->getOption('ms2_status_paid', null, 2);
			$this->ms2->changeOrderStatus($id, $statusPaid);
			$meta = json_decode($_POST['meta']);
			$paymentId = $meta->bill_id;
			$bill = $this->modx->getObject(mspModulbankBill::class, ['bill_id' => $paymentId]);
			$bill->set('transaction', $_POST['transaction_id']);
			$bill->save();
			exit('OK');
		} else {
			$this->paymentError('Wrong signature.', $_POST);
		}
	}

	/**
	 * @param $text
	 * @param array $request
	 */
	public function paymentError($text, $request = [])
	{
		$this->modx->log(
			modX::LOG_LEVEL_ERROR,
			self::LOG_NAME . ' ' . $text . ', request: ' . print_r($request, true)
		);
		header("HTTP/1.0 400 Bad Request");

		die('ERR: ' . $text);
	}

	public function log($message)
	{
		if (!$this->config['debug']) {
			return;
		}
		if (!is_scalar($message)) {
			$message = var_export($message, true);
		}
		$old = $this->modx->setLogLevel(3);
		$this->modx->log(3, $message, ["target" => "FILE", "options" => ["filename" => 'modulbank.log']]);
		$this->modx->setLogLevel($old);
    }

	/**
	 * Передача товаров для фискализации
	 * @param msOrder $order
	 * @return array
	 */
	private function getReceipt(msOrder $order)
	{
		/** @var msProduct[] $products */
		$products = $order->getMany('Products');

		if (!$products) {
			return "[]";
		}

		$paymentPrice = $order->getOne('Payment')->get('price');

		$receipt = new \ModulbankReceipt($this->config['tax_mode'], $this->config['payment_method'], $order->get('cost'));
		foreach ($products as $product) {
			$productsCost = 0;
			if ((float) $paymentPrice < 0 && preg_match('/%$/', $paymentPrice)) {
				$productsCost += ($product->get('cost') - ($product->get('cost') / 100 * abs((float) $paymentPrice)));
			}
			if ((float) $paymentPrice > 0 && preg_match('/%$/', $paymentPrice)) {
				$productsCost += ($product->get('cost') + ($product->get('cost') / 100 * abs((float) $paymentPrice)));
			}

			if ((float) $paymentPrice === 0.0) {
				$productsCost += $product->get('cost');
			}
			$receipt->addItem(
				$product->get('name'),
				$productsCost,
				$this->config['tax'],
				$this->config['payment_object'],
				$product->get('count')
			);
		}

		if ($order->get('delivery_cost') > 0) {
			$receipt->addItem(
				$this->modx->lexicon('mspmodulbank.delivery'), $order->get('delivery_cost'), $this->config['tax_delivery'], 'service');
		}

		return $receipt->getJson();
	}

	public function saveOrder(array $saveArr)
	{

		$q = $this->modx->newObject('mspModulbankBill');
		$q->fromArray($saveArr);
		$q->save();

		return true;
	}

	public function getBillPaidStatus($billId)
	{
		$key = $this->config['test_mode'] ? $this->config['test_secret'] : $this->config['secret'];
		$result = \ModulbankHelper::getBillStatus($this->config['merchant'], $billId ,$key);
		$result = json_decode($result);
		return $result->bill->paid;
	}

	public function getTransactionPaidStatus($transactionId)
	{
		$key = $this->config['test_mode'] ? $this->config['test_secret'] : $this->config['secret'];
		$result = \ModulbankHelper::getTransactionStatus($this->config['merchant'], $transactionId ,$key);
		$result = json_decode($result);
		return $result->transaction->state === 'COMPLETE'
			|| $result->transaction->state === 'AUTHORIZED';
	}

	public function confirmPayment(msOrder $order)
	{
		$key = $this->config['test_mode'] ? $this->config['test_secret'] : $this->config['secret'];
		$query = $this->modx->newQuery('mspModulbankBill');
		$query->where( array('transaction:!=' => null, 'order_id' => $order->get('id')) );
		$obj = $this->modx->getObject('mspModulbankBill', $query);

		$receipt   = $this->getReceipt($order);
		$profile   = $order->getOne('UserProfile');

		$data = [
			'transaction' => $obj->transaction,
			'amount' => $order->get('cost'),
			'merchant' => $this->config['merchant'],
			'salt' => \ModulbankHelper::getSalt(),
			'unix_timestamp' => time(),
			'receipt_contact' => $profile->get('email'),
			'receipt_items' => $receipt,
		];

		$this->log(array_merge(['action:confirm'], $data));
		$res = \ModulbankHelper::capture($data, $key);
		$this->log($res);
		$result = json_decode($result);
		$operationResult = $this->modx->lexicon('mspmodulbank.confirm_operation').$result->status;
		$comment = $order->get('order_comment');
		$comment .= "\n".$operationResult;
		$order->set('order_comment', $comment);
		$order->save();
		if ($result->status != 'ok') {
			$this->modx->log(1, $result->message);
		}
	}

	public function cancelPayment(msOrder $order)
	{
		$key = $this->config['test_mode'] ? $this->config['test_secret'] : $this->config['secret'];

		$query = $this->modx->newQuery('mspModulbankBill');
		$query->where( array('transaction:!=' => null, 'order_id' => $order->get('id')) );
		$obj = $this->modx->getObject('mspModulbankBill', $query);
		$this->log(['action:refund', $this->config['merchant'], $order->get('cost'), $obj->transaction]);

		$res = \ModulbankHelper::refund($this->config['merchant'], $order->get('cost'), $obj->transaction, $key);
		$this->log($res);
		$result = json_decode($result);
		$operationResult = $this->modx->lexicon('mspmodulbank.refund_operation').$result->status;
		$comment = $order->get('order_comment');
		$comment .= "\n".$operationResult;
		$order->set('order_comment', $comment);
		$order->save();
		if ($result->status != 'ok') {
			$this->modx->log(1, $result->message);
		}
	}


}
