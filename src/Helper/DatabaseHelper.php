<?php 
namespace Surin\Test\Helper;

class DatabaseHelper {
    
    public static function checkUniqueEmail($email){
        try {
            $d = new \Surin\Test\Data\Database("postgres://postgres:postgrespw@localhost:49153/test");
            $user = $d->getFirstRow("SELECT * FROM users where email = $1",[$email]);
            return empty($user);
        } catch (\Exception $e) {            
            return false;
            error_log("Caught on AddUserForm CheckUniqueEmail".$e->getMessage());
        }
    }

}
