<?php
namespace Surin\Test;


use Surin\Test\Data\Database;
use Devanych\View\Renderer;

class User {
    
    private $d = null;
 
    public function __construct($request) {
        $this->request = $request;
        $this->d = new Database("postgres://postgres:postgrespw@localhost:49153/test");
        $this->renderer = new Renderer('c:\\wamp64\\www\\Test\\src\\View\\');
    }

    private function checkUniqueEmail($email){
        try {
            $user = $this->d->getFirstRow("SELECT * FROM users where email = $1",[$email]);
            return empty($user);
        } catch (\Exception $e) {
            return false;
            error_log("Caught $e");
        }
        
    }

    public function list(){
        
        $column = isset($this->request->getQueryParams()['order'])?$this->request->getQueryParams()['order'][0]['column']:0;
        $order = isset($this->request->getQueryParams()['order'])?$this->request->getQueryParams()['order'][0]['dir']:'asc';
        
        switch ($column) {
            case 0:
                $column = 'firstname';
                break;
            case 1:
                $column = 'lastname';
                break;
            case 2:
                $column = 'email';
                break;            
            default:                
                break;
        }

        $allUser = $this->d->getAllRows("SELECT firstname,lastname,email,created_at FROM users ORDER BY $column $order");
        if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'){
            echo json_encode(['data' => $allUser]);die;
        }
        $content = $this->renderer->render('list', [
            'users' => $allUser
        ]);
        echo $content;

    }

    public function add(){
        try {
            
            $addUserform = new \Surin\Test\Form\AddUserForm('user','post','/user/add','form-group');
            $form = $addUserform->getForm();
            
            if ($form->isSubmitted()) {
                if($form->validate()){
                    $user = $this->request->getParsedBody();
                    $query = "Insert into users (firstname, lastname, email) values ($1,$2,$3)";
                    $this->d->Insert($query , [$user['firstname'] , $user['lastname'], $user['email']]);
                    header("Location: /");
                }
            } 

        } catch (\Exception $e) {
            echo $e->getCode()."  =>   ".$e->getMessage();die;
            echo "Data not saved, please check logs";
            error_log("Caught $e");
        }
        
        echo $this->renderer->render('add', [
            'form' => $form
        ]);
    }

}