<?php
namespace Tickets;

use Doctrine\DBAL\Connection;
use Silex\Application;
use Silex\ControllerProviderInterface;

class TicketProvider implements ControllerProviderInterface {

    public function connect(Application $app) {
        $module = $app['controllers_factory'];
        $module->get('/create-form', function (Application $app) { return $this->createForm($app); });
        $module->post('/create-save', function (Application $app) { return $this->createSave($app); });
        $module->get('/update-form/{id}', function (Application $app, $id) { return $this->updateForm($app, $id); });
        $module->post('/update-save', function (Application $app) { return $this->updateSave($app); });
        $module->get('/reply-form/{id}', function (Application $app, $id) { return $this->replyForm($app, $id); });
        $module->post('/reply-save', function (Application $app) { return $this->replySave($app); });
        $module->get('/search-form', function (Application $app) { return $this->searchForm($app); });
        $module->post('/search-form', function (Application $app) { return $this->searchForm($app); });
        return $module;
    }
    public function createForm($app){
        $view = new \Zend_View();
        $form = new \Zend_Form();
        $form->setView($view);
        $categories = $app["db"]->fetchAll("SELECT * FROM categories");
        $ticket_tags = new \Zend_Form_Element_Multiselect("tags", ["label" => "Kategori", "required" => true]);
        foreach($categories as $category) $ticket_tags->addMultiOption($category["category_id"], $category["category_name"]);
        $priority = new \Zend_Form_Element_Select("priority", ["label" => "Önem", "required" => true]);
        $priority->addMultiOption("Düşük", "Düşük");
        $priority->addMultiOption("Orta", "Orta");
        $priority->addMultiOption("Yüksek", "Yüksek");
        $priority->addMultiOption("Kritik", "Kritik");
        $form->setAction("/ticket/create-save");
        $form->addElement("text", "title", ["label" => "Başlık", "required" => true]);
        $form->addElement($ticket_tags);
        $form->addElement($priority);
        $form->addElement("textarea", "comment", ["label" => "İleti", "required" => true]);
        $form->addElement("submit", "submit", ["ignore" => true, "label" => "Giriş"]);
        return $app['twig']->render('ticket/create.html.twig', ["form" => $form ]);
    }
    public function createSave($app){
        $user = $app["db"]->fetchAssoc("SELECT * FROM users WHERE login_name = ? ", [$app["user"]->getUserName()]);
        $user_id = $user["user_id"];
        $tags = $app["request"]->get("tags");
        $priority = $app["request"]->get("priority");
        $comment = $app["request"]->get("comment");
        $title = $app["request"]->get("title");
        $date = (new \DateTime())->format("Y-m-d H:i:s");
        $app["db"]->insert("tickets", ["ticket_title" => $title, "priority" => $priority, "status" => "AÇIK", "create_date" => $date, "modify_date" => $date, "user_id" => $user_id]);
        $ticket_id =  $app["db"]->lastInsertId();
        foreach($tags as $tag) {
            $app["db"]->insert("tags", ["ticket_id" => $ticket_id, "category_id" => $tag]);
        }
        $app["db"]->insert("comments", ["comment_text" => $comment, "comment_date" => $date, "user_id" => $user_id, "ticket_id" => $ticket_id]);
        return $app->redirect("/user/home");
    }
    public function updateForm($app, $id){
        $ticket = $app["db"]->fetchAssoc("SELECT * FROM tickets WHERE ticket_id = ?", [$id]);
        $comments = $app["db"]->fetchAll("SELECT * FROM comments JOIN users USING(user_id) WHERE ticket_id = ? ORDER BY comment_date DESC", [$id]);
        $view = new \Zend_View();
        $form = new \Zend_Form();
        $form->setView($view);
        $form->setAction("/ticket/update-save");
        $form->addElement(new \Zend_Form_Element_Hidden("ticket_id", ["value" => $id]));
        $form->addElement("textarea", "comment", ["label" => "İleti", "required" => true]);
        $form->addElement("submit", "submit", ["ignore" => false, "label" => "Kaydet"]);
        return $app['twig']->render('ticket/update.html.twig', ["form" => $form, "ticket" => $ticket, "comments" => $comments ]);
    }
    public function updateSave($app){
        $user = $app["db"]->fetchAssoc("SELECT * FROM users WHERE login_name = ? ", [$app["user"]->getUserName()]);
        $user_id = $user["user_id"];
        $ticket_id = $app["request"]->get("ticket_id");
        $comment = $app["request"]->get("comment");
        $date = (new \DateTime())->format("Y-m-d H:i:s");
        $app["db"]->insert("comments", ["comment_text" => $comment, "comment_date" => $date, "user_id" => $user_id, "ticket_id" => $ticket_id]);
        $app["db"]->update("tickets", ["modify_date" => $date, "status" => "AÇIK"], ["ticket_id" => $ticket_id]);
        return $app->redirect("/user/home");
    }
    public function replyForm($app, $id){
        $ticket = $app["db"]->fetchAssoc("SELECT * FROM tickets WHERE ticket_id = ?", [$id]);
        $comments = $app["db"]->fetchAll("SELECT * FROM comments JOIN users USING(user_id) WHERE ticket_id = ? ORDER BY comment_date DESC", [$id]);
        $view = new \Zend_View();
        $form = new \Zend_Form();
        $form->setView($view);
        $form->setAction("/ticket/reply-save");
        $form->addElement(new \Zend_Form_Element_Hidden("ticket_id", ["value" => $id]));
        $form->addElement("textarea", "comment", ["label" => "İleti", "required" => true]);
        $form->addElement("submit", "submit", ["ignore" => false, "label" => "Kaydet"]);
        $form->addElement("submit", "submit", ["ignore" => false, "label" => "Kapat"]);
        return $app['twig']->render('ticket/reply.html.twig', ["form" => $form, "ticket" => $ticket, "comments" => $comments ]);
    }
    public function replySave($app){
        $user = $app["db"]->fetchAssoc("SELECT * FROM users WHERE login_name = ? ", [$app["user"]->getUserName()]);
        $user_id = $user["user_id"];
        $ticket_id = $app["request"]->get("ticket_id");
        $comment = $app["request"]->get("comment");
        $date = (new \DateTime())->format("Y-m-d H:i:s");
        $status = $app["request"]->get("submit") == "Kapat" ? "KAPALI" : "AÇIK";
        $app["db"]->insert("comments", ["comment_text" => $comment, "comment_date" => $date, "user_id" => $user_id, "ticket_id" => $ticket_id]);
        $app["db"]->update("tickets", ["modify_date" => $date, "status" => $status], ["ticket_id" => $ticket_id]);
        return $app->redirect("/admin/home");
    }
    public function searchForm($app){
        $params = [];
        $sql = "SELECT t.*, u.user_name, group_concat(concat(c.category_name, ',')) as category_name ";
        $sql = $sql." FROM tickets t JOIN users u USING (user_id) LEFT JOIN tags v USING (ticket_id) LEFT JOIN categories c USING (category_id)";
        $sql = $sql." WHERE true ";
        if (!empty($app["request"]->get("priority"))){
            $sql = $sql." AND t.priority = ?";
            $params[] = $app["request"]->get("priority");
        }
        if (!empty($app["request"]->get("tags"))){
            $sql = $sql." AND EXISTS (SELECT NULL FROM tags i WHERE i.ticket_id = t.ticket_id AND i.category_id = ?)";
            $params[] = $app["request"]->get("tags");
        }
        if (!empty($app["request"]->get("title"))){
            $sql = $sql." AND t.ticket_title like ?";
            $params[] = '%'.$app["request"]->get("title")."%";
        }
        $sql = $sql." GROUP BY t.ticket_id ORDER BY modify_date DESC";
        $tickets = $app["db"]->fetchAll($sql, $params);

        $view = new \Zend_View();
        $form = new \Zend_Form();
        $form->setView($view);
        $form->setAction("/ticket/search-form");
        $categories = $app["db"]->fetchAll("SELECT * FROM categories");
        $ticket_tags = new \Zend_Form_Element_Select("tags", ["label" => "Kategori", "required" => true]);
        $ticket_tags->addMultiOption(0, "Hepsi");
        foreach($categories as $category) $ticket_tags->addMultiOption($category["category_id"], $category["category_name"]);
        $priority = new \Zend_Form_Element_Select("priority", ["label" => "Önem", "required" => true]);
        $priority->addMultiOption("", "Hepsi");
        $priority->addMultiOption("Düşük", "Düşük");
        $priority->addMultiOption("Orta", "Orta");
        $priority->addMultiOption("Yüksek", "Yüksek");
        $priority->addMultiOption("Kritik", "Kritik");
        $form->addElement("text", "title", ["label" => "Başlık", "required" => false]);
        $form->addElement($priority);
        $form->addElement($ticket_tags);
        $form->addElement("submit", "submit", ["ignore" => true, "label" => "Ara"]);
        return $app['twig']->render('ticket/search.html.twig', ["form" => $form, "tickets" => $tickets ]);
    }
}