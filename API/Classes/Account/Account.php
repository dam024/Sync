<?php
/**
 *
 *
 *  @file   Account.php
 *  @brief  Object representation of an account
 *  @author Jaccoud Damien
 *  @date 11.09.22
 *
 *
 ***********************************************/
namespace Coproman\API\Account;

require_once CLASSES.'/CPError.php';
require_once CLASSES.'/GenericReturnableObject.php';
require_once CLASSES.'/Account/User.php';
require_once CLASSES.'/Account/Device.php';
use \Coproman\API\GenericReturnableObject;
use \Coproman\API\CPError;

class Account extends GenericReturnableObject {
	public $user;//Informations about the current user
	public $device;//Informations about the current device
	public $error;//Errors
	function __construct() {
		$this->error = new CPError();
	}
}



?>
