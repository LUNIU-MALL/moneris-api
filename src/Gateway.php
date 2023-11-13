<?php

namespace LuniuMall\Moneris;

use GuzzleHttp\Client;

/**
 * LuniuMall\Moneris\Gateway
 *
 * @property bool $avs
 * @property-read array $avsCodes
 * @property bool $cvd
 * @property-read array $cvdCodes
 * @property-read string $environment
 * @property-read string $id
 * @property-read string $token
 * @property \LuniuMall\Moneris\Transaction $transaction
 * @property bool $cof
 */
class Gateway
{
    use Gettable, Settable;

    /**
     * Determine if we will use the Address Verification Service.
     *
     * @var bool
     */
    protected $avs = false;

    /**
     * @var array
     */
    protected $avsCodes = ['A', 'B', 'D', 'M', 'P', 'W', 'X', 'Y', 'Z'];

    /**
     * Determine if we will use the Card Validation Digits.
     *
     * @var bool
     */
    protected $cvd = false;

    /**
     * @var array
     */
    protected $cvdCodes = ['M', 'Y', 'P', 'S', 'U'];

    /**
     * The environment used for connecting to the Moneris API.
     *
     * @var string
     */
    protected $environment;

    /**
     * The Moneris Store ID.
     *
     * @var string
     */
    protected $id;

    /**
     * The Moneris API Token.
     *
     * @var string
     */
    protected $token;

    /**
     * The current transaction.
     *
     * @var \LuniuMall\Moneris\Transaction
     */
    protected $transaction;

    /**
     * Determine if we will use Credential On File.
     *
     * @var bool
     */
    protected $cof = false;

    /**
     * Determine if it is MPI2 - using MPI2 url (different url and request header).
     *
     * @var bool
     */
    protected $isMPI2 = false;

    /**
     * 3-D Secure 2.0 TransStatus Codes
     * [Y, A, C, U, N, R]
     * A TransStatus = “Y” or “A” means the website can proceed immediately to the financial transaction with the CAVV value provided. This is a frictionless transaction flow without presenting a challenge.
     * A TransStatus = “C” indicates that the cardholder must be presented a challenge. To present the challenge, you must POST a <form> with a “creq” field, which contains the ChallengeData, to the URL defined in the ChallengeURL field.
     * A TransStatus = “D” indicates that the cardholder must be presented a challenge via Decoupled Authentication. See Decoupled Authentication.

     * @var array
     */
    protected $transStatusCode = ['Y', 'A', 'C'];

    /**
     * Create a new Moneris instance.
     *
     * @param string $id
     * @param string $token
     * @param string $environment
     *
     * @return void
     */
    public function __construct($id = '', $token = '', $environment = '')
    {
        $this->id = $id;
        $this->token = $token;
        $this->environment = $environment;
    }

    /**
     * Capture a pre-authorized a transaction.
     *
     * @param \LuniuMall\Moneris\Transaction|string $transaction
     * @param string|null $order
     * @param mixed|null $amount
     *
     * @return \LuniuMall\Moneris\Response
     */
    public function capture($transaction, $order = null, $amount = null)
    {
        if ($transaction instanceof Transaction) {
            $order = $transaction->order();
            $amount = !is_null($amount) ? $amount : $transaction->amount();
            $transaction = $transaction->number();
        }

        $params = [
            'type' => 'completion',
            'crypt_type' => Crypt::SSL_ENABLED_MERCHANT,
            'comp_amount' => $amount,
            'txn_number' => $transaction,
            'order_id' => $order,
        ];

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }

    /**
     * Create a new Vault instance.
     *
     * @return \LuniuMall\Moneris\Vault
     */
    public function cards()
    {
        $vault = new Vault($this->id, $this->token, $this->environment);

        if (isset($this->avs)) {
            $vault->avs = boolval($this->avs);
        }

        if (isset($this->cvd)) {
            $vault->cvd = boolval($this->cvd);
        }

        if (isset($this->cof)) {
            $vault->cof = boolval($this->cof);
        }

        return $vault;
    }

    /**
     * Pre-authorize a purchase.
     *
     * @param array $params
     *
     * @return \LuniuMall\Moneris\Response
     */
    public function preauth(array $params = [])
    {
        $params = array_merge($params, [
            'type' => 'preauth',
            'crypt_type' => Crypt::SSL_ENABLED_MERCHANT,
        ]);

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }

    /**
     * Make a purchase.
     *
     * @param array $params
     *
     * @return \LuniuMall\Moneris\Response
     */
    public function purchase(array $params = [])
    {
        $params = array_merge($params, [
            'type' => 'purchase',
            'crypt_type' => Crypt::SSL_ENABLED_MERCHANT,
        ]);

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }

        /**
     * Make an Apple Pay Token purchase.
     *
     * @param array $params
     *
     * @return \LuniuMall\Moneris\Response
     */
    public function applePayTokenPurchase(array $params = [])
    {
        $params = array_merge($params, [
            'type' => 'applepay_token_purchase',
            // 'crypt_type' => Crypt::SSL_ENABLED_MERCHANT,
        ]);

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }

    /**
     * Make a purchase with 3-D Secure.
     * Once 3DS authentication is completed and the crypt_type(ECI) = 5 then the transaction will proceed using the cavv_purchase or cavv_preath. 
     * If the crypt type = 6 or 7 then the transaction will proceed using purchase or preauth.
     * 
     * @param array $params
     *
     * @return \LuniuMall\Moneris\Response
     */
    public function cavvPurchase(array $params = [])
    {
        $default = ['crypt_type' => Crypt::AUTHENTICATED_E_COMMERCE];
        $params = array_merge($default, $params);
        $params = array_merge($params, [
            'type' => 'cavv_purchase',
        ]);

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }



    /**
     * Process a transaction through the Moneris API.
     *
     * @param \LuniuMall\Moneris\Transaction $transaction
     *
     * @return \LuniuMall\Moneris\Response
     */
    protected function process(Transaction $transaction)
    {
        $processor = new Processor(new Client());

        return $processor->process($transaction);
    }

    /**
     * Refund a transaction.
     *
     * @param \LuniuMall\Moneris\Transaction|string $transaction
     * @param string|null $order
     * @param mixed|null $amount
     *
     * @return \LuniuMall\Moneris\Response
     */
    public function refund($transaction, $order = null, $amount = null)
    {
        if ($transaction instanceof Transaction) {
            $order = $transaction->order();
            $amount = !is_null($amount) ? $amount : $transaction->amount();
            $transaction = $transaction->number();
        }

        $params = [
            'type' => 'refund',
            'crypt_type' => Crypt::SSL_ENABLED_MERCHANT,
            'amount' => $amount,
            'txn_number' => $transaction,
            'order_id' => $order,
        ];

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }

    /**
     * Get or create a new Transaction instance.
     *
     * @param array|null $params
     *
     * @return \LuniuMall\Moneris\Transaction
     */
    protected function transaction(array $params = null)
    {
        if (is_null($this->transaction) || !is_null($params)) {
            return $this->transaction = new Transaction($this, $params);
        }

        return $this->transaction;
    }

    /**
     * Validate CVD and/or AVS prior to attempting a purchase.
     *
     * @param array $params
     *
     * @return \LuniuMall\Moneris\Response
     */
    public function verify(array $params = [])
    {
        $params = array_merge($params, [
            'type' => 'card_verification',
            'crypt_type' => Crypt::SSL_ENABLED_MERCHANT,
        ]);

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }

    /**
     * Void a transaction.
     *
     * @param \LuniuMall\Moneris\Transaction|string $transaction
     * @param string|null $order
     *
     * @return \LuniuMall\Moneris\Response
     */
    public function void($transaction, $order = null)
    {
        if ($transaction instanceof Transaction) {
            $order = $transaction->order();
            $transaction = $transaction->number();
        }

        $params = [
            'type' => 'purchasecorrection',
            'crypt_type' => Crypt::SSL_ENABLED_MERCHANT,
            'txn_number' => $transaction,
            'order_id' => $order,
        ];

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }

    /**
     * The CardLookup request verifies the applicability of 3DS 2.0 on the card and returns the 3DS Method URL. 
     * That is used for device fingerprinting. This request is optional, it may increase the chance of a frictionless flow.
     * 
     * @param array $params
     *
     * @return \LuniuMall\Moneris\Response
     */
    public function mpiCardLookup(array $params = [])
    {
        $this->isMPI2 = true;
        $params = array_merge($params, [
            'type' => 'card_lookup'
        ]);

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }

    /**
     * The authentication request is used to start the validation process of the card. 
     * The result of this request determines whether 3DS 2.0 is supported by the card and what type of authentication is required.
     * 
     * $template = array (
     *      "order_id" => null,
     *      "data_key" => null,
     *      "cardholder_name" => null,
     *      "pan" => null,
     *      "expdate" => null,
     *      "amount" => null,
     *      "threeds_completion_ind" => null,
     *      "request_type" => null,
     *      "notification_url" => null,
     *      "challenge_windowsize" => null,
     *      "browser_useragent" => null,
     *      "browser_java_enabled" => null,
     *      "browser_screen_height" => null,
     *      "browser_screen_width" => null,
     *      "browser_language" => null,
     *  );
     * @param array $params
     *
     * @return \LuniuMall\Moneris\Response
     */
    public function mpiThreeDSAuthentication(array $params = [])
    {
        $this->isMPI2 = true;
        $default = [
            'threeds_completion_ind' => 'Y', //(Y|N|U) indicates whether 3ds method MpiCardLookup was successfully completed
            'request_type' => '01', // (01=payment|02=recur)
            'browser_java_enabled' => "true",
            'challenge_windowsize' => '02' //(01 = 250 x 400, 02 = 390 x 400, 03 = 500 x 600, 04 = 600 x 400, 05 = Full screen)
        ];
        $params = array_merge($default, $params);
        $params = array_merge($params, [
            'type' => 'threeds_authentication',
        ]);

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }

    /**
     * (Challenge Flow Only)
     * If you get a TransStatus = “C” in your threeDSAuthentication Response, then a form must be built and POSTed to the URL provided.
     * The “action” is retrieved from the ChallengeURL and the “creq” field is retrieved from the ChallengeData.
     * 
     * @param array $params
     *
     * @return \LuniuMall\Moneris\Response
     */
    public function mpiCavvLookup(array $params = [])
    {
        $this->isMPI2 = true;
        $params = array_merge($params, [
            'type' => 'cavv_lookup'
        ]);

        $transaction = $this->transaction($params);

        return $this->process($transaction);
    }
}
