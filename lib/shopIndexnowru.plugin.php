<?php

class shopIndexnowruPlugin extends shopPlugin
{
    private static $handled_products = array();

    /**
     * Shop-Script product_save event handler.
     *
     * The hook is deliberately fail-open: network or configuration failures
     * must never prevent Shop-Script from saving a product.
     *
     * @param array $params Official product_save event payload.
     * @return void
     */
    public function productSave($params)
    {
        if (!$this->isEnabled() || !is_array($params)) {
            return;
        }

        $data = isset($params['data']) && is_array($params['data']) ? $params['data'] : array();
        $product_id = isset($data['id']) ? (int)$data['id'] : 0;
        if ($product_id <= 0 || isset(self::$handled_products[$product_id])) {
            return;
        }

        $active_status = defined('shopProductModel::STATUS_ACTIVE')
            ? (int)constant('shopProductModel::STATUS_ACTIVE')
            : 1;
        $status = isset($data['status']) ? (int)$data['status'] : 0;
        if ($status !== $active_status) {
            return;
        }

        $site_id = trim((string)parent::getSettings('site_id'));
        $timeout = (int)parent::getSettings('timeout');
        if (!$this->isValidSiteId($site_id) || $timeout < 3 || $timeout > 30) {
            return;
        }

        try {
            $api_key = $this->getApiKey();
            if ($api_key === '') {
                return;
            }

            $product = isset($params['instance']) ? $params['instance'] : null;
            if (!is_object($product) || !method_exists($product, 'getProductUrl')) {
                $product = new shopProduct($product_id);
            }
            $url = (string)$product->getProductUrl(true, true, true);
            if (!$this->isPublicHttpUrl($url)) {
                return;
            }

            self::$handled_products[$product_id] = true;
            $this->createApiClient($api_key, $timeout)->submit($site_id, $url);
        } catch (Throwable $exception) {
            $code = (int)$exception->getCode();
            if ($code < 100 || $code > 599) {
                $code = 0;
            }
            waLog::log(
                sprintf(
                    'Index-Now.ru submission failed (product_id=%d, code=%d, error_type=%s).',
                    $product_id,
                    $code,
                    get_class($exception)
                ),
                'shop/plugins/indexnowru.log'
            );
        }
    }

    public function getControls($params = array())
    {
        $controls = parent::getControls($params);
        $config = $this->getSettingsConfig();

        if (isset($config['api_key'])) {
            $api_params = array_merge($config['api_key'], $params);
            $api_params['value'] = '';
            $api_params['placeholder'] = parent::getSettings('api_key')
                ? _wp('API key is saved securely. Leave empty to keep it.')
                : _wp('Paste an Index-Now.ru API key');
            $controls['api_key'] = waHtmlControl::getControl(
                waHtmlControl::PASSWORD,
                'api_key',
                $api_params
            );
        }

        return $controls;
    }

    public function saveSettings($settings = array())
    {
        $settings = is_array($settings) ? $settings : array();
        $enabled = !empty($settings['enabled']);
        $site_id = isset($settings['site_id']) ? trim((string)$settings['site_id']) : '';
        $timeout = isset($settings['timeout']) ? (int)$settings['timeout'] : 10;
        $new_api_key = isset($settings['api_key']) ? trim((string)$settings['api_key']) : '';
        $clear_api_key = !empty($settings['api_key_clear']);

        if ($site_id !== '' && !$this->isValidSiteId($site_id)) {
            throw new waException(_wp('Site ID may contain only letters, digits, hyphens, and underscores.'));
        }
        if ($timeout < 3 || $timeout > 30) {
            throw new waException(_wp('Request timeout must be between 3 and 30 seconds.'));
        }
        if (strlen($new_api_key) > 4096) {
            throw new waException(_wp('API key is too long.'));
        }

        $stored_api_key = (string)parent::getSettings('api_key');
        if ($clear_api_key) {
            $stored_api_key = '';
        } elseif ($new_api_key !== '') {
            $stored_api_key = shopIndexnowruSecret::encrypt($new_api_key);
        }

        if ($enabled && ($site_id === '' || $stored_api_key === '')) {
            throw new waException(_wp('Enter both an API key and a Site ID before enabling automatic submission.'));
        }

        $settings['enabled'] = $enabled ? 1 : 0;
        $settings['api_key'] = $stored_api_key;
        $settings['api_key_clear'] = 0;
        $settings['site_id'] = $site_id;
        $settings['timeout'] = $timeout;

        $result = parent::saveSettings($settings);
        if ($clear_api_key) {
            shopIndexnowruSecret::deleteKeyFile();
        }
        return $result;
    }

    protected function createApiClient($api_key, $timeout)
    {
        return new shopIndexnowruApiClient($api_key, $timeout);
    }

    protected function getApiKey()
    {
        $stored = (string)parent::getSettings('api_key');
        return $stored === '' ? '' : shopIndexnowruSecret::decrypt($stored);
    }

    private function isEnabled()
    {
        return (bool)parent::getSettings('enabled');
    }

    private function isValidSiteId($site_id)
    {
        return (bool)preg_match('/^[A-Za-z0-9_-]{1,128}$/D', (string)$site_id);
    }

    private function isPublicHttpUrl($url)
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));
        return $scheme === 'http' || $scheme === 'https';
    }
}
