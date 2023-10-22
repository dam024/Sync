<?php
/**
 *  
 *
 *  @file   Error.php
 *  @brief  structure describing error to return
 *  @author Jaccoud Damien
 *  @date 11.09.22
 *
 *  
 ***********************************************/
namespace Coproman\API;
require_once CLASSES.'/GenericReturnableObject.php';
use \Coproman\API\GenericReturnableObject;

class CPError {
    public $code = 0;
    public $message = "";
    public $userInfos = array();
    function __construct($code = 0, $message = "", $userInfos = array()) {
        $this->code = $code;
        $this->message = $message;
        $this->userInfos = $userInfos;
    }
    /*function __construct() {
        $this->code = 0;
        $this->message = "";
        $this->userInfos = array();
    }*/
}
class SingleError extends GenericReturnableObject {
    public $connection = false;
}

?>