<?php
require 'vendor/autoload.php';

use Laminas\Diactoros\ServerRequestFactory;
use Symfony\Component\Dotenv\Dotenv;
use Surin\Test\Router\Router;
use Surin\Test\User;

$dotenv = new Dotenv(true);
$dotenv->load(__DIR__.'/.env');
$r = ServerRequestFactory::fromGlobals();

$router = new Router($r);

$router->get('/', function($request) {
    $u = new User($request);
    $u->list();
});

$router->get('/user/add', function($request) {
    $u = new User($request);
    $u->add();
});

$router->post('/user/add', function($request) {
    $u = new User($request);
    $u->add();
});
