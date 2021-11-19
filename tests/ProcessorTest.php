<?php

use GuzzleHttp\Client;
use LuniuMall\Moneris\Crypt;
use LuniuMall\Moneris\Moneris;
use LuniuMall\Moneris\Response;
use LuniuMall\Moneris\Processor;
use LuniuMall\Moneris\Transaction;

class ProcessorTest extends TestCase
{
    /**
     * The Moneris gateway.
     *
     * @var \LuniuMall\Moneris\Gateway
     */
    protected $gateway;

    /**
     * The Moneris API parameters.
     *
     * @var array
     */
    protected $params;

    /**
     * The Processor instance.
     *
     * @var \LuniuMall\Moneris\Processor
     */
    protected $processor;

    /**
     * The Transaction instance.
     *
     * @var \LuniuMall\Moneris\Transaction
     */
    protected $transaction;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->params = ['environment' => $this->environment];
        $this->gateway = Moneris::create($this->id, $this->token, $this->params);
        // $this->params = [
        //     'type' => 'purchase',
        //     'crypt_type' => Crypt::SSL_ENABLED_MERCHANT,
        //     'order_id' => uniqid('1234-56789', true),
        //     'amount' => '1.00',
        //     'credit_card' => $this->visa,
        //     'expdate' => '2012',
        // ];

        /** for apply pay */
        $this->params = [
            'type' => 'applepay_token_purchase',
            // 'crypt_type' => Crypt::SSL_ENABLED_MERCHANT,
            'order_id' => time(),
            'cust_id' => $this->cust_id,
            'amount' => '1.00',
            'displayName' => $this->display_name,
            'network' => $this->network,
            'version' => $this->version,
            'data' => $this->data,
            'signature' => $this->signature,
            'header' => [
                'public_key_hash' => $this->public_key_hash,
                'ephemeral_public_key' => $this->ephemeral_public_key,
                'transaction_id' => $this->transaction_id
            ],
            
            'dynamic_descriptor' => $this->dynamic_descriptor,
            // 'type' => $this->type
        ];

        $this->transaction = new Transaction($this->gateway, $this->params);
        $this->processor = new Processor(new Client());
    }

    /** @test */
    public function it_can_instantiate_via_the_constructor()
    {
        $processor = new Processor(new Client());

        $this->assertEquals(Processor::class, get_class($processor));
    }

    /** @test */
    public function it_responds_to_an_invalid_transaction_with_the_proper_code_and_status()
    {
        $transaction = new Transaction($this->gateway);

        $response = $this->processor->process($transaction);

        $this->assertFalse($response->successful);
        $this->assertEquals(Response::INVALID_TRANSACTION_DATA, $response->status);
    }

    /** @test */
    public function it_can_submit_a_proper_request_to_the_moneris_api()
    {
        $response = $this->processor->process($this->transaction);
        $this->assertTrue($response->successful);
    }

    /** @test */
    public function it_can_submit_a_avs_secured_request_to_the_moneris_api()
    {
        $params = ['environment' => Moneris::ENV_TESTING, 'avs' => true];
        $gateway = Moneris::create($this->id, $this->token, $params);
        $response = $gateway->purchase([
            'order_id' => uniqid('1234-56789', true),
            'amount' => '1.00',
            'credit_card' => $this->visa,
            'expdate' => '2012',
            'avs_street_number' => '123',
            'avs_street_name' => 'Fake Street',
            'avs_zipcode' => 'X0X0X0',
        ]);

        $this->assertTrue($response->successful);
    }

    /** @test */
    public function it_can_submit_a_cvd_secured_request_to_the_moneris_api()
    {
        $params = ['environment' => Moneris::ENV_TESTING, 'cvd' => true];
        $gateway = Moneris::create($this->id, $this->token, $params);
        $response = $gateway->purchase([
            'order_id' => uniqid('1234-56789', true),
            'amount' => '1.00',
            'credit_card' => $this->visa,
            'expdate' => '2012',
            'cvd' => '111'
        ]);

        $this->assertTrue($response->successful);
    }
}