<?php
/**
 *
 *
 *  @file   canAccess.php
 *  @brief  Manage who the accounts
 *  @author Jaccoud Damien
 *  @date 10.09.22
 *
 *
 ***********************************************/
namespace Coproman\API;

require_once CLASSES.'/CPError.php';
require_once PDO;
require_once FUNCTIONS;
//use Coproman\API\CPError;

abstract class GenericReturnableObject {
    public $error;
    public $debug;

    public function exit() {
        global $pdo,$synchRequest;
        setOutputToSync($this->json());
        unset($this->debug);
        echo $this->json();
        if($pdo->inTransaction()) {
            $pdo->rollback();
        }
        setOutputToSync($this->json());
        exit();
    }
    public function exitIfError() {
        if(isset($this->error) && $this->error->code != 0) {
            $this->exit();
        }
    }
    public function json() {
        return json_encode(get_object_vars($this));
    }
}



?>
