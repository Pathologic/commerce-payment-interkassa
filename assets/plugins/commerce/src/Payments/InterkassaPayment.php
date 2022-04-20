<?php

namespace Commerce\Payments;

class InterkassaPayment extends Payment
{
    protected $debug;
    public function __construct($modx, array $params = [])
    {
        parent::__construct($modx, $params);
        $this->lang = $modx->commerce->getUserLanguage('interkassa');
        $this->debug = $this->getSetting('debug') == '1';
    }

    public function getMarkup()
    {
        if (empty($this->getSetting('accountId')) || empty($this->getSetting('secretKey'))) {
            return '<span class="error" style="color: red;">' . $this->lang['interkassa.error.empty_client_credentials'] . '</span>';
        }

        return '';
    }

    public function getPaymentMarkup()
    {
        $processor = $this->modx->commerce->loadProcessor();
        $order = $processor->getOrder();
        $currency = ci()->currency->getCurrency($order['currency']);
        $payment = $this->createPayment($order['id'], $order['amount']);

        $items = $this->prepareItems($processor->getCart());
        $isPartialPayment = $payment['amount'] < $order['amount'];

        if ($isPartialPayment) {
            $items = $this->decreaseItemsAmount($items, $order['amount'], $payment['amount']);
        }
        $debugKey = $this->getSetting('debugKey');
        $products = [];
        foreach ($items as $i => $item) {
            $products[] = [
                'id'          => $item['id'],
                'name'        => $item['name'],
                'description' => $item['name'],
                'qty'         => (int) $item['count'],
                'currency'    => empty($debugKey) ? $currency['code'] : 'UAH',
                'amount'      => number_format($item['price'], 2, '.', '')
            ];
        }

        $formData = [
            'ik_co_id'           => $this->getSetting('accountId'),
            'ik_pm_no'           => $payment['hash'],
            'ik_cur'             => empty($debugKey) ? $currency['code'] : 'UAH',
            'ik_am'              => number_format($payment['amount'], 2, '.', ''),
            'ik_desc'            => $this->lang['interkassa.order_num'] . ' ' . $order['id'],
            'ik_cli'             => $order['email'],
            'ik_products'        => $products,
            'ik_customer_fields' => [
                'name'  => $order['name'],
                'email' => $order['email'],
                'phone' => $order['phone']
            ],
            'ik_ia_u'            => $this->modx->getConfig('site_url') . 'commerce/interkassa/payment-process?' . http_build_query(['paymentHash' => $payment['hash']]),
            'ik_suc_u'           => $this->modx->getConfig('site_url') . 'commerce/interkassa/payment-success',
            'ik_fal_u'           => $this->modx->getConfig('site_url') . 'commerce/interkassa/payment-failed',
            'ik_pnd_u'           => $this->modx->getConfig('site_url') . 'commerce/interkassapayment-success',
        ];
        $formData['ik_sign'] = $this->getSign($formData);

        if ($this->debug) {
            $this->modx->logEvent(0, 1, 'Request data: <pre>' . htmlentities(print_r($formData, true)) . '</pre>', 'Commerce Interkassa Payment Debug: payment start');
        }

        $view = new \Commerce\Module\Renderer($this->modx, null, [
            'path' => 'assets/plugins/commerce/templates/front/',
        ]);

        return $view->render('interkassa.tpl', [
            'url'  => 'https://sci.interkassa.com/',
            'data' => $formData,
            'method' => 'post'
        ]);

    }

    protected static function sortByKeyRecursive(array $array): array
    {
        ksort($array, SORT_STRING);
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::sortByKeyRecursive($value);
            }
        }

        return $array;
    }

    public function handleCallback()
    {
        $data  = $_POST;

        if ($this->debug) {
            $this->modx->logEvent(0, 1, 'Callback data: <pre>' . htmlentities(print_r($data, true)) . '</pre>', 'Commerce Interkassa Payment Debug: callback start');
        }
        $debugKey = $this->getSetting('debugKey');
        if (isset($data['ik_inv_st']) && $data['ik_inv_st'] === 'success' && isset($data['ik_sign']) && $data['ik_sign'] === $this->getSign($data, empty($debugKey) ? 'secretKey' : 'debugKey')) {
            try {
                $processor = $this->modx->commerce->loadProcessor();
                $payment = $processor->loadPaymentByHash($data['ik_pm_no']);
                $processor->processPayment($payment, floatval($data['ik_am']));
            } catch (\Exception $e) {
                $this->modx->logEvent(0, 3, 'Payment process failed: ' . $e->getMessage(), 'Commerce Interkassa Payment');

                return false;
            }
        } else {
            if ($this->debug) {
                $data['sign'] = $this->getSign($data);
                $this->modx->logEvent(0, 1, 'Callback data: <pre>' . htmlentities(print_r($data, true)) . '</pre>', 'Commerce Interkassa Payment Debug: callback failed');
            }

            return false;
        }

        return true;
    }

    protected static function implodeRecursive(string $separator, array $array): string
    {
        $result = '';
        foreach ($array as $item) {
            $result .= (is_array($item) ? self::implodeRecursive($separator, $item) : (string) $item) . $separator;
        }

        return substr($result, 0, -1 * strlen($separator));
    }

    protected function getSign(array $data, $key = 'secretKey')
    {
        unset($data['ik_sign']); // Delete string with signature from dataset
        $data = self::sortByKeyRecursive($data); // Sort elements in array by var names in alphabet queue
        $key = $this->getSetting($key);
        $data[] = $key;  // Adding secret key at the end of the string
        $signString = self::implodeRecursive(':', $data); // Concatenation values using symbol ":"
        $sign = base64_encode(hash('sha256', $signString,
            true)); // Get sha256 hash as binare view using generate string and code it in BASE64

        return $sign; // Return the result
    }
}