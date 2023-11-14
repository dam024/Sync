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
//require_once PDO;
require_once FUNCTIONS;
//use Coproman\API\CPError;
use function \setOutputToSync;

abstract class GenericReturnableObject {
    public $error;
    public $debug;

    public function exit() {
        global $pdo, $IS_TEST_SYSTEM, $TEST_INIT_FILE,$GENERAL_DEBUG;

        //$this->debug['testGeneric'] = $pdo->query("SELECT * FROM Users")->fetchAll();
        \setOutputToSync($this->json());

        $this->debug['testSessionName'] = $TEST_INIT_FILE;
        if($pdo->inTransaction()) {
            $pdo->rollback();
        }
        \setOutputToSync($this->json());

        //Export the DB if we are in a test session
        if($IS_TEST_SYSTEM && isset($TEST_INIT_FILE)) {
            //$this->debug['testInput'] = explode(\PHP_EOL,file_get_contents($TEST_INIT_FILE));
            $val = \saveDBState($TEST_INIT_FILE,$output);
            //$this->debug['output'] = $output;
            if($val != null) {
                $this->debug['saveDb'] = $val;
            }
        }
        if(count($GENERAL_DEBUG) > 0) {
            $this->debug['GENERAL_DEBUG'] = $GENERAL_DEBUG;
        }
        if(!$IS_TEST_SYSTEM || is_null($this->debug) || count((array)$this->debug) == 0 /*|| count(get_object_vars($this->debug)) == 0*/) {
            unset($this->debug);
        }
        if(!$IS_TEST_SYSTEM && isset($this->error)) {
            $this->error->userInfos = array();//We delete the debug infos when we are not in test mode!
        }
        echo $this->json();
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
