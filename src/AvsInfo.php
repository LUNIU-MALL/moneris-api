<?php

namespace LuniuMall\Moneris;

/**
 * LuniuMall\Moneris\AvsInfo
 *
 */
class AvsInfo
{
    use Preparable;

    /**
     * The AvsInfo data.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Create a new AvsInfo instance.
     *
     * @param array $params
     *
     * @return void
     */
    public function __construct(array $params = [])
    {
        $this->data = $this->prepare($params, [
            // Vault AVS Info fields
            ['property' => 'street_number', 'key' => 'street_number'],
            ['property' => 'street_name', 'key' => 'street_name'],
            ['property' => 'zipcode', 'key' => 'zipcode'],

            // Additional AVS Info fields
            ['property' => 'email', 'key' => 'email'],
            ['property' => 'hostname', 'key' => 'hostname'],
            ['property' => 'browser', 'key' => 'browser'],
            ['property' => 'shiptocountry', 'key' => 'shiptocountry'],
            ['property' => 'shipmethod', 'key' => 'shipmethod'],
            ['property' => 'merchprodsku', 'key' => 'merchprodsku'],
            ['property' => 'custip', 'key' => 'custip'],
            ['property' => 'custphone', 'key' => 'custphone'],
        ]);
    }

    /**
     * Create a new AvsInfo instance.
     *
     * @param array $params
     *
     * @return \LuniuMall\Moneris\AvsInfo
     */
    public static function create(array $params = [])
    {
        return new static($params);
    }

    /**
     * Retrieve a property off of the class.
     *
     * @param string $property
     *
     * @throws \InvalidArgumentException
     * @return mixed
     */
    public function __get($property)
    {
        if (property_exists($this, $property)) {
            return $this->$property;
        }

        if (array_key_exists($property, $this->data)) {
            return $this->data[$property]; // 允许返回 null
        }

        throw new \InvalidArgumentException('['.get_class($this).'] does not contain a property named ['.$property.']');
    }

    /**
     * Set a property that exists on the class.
     *
     * @param string $property
     * @param mixed $value
     *
     * @throws \InvalidArgumentException
     * @return void
     */
    public function __set($property, $value)
    {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        } elseif (!is_null($this->data)) {
            $this->data[$property] = $value;
        } else {
            throw new \InvalidArgumentException('['.get_class($this).'] does not contain a property named ['.$property.']');
        }
    }

    public function toArray()
    {
        $params = [
            'avs_street_number' => $this->street_number,
            'avs_street_name' => $this->street_name,
            'avs_zipcode' => $this->zipcode,
            'avs_email' => $this->email,
            'avs_hostname' => $this->hostname,
            'avs_browser' => $this->browser,
            'avs_shiptocountry' => $this->shiptocountry,
            'avs_shipmethod' => $this->shipmethod,
            'avs_merchprodsku' => $this->merchprodsku,
            'avs_custip' => $this->custip,
            'avs_custphone' => $this->custphone,
        ];
        return $params;
    }
}
