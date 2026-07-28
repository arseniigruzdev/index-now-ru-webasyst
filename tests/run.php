<?php

$test_root = sys_get_temp_dir().'/indexnowru-tests-'.bin2hex(random_bytes(6));
mkdir($test_root, 0700, true);

class waException extends Exception {}

class waConfig
{
    public static $path;
    public static function get($name)
    {
        return $name === 'wa_path_config' ? self::$path : null;
    }
}

class waFiles
{
    public static function create($path)
    {
        return is_dir($path) || mkdir($path, 0700, true);
    }
}

class waUtils
{
    public static function varExportToFile($value, $path)
    {
        $contents = "<?php\nreturn ".var_export($value, true).";\n";
        return file_put_contents($path, $contents, LOCK_EX) !== false;
    }
}

class waHtmlControl
{
    const INPUT = 'input';
    const PASSWORD = 'password';
    const CHECKBOX = 'checkbox';
    const HELP = 'help';
    public static function getControl($type, $name, $params = array())
    {
        return json_encode(array('type' => $type, 'name' => $name, 'params' => $params));
    }
}

class waNet
{
    const FORMAT_JSON = 'json';
    const METHOD_POST = 'POST';
}

class waLog
{
    public static $rows = array();
    public static function log($message, $file = null)
    {
        self::$rows[] = array($message, $file);
    }
}

class shopProductModel
{
    const STATUS_ACTIVE = 1;
}

class shopProduct
{
    private $id;
    public function __construct($id)
    {
        $this->id = $id;
    }
    public function getProductUrl()
    {
        return 'https://shop.example/product/'.$this->id.'/';
    }
}

class shopPlugin
{
    protected $settings = array(
        'enabled' => 0,
        'api_key' => '',
        'api_key_clear' => 0,
        'site_id' => '',
        'timeout' => 10,
    );

    public function getSettings($name = null)
    {
        return $name === null ? $this->settings : (isset($this->settings[$name]) ? $this->settings[$name] : null);
    }

    public function saveSettings($settings = array())
    {
        $this->settings = array_merge($this->settings, $settings);
    }

    protected function getSettingsConfig()
    {
        return include dirname(__DIR__).'/lib/config/settings.php';
    }

    public function getControls($params = array())
    {
        $controls = array();
        foreach ($this->getSettingsConfig() as $name => $row) {
            $row['value'] = $this->getSettings($name);
            $controls[$name] = waHtmlControl::getControl($row['control_type'], $name, array_merge($row, $params));
        }
        return $controls;
    }
}

function _wp($value)
{
    return $value;
}

require dirname(__DIR__).'/lib/classes/shopIndexnowruSecret.class.php';
require dirname(__DIR__).'/lib/classes/shopIndexnowruApiClient.class.php';
require dirname(__DIR__).'/lib/shopIndexnowru.plugin.php';

waConfig::$path = $test_root.'/wa-config';
mkdir(waConfig::$path.'/apps/shop', 0700, true);

function assert_true($condition, $message)
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expect_exception(callable $callback, $message)
{
    try {
        $callback();
    } catch (Throwable $exception) {
        return;
    }
    throw new RuntimeException($message);
}

class testIndexnowruApiClient extends shopIndexnowruApiClient
{
    public $net;
    protected function createNet(array $options, array $headers)
    {
        $this->net = new testIndexnowruNet($options, $headers);
        return $this->net;
    }
}

class testIndexnowruNet
{
    public $options;
    public $headers;
    public $query;
    public $user_agent;
    public function __construct($options, $headers)
    {
        $this->options = $options;
        $this->headers = $headers;
    }
    public function userAgent($value)
    {
        $this->user_agent = $value;
    }
    public function query($url, $payload, $method)
    {
        $this->query = compact('url', 'payload', 'method');
        return array('ok' => true);
    }
}

class testIndexnowruPlugin extends shopIndexnowruPlugin
{
    public $client;
    public $fail = false;
    public $client_count = 0;
    protected function createApiClient($api_key, $timeout)
    {
        $this->client_count++;
        if ($this->fail) {
            return new testIndexnowruFailingClient($api_key, $timeout);
        }
        $this->client = new testIndexnowruApiClient($api_key, $timeout);
        return $this->client;
    }
}

class testIndexnowruFailingClient extends shopIndexnowruApiClient
{
    public function submit($site_id, $url)
    {
        throw new waException('response body deliberately not logged', 503);
    }
}

class testInvalidUrlProduct extends shopProduct
{
    public function getProductUrl()
    {
        return 'javascript:alert(1)';
    }
}

try {
    $secret = 'test-secret-'.bin2hex(random_bytes(8));
    $encrypted = shopIndexnowruSecret::encrypt($secret);
    assert_true(strpos($encrypted, shopIndexnowruSecret::PREFIX) === 0, 'Encrypted value prefix is missing.');
    assert_true(strpos($encrypted, $secret) === false, 'Plaintext leaked into the stored value.');
    assert_true(shopIndexnowruSecret::decrypt($encrypted) === $secret, 'Secret round-trip failed.');
    assert_true(is_file(shopIndexnowruSecret::getKeyFilePath()), 'Encryption key file was not created.');

    $api = new testIndexnowruApiClient('api-secret', 9);
    $api->submit('site_123', 'https://shop.example/product/demo/');
    assert_true($api->net->options['timeout'] === 9, 'Timeout was not passed to waNet.');
    assert_true($api->net->options['request_format'] === waNet::FORMAT_JSON, 'JSON request format is missing.');
    assert_true($api->net->headers['Authorization'] === 'Bearer api-secret', 'Bearer header is incorrect.');
    assert_true($api->net->query['url'] === shopIndexnowruApiClient::ENDPOINT, 'Endpoint is incorrect.');
    assert_true($api->net->query['method'] === waNet::METHOD_POST, 'HTTP method is not POST.');
    assert_true($api->net->query['payload']['siteId'] === 'site_123', 'Site ID payload is incorrect.');
    assert_true($api->net->query['payload']['urls'] === array('https://shop.example/product/demo/'), 'URL payload is incorrect.');

    $plugin = new testIndexnowruPlugin();
    $plugin->saveSettings(array(
        'enabled' => 1,
        'api_key' => 'runtime-secret',
        'site_id' => 'site_123',
        'timeout' => 8,
    ));
    $saved = $plugin->getSettings();
    assert_true($saved['api_key'] !== 'runtime-secret', 'Plugin settings contain a plaintext API key.');
    assert_true(strpos($plugin->getControls()['api_key'], 'runtime-secret') === false, 'Settings control exposes the API key.');
    $encrypted_before_blank_save = $saved['api_key'];
    $plugin->saveSettings(array(
        'enabled' => 1,
        'api_key' => '',
        'site_id' => 'site_123',
        'timeout' => 8,
    ));
    assert_true($plugin->getSettings('api_key') === $encrypted_before_blank_save, 'Blank API key input must preserve the saved key.');

    $plugin->productSave(array(
        'data' => array('id' => 101, 'status' => shopProductModel::STATUS_ACTIVE),
        'instance' => new shopProduct(101),
    ));
    assert_true($plugin->client instanceof testIndexnowruApiClient, 'Active product was not submitted.');
    assert_true($plugin->client->net->query['payload']['urls'][0] === 'https://shop.example/product/101/', 'Public product URL is incorrect.');
    $plugin->productSave(array(
        'data' => array('id' => 101, 'status' => shopProductModel::STATUS_ACTIVE),
        'instance' => new shopProduct(101),
    ));
    assert_true($plugin->client_count === 1, 'Duplicate event in one request was not suppressed.');

    $disabled = new testIndexnowruPlugin();
    $disabled->productSave(array(
        'data' => array('id' => 104, 'status' => shopProductModel::STATUS_ACTIVE),
        'instance' => new shopProduct(104),
    ));
    assert_true($disabled->client === null, 'Disabled plugin must not submit products.');

    $inactive = new testIndexnowruPlugin();
    $inactive->saveSettings(array(
        'enabled' => 1,
        'api_key' => 'runtime-secret',
        'site_id' => 'site_123',
        'timeout' => 8,
    ));
    $inactive->productSave(array(
        'data' => array('id' => 102, 'status' => 2),
        'instance' => new shopProduct(102),
    ));
    assert_true($inactive->client === null, 'Inactive product must not be submitted.');

    $invalid_url = new testIndexnowruPlugin();
    $invalid_url->saveSettings(array(
        'enabled' => 1,
        'api_key' => 'runtime-secret',
        'site_id' => 'site_123',
        'timeout' => 8,
    ));
    $invalid_url->productSave(array(
        'data' => array('id' => 105, 'status' => shopProductModel::STATUS_ACTIVE),
        'instance' => new testInvalidUrlProduct(105),
    ));
    assert_true($invalid_url->client === null, 'Non-HTTP product URL must not be submitted.');

    $failing = new testIndexnowruPlugin();
    $failing->saveSettings(array(
        'enabled' => 1,
        'api_key' => 'never-log-this-secret',
        'site_id' => 'site_123',
        'timeout' => 8,
    ));
    $failing->fail = true;
    $failing->productSave(array(
        'data' => array('id' => 103, 'status' => shopProductModel::STATUS_ACTIVE),
        'instance' => new shopProduct(103),
    ));
    assert_true(count(waLog::$rows) === 1, 'Graceful API failure was not logged once.');
    $log_dump = json_encode(waLog::$rows);
    assert_true(strpos($log_dump, 'never-log-this-secret') === false, 'API key leaked into the log.');
    assert_true(strpos($log_dump, 'response body deliberately not logged') === false, 'Response body leaked into the log.');

    expect_exception(function () {
        $plugin = new testIndexnowruPlugin();
        $plugin->saveSettings(array(
            'enabled' => 1,
            'api_key' => 'x',
            'site_id' => 'bad site id',
            'timeout' => 8,
        ));
    }, 'Invalid site ID must be rejected.');

    expect_exception(function () {
        $plugin = new testIndexnowruPlugin();
        $plugin->saveSettings(array(
            'enabled' => 1,
            'api_key' => 'x',
            'site_id' => 'site_123',
            'timeout' => 60,
        ));
    }, 'Invalid timeout must be rejected.');

    $clear = new testIndexnowruPlugin();
    $clear->saveSettings(array(
        'enabled' => 0,
        'api_key' => 'clear-me',
        'site_id' => 'site_123',
        'timeout' => 8,
    ));
    $clear->saveSettings(array(
        'enabled' => 0,
        'api_key' => '',
        'api_key_clear' => 1,
        'site_id' => 'site_123',
        'timeout' => 8,
    ));
    assert_true($clear->getSettings('api_key') === '', 'Clear API key setting failed.');

    shopIndexnowruSecret::deleteKeyFile();
    assert_true(!file_exists(shopIndexnowruSecret::getKeyFilePath()), 'Uninstall key cleanup failed.');

    echo "OK: unit suite passed\n";
} finally {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($test_root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    @rmdir($test_root);
}
