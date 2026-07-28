<?php

return array(
    'enabled' => array(
        'title'        => _wp('Enable automatic submission'),
        'description'  => _wp('Submit the public product URL after an active product is saved.'),
        'label'        => _wp('Enabled'),
        'value'        => 0,
        'control_type' => waHtmlControl::CHECKBOX,
    ),
    'api_key' => array(
        'title'        => _wp('API key'),
        'description'  => _wp('Create a key at index-now.ru. The saved value is encrypted and is never written to plugin logs.'),
        'value'        => '',
        'size'         => 55,
        'maxlength'    => 4096,
        'control_type' => waHtmlControl::PASSWORD,
    ),
    'api_key_clear' => array(
        'title'        => _wp('Remove saved API key'),
        'label'        => _wp('Delete the encrypted key when settings are saved'),
        'value'        => 0,
        'control_type' => waHtmlControl::CHECKBOX,
    ),
    'site_id' => array(
        'title'        => _wp('Site ID'),
        'description'  => _wp('Identifier of the site connected in the Index-Now.ru account.'),
        'value'        => '',
        'size'         => 55,
        'maxlength'    => 128,
        'control_type' => waHtmlControl::INPUT,
    ),
    'timeout' => array(
        'title'        => _wp('Request timeout'),
        'description'  => _wp('HTTPS request timeout in seconds, from 3 to 30.'),
        'value'        => 10,
        'size'         => 5,
        'maxlength'    => 2,
        'control_type' => waHtmlControl::INPUT,
    ),
    'external_service' => array(
        'title'        => _wp('External service'),
        'value'        => _wp('When enabled, the plugin sends the API key in the Authorization header, the configured site ID, and the public product URL to https://index-now.ru/api/v1/submit. Privacy: https://index-now.ru/privacy. Terms: https://index-now.ru/terms. URL submission does not guarantee crawling or indexing.'),
        'control_type' => waHtmlControl::HELP,
    ),
);
