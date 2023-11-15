<?php

namespace LuniuMall\Moneris;

class Receipt
{
    use Preparable;

    /**
     * @var array
     */
    protected $gateway;

    /**
     * @var array
     */
    protected $data;

    /**
     * Create a new Receipt instance.
     *
     * @param $data
     */
    public function __construct($gateway, $data)
    {
        $this->gateway = $gateway;

        $this->data = $this->prepare($data, [
            ['property' => 'amount', 'key' => 'TransAmount', 'cast' => 'string'],
            ['property' => 'authorization', 'key' => 'AuthCode', 'cast' => 'string'],
            ['property' => 'avs_result', 'key' => 'AvsResultCode', 'cast' => 'string'],
            ['property' => 'card', 'key' => 'CardType', 'cast' => 'string'],
            ['property' => 'code', 'key' => 'ResponseCode', 'cast' => 'string'],
            ['property' => 'complete', 'key' => 'Complete', 'cast' => 'boolean'],
            ['property' => 'cvd_result', 'key' => 'CvdResultCode', 'cast' => 'string'],
            ['property' => 'data', 'key' => 'ResolveData', 'cast' => 'array', 'callback' => 'setData'],
            ['property' => 'date', 'key' => 'TransDate', 'cast' => 'string'],
            ['property' => 'id', 'key' => 'ReceiptId', 'cast' => 'string'],
            ['property' => 'iso', 'key' => 'ISO', 'cast' => 'string'],
            ['property' => 'key', 'key' => 'DataKey', 'cast' => 'string'],
            ['property' => 'message', 'key' => 'Message', 'cast' => 'string'],
            ['property' => 'reference', 'key' => 'ReferenceNum', 'cast' => 'string'],
            ['property' => 'time', 'key' => 'TransTime', 'cast' => 'string'],
            ['property' => 'transaction', 'key' => 'TransID', 'cast' => 'string'],
            ['property' => 'type', 'key' => 'TransType', 'cast' => 'string'],
            ['property' => 'issuer_id', 'key' => 'IssuerId', 'cast' => 'string'],
            ['property' => 'timeout', 'key' => 'TimedOut', 'cast' => 'boolean'],
            ['property' => 'corporate_card', 'key' => 'CorporateCard', 'cast' => 'boolean'],
            ['property' => 'payment_type', 'key' => 'PaymentType', 'cast' => 'string'],
            ['property' => 'visa_debit', 'key' => 'IsVisaDebit', 'cast' => 'boolean'],

            // MPI response fields
            ['property' => 'message_type', 'key' => 'MessageType', 'cast' => 'string'],
            ['property' => '3ds_url', 'key' => 'ThreeDSMethodURL', 'cast' => 'string'],
            ['property' => '3ds_data', 'key' => 'ThreeDSMethodData', 'cast' => 'string'],
            ['property' => 'challenge_url', 'key' => 'ChallengeURL', 'cast' => 'string'],
            ['property' => 'challenge_data', 'key' => 'ChallengeData', 'cast' => 'string'],
            ['property' => 'challenge_completion_indicator', 'key' => 'ChallengeCompletionIndicator', 'cast' => 'string'],
            ['property' => 'trans_status', 'key' => 'TransStatus', 'cast' => 'string'], // [Y, N, A, U, R, C]
            ['property' => '3ds_trans_id', 'key' => 'ThreeDSServerTransId', 'cast' => 'string'],
            ['property' => 'ds_trans_id', 'key' => 'DSTransId', 'cast' => 'string'],
            ['property' => 'eci', 'key' => 'ECI', 'cast' => 'string'],
            ['property' => 'cavv', 'key' => 'Cavv', 'cast' => 'string'],
            ['property' => 'status_reason', 'key' => 'TransStatusReason', 'cast' => 'string'],
            ['property' => 'cardholder', 'key' => 'CardholderInfo', 'cast' => 'array'],
            ['property' => 'cavv_result', 'key' => 'CavvResultCode', 'cast' => 'string'],
        ]);
    }

    public function successful()
    {
        $complete = $this->gateway->isMPI2 ? $this->read('message') === 'SUCCESS' : $this->read('complete');
        $valid_code = $this->read('code') !== 'null';
        $code = (int)$this->read('code');

        $condition = $complete && $valid_code && $code >= 0 && $code < 50;

        // more condition for MPI - only for mpiThreeDSAuthentication (3DS)
        if($this->gateway->isMPI2){
            $condition = $condition && 
            (empty($this->read('trans_status')) || in_array($this->read('trans_status'), $this->gateway->transStatusCode));
        }
        
        return $condition;
    }

    /**
     * Read an item from the receipt.
     *
     * @param string $value
     *
     * @return mixed|null
     */
    public function read($value = '')
    {
        if (isset($this->data[$value]) && !is_null($this->data[$value])) {
            return $this->data[$value];
        }

        return null;
    }

    /**
     * Format the resolved data from the Moneris API.
     *
     * @param array $data
     *
     * @return array
     */
    private function setData(array $data)
    {
        return [
            'customer_id' => isset($data['cust_id']) ? (is_string($data['cust_id']) ? $data['cust_id'] : $data['cust_id']->__toString()) : null,
            'phone' => isset($data['phone']) ? (is_string($data['phone']) ? $data['phone'] : $data['phone']->__toString()) : null,
            'email' => isset($data['email']) ? (is_string($data['email']) ? $data['email'] : $data['email']->__toString()) : null,
            'note' => isset($data['note']) ? (is_string($data['note']) ? $data['note'] : $data['note']->__toString()) : null,
            'crypt' => isset($data['crypt_type']) ? intval($data['crypt_type']) : null,
            'masked_pan' => isset($data['masked_pan']) ? $data['masked_pan'] : null,
            'pan' => isset($data['pan']) ? $data['pan'] : null,
            'expiry_date' => [
                'month' => isset($data['expdate']) ? substr($data['expdate'], -2, 2) : null,
                'year' => isset($data['expdate']) ? substr($data['expdate'], 0, 2) : null,
            ],
        ];
    }
}
