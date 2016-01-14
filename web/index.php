<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../providers/TicketProvider.php';
require_once __DIR__.'/../providers/UserProvider.php';
require_once __DIR__.'/../providers/AdminProvider.php';
require_once __DIR__.'/../providers/SecurityProvider.php';

$app = new Silex\Application();
$app["debug"] = true;
$db = ["driver" => "pdo_mysql", "dbname" => "tickets", "host" => "127.0.0.1", "port" => 3306, "user" => "root", "password" => "123456"];

$sc = [
    'login' => ['pattern' => '^/login-form',],
    'secured' => [
        'pattern' => '^/user',
        'form' => ['login_path' => '/login-form', 'check_path' => '/user/login-save'],
        'logout' => ['logout_path' => '/user/logout', 'invalidate_session' => true],
        'users' => $app->share(function () use ($app) { return new \Tickets\SecurityProvider($app['db']); }),
        //'default_target_path' => '/',
        //'always_use_default_target_path' => true,
        //'use_forward' => true
    ]
];
$sa = [['^/ticket.+$', 'ROLE_USER'], ['^/user.+$', 'ROLE_USER'], ['^/admin.+$', 'ROLE_ADMIN']];

$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\TwigServiceProvider(), ['twig.path' => __DIR__.'/../views']);
$app->register(new Silex\Provider\DoctrineServiceProvider(), ['db.options' => $db]);
$app->register(new Silex\Provider\SecurityServiceProvider(), [ 'security.firewalls' => $sc, 'security.access_rules' => $sa ]);
$app->before(function ($request) use ($app) {
    $sh = new \Tickets\SecurityProvider($app["db"]);
    $app["user"] = $sh->loadUserByUsername($app["session"]->get("username"));
});







$app->mount('/ticket', new Tickets\TicketProvider());
$app->mount('/user', new Tickets\UserProvider());
$app->mount('/admin', new Tickets\AdminProvider());

$app->get('/', function () use($app) {
    $user = isset($app["user"]) ? $app["user"]: null;
    if ($user == null) return $app->redirect("/login-form");
    if (array_search('ROLE_ADMIN', $user->getRoles()) !== false) return $app->redirect("/admin/home");
    if (array_search('ROLE_USER', $user->getRoles()) !== false) return $app->redirect("/user/home");
    return $app->redirect("/login-form");
});
$app->get('/login-form', function () use($app) {
    $view = new \Zend_View();
    $form = new \Zend_Form();
    $form->setView($view);
    $form->setAction("/user/login-save");
    $form->addElement("text", "_username", ["label" => "Kullanıcı Adı", "required" => true]);
    $form->addElement("password", "_password", ["label" => "Şifre", "required" => true]);
    $form->addElement("submit", "submit", ["ignore" => true, "label" => "Giriş"]);
    return $app['twig']->render('user/login_form.html.twig', ["form" => $form ]);
});

$app->run();
