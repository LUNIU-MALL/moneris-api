<?php

namespace LuniuMall\Moneris;

use SimpleXMLElement;

/**
 * LuniuMall\Moneris\Gateway
 *
 * @property-read array $errors
 * @property-read \LuniuMall\Moneris\Gateway $gateway
 * @property-read array $params
 * @property \SimpleXMLElement|null $response
 */
class Transaction
{
    use Gettable, Settable;

    const EMPTY_PARAMETERS             = 1;
    const PARAMETER_NOT_SET            = 2;
    const UNSUPPORTED_TRANSACTION_TYPE = 3;

    /**
     * The errors for the transaction.
     *
     * @var array
     */
    protected $errors;

    /**
     * The Gateway instance.
     *
     * @var \LuniuMall\Moneris\Gateway
     */
    protected $gateway;

    /**
     * The extra parameters needed for Moneris.
     *
     * @var array
     */
    protected $params;

    /**
     * @var \SimpleXMLElement|null
     */
    protected $response = null;

    /**
     * Create a new Transaction instance.
     *
     * @param \LuniuMall\Moneris\Gateway $gateway
     * @param array $params
     */
    public function __construct(Gateway $gateway, array $params = [])
    {
        $this->gateway = $gateway;
        $this->params = $this->prepare($params);
    }

    /**
     * Retrieve the amount for the transaction. The is only available on certain transaction types.
     *
     * @return string|null
     */
    public function amount()
    {
        if (isset($this->params['amount'])) {
            return $this->params['amount'];
        }

        return null;
    }

    /**
     * Append elements to the XML response.
     *
     * @param array $params
     * @param \SimpleXMLElement $type
     *
     * @return void
     */
    protected function append(array $params, SimpleXMLElement $type)
    {
        foreach ($params as $key => $value) {
            if (is_array($value)) {
                if ($key === 'items') {
                    foreach ($value as $item) {
                        $parent = $type->addChild('item');
                        $parent->addChild('name', isset($item['name']) ? $item['name'] : '');
                        $parent->addChild('quantity', isset($item['quantity']) ? $item['quantity'] : '');
                        $parent->addChild('product_code', isset($item['product_code']) ? $item['product_code'] : '');
                        $parent->addChild('extended_amount', isset($item['extended_amount']) ? $item['extended_amount'] : '');
                    }
                } else {
                    $parent = $type->addChild($key);

                    $this->append($value, $parent);
                }
            } else {
                $type->addChild($key, $value);
            }
        }
    }

    /**
     * Check that the required parameters have not been provided to the transaction.
     *
     * @return bool
     */
    public function invalid()
    {
        return !$this->valid();
    }

    /**
     * Retrieve the transaction number, assuming the transaction has been processed.
     *
     * @return null|string
     */
    public function number()
    {
        if (is_null($this->response)) {
            return null;
        }

        return (string)$this->response->receipt->TransID;
    }

    /**
     * Retrieve the order id for the transaction. The is only available on certain transaction types.
     *
     * @return string|null
     */
    public function order()
    {
        if (isset($this->params['order_id'])) {
            return $this->params['order_id'];
        }

        return null;
    }

    /**
     * Prepare the transaction parameters.
     *
     * @param array $params
     *
     * @return array
     */
    protected function prepare(array $params)
    {
        foreach ($params as $key => $value) {
            if (is_string($value)) {
                $params[$key] = trim($value);
            }

            if ($params[$key] == '') {
                unset($params[$key]);
            }
        }

        if (isset($params['credit_card'])) {
            $params['pan'] = preg_replace('/\D/', '', $params['credit_card']);
            unset($params['credit_card']);
        }

        if (isset($params['description'])) {
            $params['dynamic_descriptor'] = $params['description'];
            unset($params['description']);
        }

        if (isset($params['expiry_month']) && isset($params['expiry_year']) && !isset($params['expdate'])) {
            $params['expdate'] = sprintf('%02d%02d', $params['expiry_year'], $params['expiry_month']);
            unset($params['expiry_year'], $params['expiry_month']);
        }

        return $params;
    }

    protected function getType($type){
        $type = 'request';
        if(in_array($type, ['txn', 'acs'])){
            $type = 'MpiRequest';
        }else if($this->gateway->isMPI2){
            $type = 'Mpi2Request';
        }
        return $type;
    }

    /**
     * Convert the transaction parameters into an XML structure.
     *
     * @return string|bool
     */
    public function toXml()
    {
        $gateway = $this->gateway;
        $params = $this->params;

        $type = $this->getType($this->params['type']);
        $is_status_check = false;

        $xml = new SimpleXMLElement("<$type/>");
        $xml->addChild('store_id', $gateway->id);
        $xml->addChild('api_token', $gateway->token);

        // Used for retrieve order/transaction（获取订单状态。查验是否支付成功）
        if(isset($params['status_check'])){
            $is_status_check = true;
            $xml->addChild('status_check', $params['status_check']);
            unset($params['status_check']);
        }

        $type = $xml->addChild($params['type']);
        $efraud = in_array(
            $params['type'],
            [
                'purchase',
                'preauth',
                'card_verification',
                'cavv_purchase',
                'cavv_preauth',
                'res_purchase_cc',
                'res_preauth_cc',
                'res_cavv_purchase_cc',
                'res_cavv_preauth_cc'
            ]
        );

        $cc_action = in_array(
            $params['type'],
            [
                'res_add_cc',
                'res_update_cc'
            ]
        );
        unset($params['type']);

        if (!$is_status_check && $gateway->cvd && $efraud) {
            $cvd = $type->addChild('cvd_info');
            $cvd->addChild('cvd_indicator', '1');
            $cvd->addChild('cvd_value', $params['cvd']);
            unset($params['cvd']);
        }

        if (!$is_status_check && $gateway->avs && $efraud) {
            $avs = $type->addChild('avs_info');

            foreach ($params as $key => $value) {
                if (substr($key, 0, 4) !== 'avs_') {
                    continue;
                }

                $avs->addChild($key, $value);
                unset($params[$key]);
            }
        }

        if (!$is_status_check && $gateway->cof && ($efraud || $cc_action)) {
            $cofInfo = $type->addChild('cof_info');
            if (!empty($params['payment_indicator'])) {
                $cofInfo->addChild('payment_indicator', $params['payment_indicator']);
            }

            if (!empty($params['payment_information'])) {
                $cofInfo->addChild('payment_information', $params['payment_information']);
            }

            if (!empty($params['issuer_id'])) {
                $cofInfo->addChild('issuer_id', $params['issuer_id']);
            }

            unset($params['payment_indicator'], $params['payment_information'], $params['issuer_id']);
        }

        $this->append($params, $type);

        return $xml->asXML();
    }

    /**
     * Check that the required parameters have been provided to the transaction.
     *
     * @return bool
     */
    public function valid()
    {
        $params = $this->params;
        $errors = [];

        $errors[] = empty($params) ? ['field' => 'all', 'code' => self::EMPTY_PARAMETERS, 'title' => 'empty'] : null;

        if (isset($params['type'])) {
            switch ($params['type']) {
                case 'res_get_expiring':
                    break;
                case 'card_verification':
                    $errors[] = isset($params['order_id']) ? null : [
                        'field' => 'order_id',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    $errors[] = isset($params['pan']) ? null : [
                        'field' => 'credit_card',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    $errors[] = isset($params['expdate']) ? null : [
                        'field' => 'expdate',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    if ($this->gateway->avs) {
                        $errors[] = isset($params['avs_street_number']) ? null : [
                            'field' => 'avs_street_number',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];

                        $errors[] = isset($params['avs_street_name']) ? null : [
                            'field' => 'avs_street_name',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];

                        $errors[] = isset($params['avs_zipcode']) ? null : [
                            'field' => 'avs_zipcode',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                    }

                    if ($this->gateway->cvd) {
                        $errors[] = isset($params['cvd']) ? null : [
                            'field' => 'cvd',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                    }

                    if ($this->gateway->cof) {
                        $errors[] = isset($params['payment_indicator']) ? null : [
                            'field' => 'payment_indicator',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];

                        $errors[] = isset($params['payment_information']) ? null : [
                            'field' => 'payment_information',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                    }

                    break;
                case 'preauth':
                case 'purchase':
                    $errors[] = isset($params['order_id']) ? null : [
                        'field' => 'order_id',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];
                    if(!isset($params['status_check'])){
                        $errors[] = isset($params['pan']) ? null : [
                            'field' => 'credit_card',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];

                        $errors[] = isset($params['amount']) ? null : [
                            'field' => 'amount',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];

                        $errors[] = isset($params['expdate']) ? null : [
                            'field' => 'expdate',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];

                        if ($this->gateway->avs) {
                            $errors[] = isset($params['avs_street_number']) ? null : [
                                'field' => 'avs_street_number',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];

                            $errors[] = isset($params['avs_street_name']) ? null : [
                                'field' => 'avs_street_name',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];

                            $errors[] = isset($params['avs_zipcode']) ? null : [
                                'field' => 'avs_zipcode',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];
                        }

                        if ($this->gateway->cvd) {
                            $errors[] = isset($params['cvd']) ? null : [
                                'field' => 'cvd',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];
                        }

                        if ($this->gateway->cof) {
                            $errors[] = isset($params['payment_indicator']) ? null : [
                                'field' => 'payment_indicator',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];

                            $errors[] = isset($params['payment_information']) ? null : [
                                'field' => 'payment_information',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];
                        }
                    }

                    break;
                case 'cavv_preauth':
                case 'cavv_purchase':
                    $errors[] = isset($params['order_id']) ? null : [
                        'field' => 'order_id',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];
                    if(!isset($params['status_check'])){
                        $errors[] = isset($params['pan']) ? null : [
                            'field' => 'credit_card',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];

                        $errors[] = isset($params['amount']) ? null : [
                            'field' => 'amount',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];

                        $errors[] = isset($params['expdate']) ? null : [
                            'field' => 'expdate',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];

                        $errors[] = isset($params['cavv']) ? null : [
                            'field' => 'cavv',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];

                        $errors[] = isset($params['threeds_server_trans_id']) ? null : [
                            'field' => 'threeds_server_trans_id',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];

                        if ($this->gateway->avs) {
                            $errors[] = isset($params['avs_street_number']) ? null : [
                                'field' => 'avs_street_number',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];

                            $errors[] = isset($params['avs_street_name']) ? null : [
                                'field' => 'avs_street_name',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];

                            $errors[] = isset($params['avs_zipcode']) ? null : [
                                'field' => 'avs_zipcode',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];
                        }

                        if ($this->gateway->cvd) {
                            $errors[] = isset($params['cvd']) ? null : [
                                'field' => 'cvd',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];
                        }

                        if ($this->gateway->cof) {
                            $errors[] = isset($params['payment_indicator']) ? null : [
                                'field' => 'payment_indicator',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];

                            $errors[] = isset($params['payment_information']) ? null : [
                                'field' => 'payment_information',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];
                        }
                    }

                    break;
                case 'res_tokenize_cc':
                case 'purchasecorrection':
                    $errors[] = isset($params['order_id']) ? null : [
                        'field' => 'order_id',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    $errors[] = isset($params['txn_number']) ? null : [
                        'field' => 'txn_number',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    break;
                case 'completion':
                    $errors[] = isset($params['comp_amount']) ? null : [
                        'field' => 'comp_amount',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    $errors[] = isset($params['order_id']) ? null : [
                        'field' => 'order_id',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    $errors[] = isset($params['txn_number']) ? null : [
                        'field' => 'txn_number',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    break;
                case 'refund':
                    $errors[] = isset($params['amount']) ? null : [
                        'field' => 'amount',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    $errors[] = isset($params['order_id']) ? null : [
                        'field' => 'order_id',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    $errors[] = isset($params['txn_number']) ? null : [
                        'field' => 'txn_number',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    break;
                case 'res_add_cc':
                    $errors[] = isset($params['pan']) ? null : [
                        'field' => 'credit_card',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    $errors[] = isset($params['expdate']) ? null : [
                        'field' => 'expdate',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    if ($this->gateway->cof) {
                        $errors[] = isset($params['issuer_id']) ? null : [
                            'field' => 'issuer_id',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                    }

                    break;
                case 'res_update_cc':
                    $errors[] = isset($params['data_key']) ? null : [
                        'field' => 'data_key',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    $errors[] = isset($params['pan']) ? null : [
                        'field' => 'credit_card',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    $errors[] = isset($params['expdate']) ? null : [
                        'field' => 'expdate',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    if ($this->gateway->cof) {
                        $errors[] = isset($params['issuer_id']) ? null : [
                            'field' => 'issuer_id',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                    }

                    break;
                case 'res_delete':
                case 'res_lookup_full':
                case 'res_lookup_masked':
                    $errors[] = isset($params['data_key']) ? null : [
                        'field' => 'data_key',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    break;
                case 'res_preauth_cc':
                case 'res_purchase_cc':
                    $errors[] = isset($params['order_id']) ? null : [
                        'field' => 'order_id',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    if(!isset($params['status_check'])){
                        $errors[] = isset($params['data_key']) ? null : [
                            'field' => 'data_key',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];

                        $errors[] = isset($params['amount']) ? null : [
                            'field' => 'amount',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];

                        if ($this->gateway->avs) {
                            $errors[] = isset($params['avs_street_number']) ? null : [
                                'field' => 'avs_street_number',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];

                            $errors[] = isset($params['avs_street_name']) ? null : [
                                'field' => 'avs_street_name',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];

                            $errors[] = isset($params['avs_zipcode']) ? null : [
                                'field' => 'avs_zipcode',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];
                        }

                        if ($this->gateway->cvd) {
                            $errors[] = isset($params['cvd']) ? null : [
                                'field' => 'cvd',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];
                        }

                        if ($this->gateway->cof) {
                            $errors[] = isset($params['payment_indicator']) ? null : [
                                'field' => 'payment_indicator',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];

                            $errors[] = isset($params['payment_information']) ? null : [
                                'field' => 'payment_information',
                                'code' => self::PARAMETER_NOT_SET,
                                'title' => 'not_set'
                            ];
                        }
                    }

                    break;
                case 'res_cavv_preauth_cc':
                case 'res_cavv_purchase_cc':
                    $errors[] = isset($params['order_id']) ? null : [
                        'field' => 'order_id',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    if(!isset($params['status_check'])){
                        $errors[] = isset($params['data_key']) ? null : [
                            'field' => 'data_key',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];

                        $errors[] = isset($params['amount']) ? null : [
                            'field' => 'amount',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];

                        $errors[] = isset($params['cavv']) ? null : [
                            'field' => 'cavv',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];

                        $errors[] = isset($params['threeds_server_trans_id']) ? null : [
                            'field' => 'threeds_server_trans_id',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                    }
                    break;    
                case 'applepay_token_purchase':
                    $errors[] = isset($params['order_id']) ? null : [
                        'field' => 'order_id',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    if(!isset($params['status_check'])){
                        $errors[] = isset($params['amount']) ? null : [
                            'field' => 'amount',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                        $errors[] = isset($params['displayName']) ? null : [
                            'field' => 'displayName',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                        $errors[] = isset($params['network']) ? null : [
                            'field' => 'network',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                        $errors[] = isset($params['version']) ? null : [
                            'field' => 'version',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                        $errors[] = isset($params['data']) ? null : [
                            'field' => 'data',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                        $errors[] = isset($params['signature']) ? null : [
                            'field' => 'signature',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                        $errors[] = isset($params['header']['public_key_hash']) ? null : [
                            'field' => 'public_key_hash',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                        $errors[] = isset($params['header']['ephemeral_public_key']) ? null : [
                            'field' => 'ephemeral_public_key',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                        $errors[] = isset($params['header']['transaction_id']) ? null : [
                            'field' => 'transaction_id',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                    }
                    
                    break;
                case 'card_lookup':
                    $errors[] = isset($params['order_id']) ? null : [
                        'field' => 'order_id',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    $errors[] = isset($params['pan']) || isset($params['data_key']) ? null : [
                        'field' => 'credit_card',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    $errors[] = isset($params['notification_url']) ? null : [
                        'field' => 'notification_url',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    break;
                case 'threeds_authentication':
                    $errors[] = isset($params['order_id']) ? null : [
                        'field' => 'order_id',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];

                    if(!isset($params['status_check'])){
                        $errors[] = isset($params['cardholder_name']) ? null : [
                            'field' => 'cardholder_name',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
    
                        $errors[] = isset($params['pan']) || isset($params['data_key']) ? null : [
                            'field' => 'credit_card',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
    
                        $errors[] = isset($params['amount']) ? null : [
                            'field' => 'amount',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
    
                        $errors[] = isset($params['threeds_completion_ind']) ? null : [
                            'field' => 'threeds_completion_ind',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
    
                        $errors[] = isset($params['request_type']) ? null : [
                            'field' => 'request_type',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
    
                        $errors[] = isset($params['notification_url']) ? null : [
                            'field' => 'notification_url',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
    
                        $errors[] = isset($params['challenge_windowsize']) ? null : [
                            'field' => 'challenge_windowsize',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                        $errors[] = isset($params['browser_useragent']) ? null : [
                            'field' => 'browser_useragent',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                        $errors[] = isset($params['browser_java_enabled']) ? null : [
                            'field' => 'browser_java_enabled',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                        $errors[] = isset($params['browser_screen_height']) ? null : [
                            'field' => 'browser_screen_height',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                        $errors[] = isset($params['browser_screen_width']) ? null : [
                            'field' => 'browser_screen_width',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                        $errors[] = isset($params['browser_language']) ? null : [
                            'field' => 'browser_language',
                            'code' => self::PARAMETER_NOT_SET,
                            'title' => 'not_set'
                        ];
                    }

                    break;
                case 'cavv_lookup':
                    $errors[] = isset($params['cres']) ? null : [
                        'field' => 'cres',
                        'code' => self::PARAMETER_NOT_SET,
                        'title' => 'not_set'
                    ];
                    break;
                default:
                    $errors[] = [
                        'field' => 'type',
                        'code' => self::UNSUPPORTED_TRANSACTION_TYPE,
                        'title' => 'unsupported_transaction'
                    ];
            }
        } else {
            $errors[] = [
                'field' => 'type',
                'code' => self::PARAMETER_NOT_SET,
                'title' => 'not_set'
            ];
        }

        $errors = array_values(array_filter($errors));
        $this->errors = $errors;

        return empty($errors);
    }

    /**
     * Validate the result of the Moneris API call.
     *
     * @param \SimpleXMLElement $result
     *
     * @return \LuniuMall\Moneris\Response
     */
    public function validate(SimpleXMLElement $result)
    {
        $this->response = $result;

        $response = Response::create($this);
        $response->validate();

        return $response;
    }
}
