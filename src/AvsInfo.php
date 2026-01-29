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
            ['property' => 'street_number', 'key' => 'avs_street_number'],
            ['property' => 'street_name', 'key' => 'avs_street_name'],
            ['property' => 'zipcode', 'key' => 'avs_zipcode'],

            // Additional AVS Info fields
            ['property' => 'email', 'key' => 'avs_email'],
            ['property' => 'hostname', 'key' => 'avs_hostname'],
            ['property' => 'browser', 'key' => 'avs_browser'],
            ['property' => 'shiptocountry', 'key' => 'avs_shiptocountry'],
            ['property' => 'shipmethod', 'key' => 'avs_shipmethod'],
            ['property' => 'merchprodsku', 'key' => 'avs_merchprodsku'],
            ['property' => 'custip', 'key' => 'avs_custip'],
            ['property' => 'custphone', 'key' => 'avs_custphone'],
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

        if (isset($this->data[$property]) && !is_null($this->data[$property])) {
            return $this->data[$property];
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
}
