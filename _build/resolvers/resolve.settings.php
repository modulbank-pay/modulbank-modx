<?php

/** @var xPDOSimpleObject $object */
if ($object->xpdo) {
    /* @var modX $modx */
    $modx = $object->xpdo;

    /** @var array $options */
    switch ($options[xPDOTransport::PACKAGE_ACTION]) {
        case xPDOTransport::ACTION_INSTALL:
        case xPDOTransport::ACTION_UPGRADE:
            $payment = $modx->getObject(msPayment::class, ['class' => 'Modulbank']);

            if (!$payment) {
                $q = $modx->newObject(msPayment::class);
                $q->fromArray(array(
                    'name' => 'Modulbank',
                    'active' => 0,
                    'class' => 'Modulbank'
                ));
                $save = $q->save();
            }

            /* @var miniShop2 $miniShop2 */
            $miniShop2 = $modx->getService('minishop2');

            if ($miniShop2) {
                $miniShop2->addService(
                    'payment',
                    'mspModulbank',
                    '{core_path}components/mspmodulbank/model/modulbank.class.php'
                );
            }
            break;

        case xPDOTransport::ACTION_UNINSTALL:
            $miniShop2 = $modx->getService('minishop2');
            $miniShop2->removeService(
                'payment',
                'Modulbank'
            );
            $payment = $modx->getObject(msPayment::class, ['class' => 'Modulbank']);
            if ($payment) {
                $payment->remove();
            }
            $modx->removeCollection(modSystemSetting::class, array('key:LIKE' => 'mspmodulbank\_%'));
            break;
    }
}
return true;
