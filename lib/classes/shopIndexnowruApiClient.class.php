<?php

class shopIndexnowruApiClient
{
    const ENDPOINT = 'https://index-now.ru/api/v1/submit';

    private $api_key;
    private $timeout;

    public function __construct($api_key, $timeout)
    {
        $this->api_key = (string)$api_key;
        $this->timeout = (int)$timeout;
    }

    /**
     * @param string $site_id Index-Now.ru site identifier.
     * @param string $url     Public absolute product URL.
     * @return mixed Decoded JSON response, or null for a 204 response.
     * @throws waNetException
     */
    public function submit($site_id, $url)
    {
        $options = array(
            'format'                => waNet::FORMAT_JSON,
            'request_format'        => waNet::FORMAT_JSON,
            'timeout'               => $this->timeout,
            'verify'                => true,
            'expected_http_code'    => array(200, 201, 202, 204),
            'tolerate_empty_body_request' => true,
        );
        $headers = array(
            'Authorization' => 'Bearer '.$this->api_key,
            'Accept'        => 'application/json',
        );

        $net = $this->createNet($options, $headers);
        $net->userAgent('Index-Now.ru-Webasyst/1.0.0');

        return $net->query(
            self::ENDPOINT,
            array(
                'siteId' => (string)$site_id,
                'urls'   => array((string)$url),
            ),
            waNet::METHOD_POST
        );
    }

    /**
     * Separated for deterministic local tests; production uses Webasyst waNet.
     */
    protected function createNet(array $options, array $headers)
    {
        return new waNet($options, $headers);
    }
}

