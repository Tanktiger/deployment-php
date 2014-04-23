<?php
$config = array(
    //Server only for remote access via ssh
    'server' => array (
        'host' => '',
        'user' => '',
        'password' => '',
        'port' => '22'
    ),
    'replaceFiles' => array(
        //relative to project root
        'payment' => array(
            'oldName' => 'config/autoload/payment.local.dist.php',
            'newName' => 'config/autoload/payment.local.php',
            'variables' => array(
                //paypal sdk
                '<!sdk.mode!>' => 'sandbox',
                '<!sdk.username!>' => '',
                '<!sdk.password!>' => '',
                '<!sdk.signature!>' => '',
                '<!sdk.appid!>' => '',
                //paypal main
                '<!paypal.receiver!>' => '',
                '<!paypal.cancelUrl!>' => '',
                '<!paypal.returnUrl!>' => '',
                '<!paypal.ipnUrl!>' => '/paypal-ipn-listener',
            )
        ),
        'doctrine' => array(
            'oldName' => 'config/autoload/doctrineconnection.local.dist.php',
            'newName' => 'config/autoload/doctrineconnection.local.php',
            'variables' => array(
                '<!orm_default.host!>' => '',
                '<!orm_default.port!>' => '',
                '<!orm_default.user!>' => '',
                '<!orm_default.password!>' => '',
                '<!orm_default.dbname!>' => '',
            )
        ),
    ),

    'composer' => true,

    'permissions' => array(
        'data/DoctrineORMModule/Proxy' => 0777,
        'public/uploads/images' => 0777
    )

);