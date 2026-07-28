<?php

return array(
    'name'        => 'Index-Now.ru',
    'description' => 'Submits public Shop-Script product URLs to Index-Now.ru after publishing or updating.',
    // Local-build placeholder. Webasyst Store requires the numeric developer ID.
    // build.ps1 -Marketplace refuses to build while this value is 0.
    'vendor'      => 0,
    'version'     => '1.0.0',
    'handlers'    => array(
        'product_save' => 'productSave',
    ),
);

