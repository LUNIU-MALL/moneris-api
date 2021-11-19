<?php

use LuniuMall\Moneris\Moneris;

class TestCase extends PHPUnit_Framework_TestCase
{
    /**
     * @var string
     */
    protected $amex;

    /**
     * @var string
     */
    protected $environment;

    /**
     * @var string
     */
    protected $id;

    /**
     * @var string
     */
    protected $mastercard;

    /**
     * @var string
     */
    protected $token;

    /**
     * @var string
     */
    protected $visa;

    /**
     * Set up the test environment.
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();

        $this->amex = '373599005095005';
        $this->mastercard = '5454545454545454';
        $this->visa = '4242424242424242';

        $this->id = 'store3';
        $this->token = 'yesguy';
        $this->environment = Moneris::ENV_LIVE;

        /** Apple Pay */
        $this->cust_id = 'nqa cust id';
        $this->display_name = "Visa 1117";
        $this->network = "Visa";
        $this->version = "EC_v1";
        $this->data = "Vva7mlHMaaZb18al4gCbGMMzt0OoXAvrpA5TfPGokeKsdwzRBgeBvVkqgLC3nJ8J2jZy469eAKmhotSrYeZ1sE7QNeK0Mh+oeZyHINWky5dmwP6EpBYNGX49EPgYG43ScVggv3I0qCRCIgK4FWb8oGWgfLMGT/iMVYD3rIf4Q/Th9v0+Gm+tz1mETfXi3N4C8r4jNm5jyO5rRLkBJ9WKaKjrABBhujwFMDNBL/zwfJqy3csBPFHEwAx4D2vR0wonZUvgIcV+EqG5jef/kURmE97AzCMYCXPLIXbJ54mloog1C8vo+84B7313WIi8ebhMuByZDvCTAM0KgyktcWGNwRnVDaWVFJq4cScvOE6ESBuyBybquYc=";
        $this->signature = "MIAGCSqGSIb3DQEHAqCAMIACAQExDzANBglghkgBZQMEAgEFADCABgkqhkiG9w0BBwEAAKCAMIID4zCCA4igAwIBAgIITDBBSVGdVDYwCgYIKoZIzj0EAwIwejEuMCwGA1UEAwwlQXBwbGUgQXBwbGljYXRpb24gSW50ZWdyYXRpb24gQ0EgLSBHMzEmMCQGA1UECwwdQXBwbGUgQ2VydGIEluYy4xCzAJBgNVBAYTAlVTMFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEwhV37evWx7Ihj2jdcJChIY3HsL1vLCg9hGCV2Ur0pUEbg0IO2BHzQH6DMx8cVMP36zIg1rrV1O/0komJPnwPE6OCAhEwggINMAwGA1UdEwEB/wQCMAz6Pmr2y9g4CJDcgs3apjMIIC7jCCAnWgAwIBAgIISW0vvzqY2pcwCgYIKoZIzj0EAwIwZzEbMBkGA1UEAwwSQXBwbGUgUm9vdCBDQSAtIEczMSYwJAYDVQQLDB1BcHBsZSBDZXJ0aWZpY2F0aW9uIEF1dGhvcml0eTETMBEGA1UECgwKQXBwbGUgSW5jLjELMAkGA1UEBhMCVVMwHhcNMTQwNTA2MjM0NjMwWhcNMjkwNTA2MjM0NjMwWjB6MS4wLAYDVQQDDCVBcHBsZSBBcHBsaWNhdGlvbiBJbnRlZ3JhdGlvbiBDQSAtIEczMSYwJAYDVQQLDB1BcHBsZSBDZXJ0aWZpY2F0aW9uIEF1dGhvcml0eTETMBEGA1UECgwKQXBwbGUgSW5jLjELMAkGA1UEBhMCVVMwWTATBgcqhkjOPQIBBggqhkjOPQMBBwNCAATwFxGEGddkhdUaXiWBB3bogKLv3nuuTeCN/EuT4TNW1WZbNa4i0Jd2DSJOe7oI/XYXzojLdrtmcL7I6CmE/1RFo4H3MIH0MEYGCCsGAQUFBwEBBDowODA2BggrBgEFBQcwAYYqaHR0cDovL29jc3AuYXBwbGUuY29tL29jc3AwNC1hcHBsZXJvb3RjYWczMB0GA1UdDgQWBBQj8knET5Pk7yfmxPYobD+iu/0uSzAPBgNVHRMBAf8EBTADAQH/MB8GA1UdIwQYMBaAFLuw3qFYM4iapIqZ3r6966/ayySrMDcGA1UdHwQwMC4wLKAqoCiGJmh0dHA6Ly9jcmwuYXBwbGUuY29tL2FwcGxlcm9vdGNhZzMuY3JsMA4GA1UdDwEB/wQEAwIBBjAQBgoqhkiG92NkBgIOBAIFADAKBggqhkjOPQQDAgNnADBkAjA6z3KDURaZsYb7NcNWymK/9Bft2Q91TaKOvvGcgV5Ct4n4mPebWZ+Y1UENj53pwv4CMDIt1UQhsKMFd2xd8zg7kGf9F3wsIW2WT8ZyaYISb1T4en0bmcubCYkhYQaZDwmSHQAAMYIBizCCAYcCAQEwgYYwejEuMCwGA1UEAwwlQXBwbGUgQXBwbGljYXRpb24gSW50ZWdyYXRpb24gQ0EgLSBHMzEmMCQGA1UECwwdQXBwbGUgQ2VydGlmaWNhdGlvbiBBdXRob3JpdHkxEzARBgNVBAoMCkFwcGxlIEluYy4xCzAJBgNVBAYTAlVTAghMMEFJUZ1UNjANBglghkgBZQMEAgEFAKCBlTAYBgkqhkiG9w0BCQMxCwYJKoZIhvcNAQcBMBwGCSqGSIb3DQEJBTEPFw0yMTExMTkxODUxMTZaMCoGCSqGSIb3DQEJNDEdMBswDQYJYIZIAWUDBAIBBQChCgYIKoZIzj0EAwIwLwYJKoZIhvcNAQkEMSIEIHq1tbcOK9qtscjYAbnBvYCESVPe9ffouVxj6wjFp5wqMAoGCCqGSM49BAMCBEYwRAIgMXg8LdOcb1t3Wanch6PRiL6cjmto8zXlZi60G7LmKMYCIFkBVh7yjsi1smSgV72SxngwtK36C8tst0sGBTidvjvCAAAAAAAA";
        $this->public_key_hash = "cElbrbg4mHJqIcTO+n/u4whQ+oF2tyiI=";
        $this->ephemeral_public_key = "MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQo7br6inWJoIwn/L3ZipkhUb+qJogsBnoRuuXVhGI5ncM0DJjJ1mflbcxxl07KqygFDyaoA==";
        $this->transaction_id = "c238dae0525c92098bd4b7cd53be89d26c48d5dc710c2a33";
        $this->dynamic_descriptor = "nqa-dd";
        // $this->type = 'PKPaymentMethodTypeCredit';
    }
}
