<?php
// DIC configuration

$container = $app->getContainer();

// view renderer
$container['renderer'] = function ($c) {
    $settings = $c->get('settings')['renderer'];
    return new Slim\Views\PhpRenderer($settings['template_path']);
};

// monolog
$container['logger'] = function ($c) {
    $settings = $c->get('settings')['logger'];
    $logger = new Monolog\Logger($settings['name']);
    $logger->pushProcessor(new Monolog\Processor\UidProcessor());
    $logger->pushHandler(new Monolog\Handler\StreamHandler($settings['path'], $settings['level']));
    return $logger;
};

// AWS
use Aws\Common\Aws;
$container['db'] = function ($c) {
    $settings = $c->get('settings')['db'];
    
    // Create a service builder using a configuration file
    $aws = Aws::factory($settings['config_file']);

    // Get the client from the builder by namespace
    $client = $aws->get('DynamoDb');
    return $client;
};
