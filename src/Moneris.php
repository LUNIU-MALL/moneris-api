<?php

namespace LuniuMall\Moneris;

/**
 * LuniuMall\Moneris\Moneris
 *
 * @property-read string $id
 * @property-read string $token
 * @property-read string $environment
 * @property-read string $params
 */
class Moneris
{
    use Gettable;

    const ENV_LIVE    = 'live';
    const ENV_STAGING = 'staging';
    const ENV_TESTING = 'testing';

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
     * The extra parameters needed for Moneris.
     *
     * @var array
     */
    protected $params;

    /**
     * The Moneris API Token.
     *
     * @var string
     */
    protected $token;

    /**
     * Create a new Moneris instance.
     *
     * @param string $id
     * @param string $token
     * @param array $params
     *
     * @return void
     */
    public function __construct($id = '', $token = '', array $params = [])
    {
        $this->id = $id;
        $this->token = $token;
        $this->environment = isset($params['environment']) ? $params['environment'] : self::ENV_LIVE;
        $this->params = $params;
    }

    /**
     * Create a new Moneris instance.
     *
     * @param string $id
     * @param string $token
     * @param array $params
     *
     * @return \LuniuMall\Moneris\Gateway
     */
    public static function create($id = '', $token = '', array $params = [])
    {
        $moneris = new static($id, $token, $params);

        return $moneris->connect();
    }

    /**
     * Create and return a new Gateway instance.
     *
     * @return \LuniuMall\Moneris\Gateway
     */
    public function connect()
    {
        $gateway = new Gateway($this->id, $this->token, $this->environment);

        if (isset($this->params['avs'])) {
            $gateway->avs = boolval($this->params['avs']);
        }

        if (isset($this->params['cvd'])) {
            $gateway->cvd = boolval($this->params['cvd']);
        }

        if (isset($this->params['cof'])) {
            $gateway->cof = boolval($this->params['cof']);
        }

        if (isset($this->params['cavv'])) {
            $gateway->cavv = boolval($this->params['cavv']);
        }

        if (isset($this->params['avsCodes'])) {
            $gateway->avsCodes = $this->params['avsCodes'];
        }

        if (isset($this->params['cvdCodes'])) {
            $gateway->cvdCodes = $this->params['cvdCodes'];
        }

        if (isset($this->params['cavvCodes'])) {
            $gateway->cavvCodes = $this->params['cavvCodes'];
        }

        if (isset($this->params['countryCodes'])) {
            $gateway->countryCodes = isset($this->params['countryCodes']) ? $this->params['countryCodes'] : 'CA';
        }

        return $gateway;
    }
}
