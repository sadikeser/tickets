<?php
namespace Tickets;

use Doctrine\DBAL\Connection;
use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;

class UserProvider implements ControllerProviderInterface {
    public function checkAuth($app){
    }

    public function connect(Application $app) {
        $module = $app['controllers_factory'];
        $module->get('/home', function (Application $app) { return $this->homeForm($app); });
        $module->get('/login-form', function (Application $app) { return $this->loginForm($app); });
        $module->post('/login-save', function (Application $app) { return $this->loginCheck($app); });
        $module->get('/logout', function (Application $app) { return $this->logoutPerform($app); });
        $module->get('/profile', function (Application $app) { return $this->profileForm($app); });
        $module->post('/save', function (Application $app) { return $this->profileSave($app); });
        return $module;
    }
    /**
     * Checks and performs login
     * @param $app Application
     * @return string
     */
    public function homeForm(Application $app){
        /** @var Connection $db */
        $this->checkAuth($app);
        $db = $app["db"];
        $username = $app["user"]->getUserName();
        $user = $db->fetchAssoc("SELECT * FROM users WHERE login_name = ? ", [$username]);
        $tickets = $db->fetchAll("SELECT * FROM tickets WHERE user_id = ? ORDER BY modify_date DESC", [ $user["user_id"] ]);
        return $app['twig']->render('user/home_form.html.twig', ["user" => $user, "tickets" => $tickets ]);
    }
    
    public function loginForm($app) {
        $view = new \Zend_View();
        $form = new \Zend_Form();
        $form->setView($view);
        $form->setAction("/user/login-save");
        $form->addElement("text", "_username", ["label" => "Kullanıcı Adı", "required" => true]);
        $form->addElement("password", "_password", ["label" => "Şifre", "required" => true]);
        $form->addElement("submit", "submit", ["ignore" => true, "label" => "Giriş"]);
        return $app['twig']->render('user/login_form.html.twig', ["form" => $form ]);
    }


    public function logoutPerform(Application $app){
        $app["session"]->set("__USER_ID__", 0);
        return $app->redirect("/");
    }

    /**
     * Checks and performs login
     * @param $app Application
     * @return string
     */
    public function profileForm(Application $app){
        $this->checkAuth($app);
        $username = $app["user"]->getUserName();
        $db = $app["db"];
        $user = $db->fetchAssoc("SELECT * FROM users WHERE login_name = ? ", [$username]);
        $view = new \Zend_View();
        $form = new \Zend_Form();
        $form->setView($view);
        $form->setAction("/user/save");
        $form->addElement("text", "login_name", ["label" => "Kullanıcı Adı", "required" => true])->setDefault("login_name", $user["login_name"]);
        $form->addElement("password", "login_pass", ["label" => "Şifre", "required" => true]);
        $form->addElement("password", "login_pass_again", ["label" => "Şifre Tekrar", "required" => true]);
        $form->addElement("text", "user_name", ["label" => "Ad Soyad", "required" => true])->setDefault("user_name", $user["user_name"]);
        $form->addElement("submit", "submit", ["ignore" => true, "label" => "Kaydet"]);
        return $app['twig']->render('user/profile_form.html.twig', ["user" => $user, "form" => $form]);
    }

    public function profileSave(Application $app){
        $this->checkAuth($app);
        /** @var Connection $db */
        $username = $app["user"]->getUserName();
        $db = $app["db"];
        $user = $db->fetchAssoc("SELECT * FROM users WHERE login_name = ? ", [$username]);
        $user_id = $user["user_id"];
        if (!empty($app["request"]->get("login_pass")) && $app["request"]->get("login_pass") == $app["request"]->get("login_pass_again")){
            $encoder = $app['security.encoder_factory']->getEncoder($app["user"]);
            $password = $encoder->encodePassword($app["request"]->get("login_pass"), $app["user"]->getSalt());
            $db->executeUpdate("UPDATE users set user_name = ?, login_name = ?, login_pass = ? WHERE user_id = ?", [$app["request"]->get("user_name"), $app["request"]->get("login_name"), $password, $user_id]);
            return $app->redirect("/user/home");
        }

        if (empty($app["request"]->get("login_pass")) && empty($app["request"]->get("login_pass_again"))){
            $db->executeUpdate("UPDATE users set user_name = ?, login_name = ? WHERE user_id = ?", [$app["request"]->get("user_name"), $app["request"]->get("login_name"), $user_id]);
            return $app->redirect("/user/home");
        }
        return $app->redirect("/user/profile");
    }
}