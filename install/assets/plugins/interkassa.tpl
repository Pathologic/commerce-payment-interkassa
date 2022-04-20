//<?php
/**
 * Payment Interkassa
 *
 * Interkassa payments processing
 *
 * @category    plugin
 * @version     0.0.1
 * @author      Pathologic
 * @internal    @events OnRegisterPayments,OnBeforeOrderSending,OnManagerBeforeOrderRender
 * @internal    @properties &title=Title;text; &accountId=Account ID;text; &secretKey=Secret Key;text; &debugKey=Debug Key;text; &debug=Debug;list;No==0||Yes==1;1 
 * @internal    @modx_category Commerce
 * @internal    @installset base
 */

if (empty($modx->commerce) && !defined('COMMERCE_INITIALIZED')) {
    return;
}

$isSelectedPayment = !empty($order['fields']['payment_method']) && $order['fields']['payment_method'] == 'interkassa';
$commerce = ci()->commerce;
$lang = $commerce->getUserLanguage('interkassa');

switch ($modx->event->name) {
    case 'OnRegisterPayments': {
        $class = new \Commerce\Payments\InterkassaPayment($modx, $params);

        if (empty($params['title'])) {
            $params['title'] = $lang['interkassa.caption'];
        }

        $commerce->registerPayment('interkassa', $params['title'], $class);
        break;
    }

    case 'OnBeforeOrderSending': {
        if ($isSelectedPayment) {
            $FL->setPlaceholder('extra', $FL->getPlaceholder('extra', '') . $commerce->loadProcessor()->populateOrderPaymentLink());
        }

        break;
    }

    case 'OnManagerBeforeOrderRender': {
        if (isset($params['groups']['payment_delivery']) && $isSelectedPayment) {
            $params['groups']['payment_delivery']['fields']['payment_link'] = [
                'title'   => $lang['interkassa.link_caption'],
                'content' => function($data) use ($commerce) {
                    return $commerce->loadProcessor()->populateOrderPaymentLink('@CODE:<a href="[+link+]" target="_blank">[+link+]</a>');
                },
                'sort' => 50,
            ];
        }

        break;
    }
}
