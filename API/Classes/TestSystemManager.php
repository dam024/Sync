<?php
/**
 *
 *
 *  @file   TestSystemManager.php
 *  @brief  Object representation of what will be returned by the test database PDO initialization 
 *  @author Jaccoud Damien
 *  @date 04.11.23
 *
 *
 ***********************************************/

namespace Coproman\System\PDO;

require_once CLASSES.'/CPError.php';
require_once CLASSES.'/GenericReturnableObject.php';
use \Coproman\API\GenericReturnableObject;
use \Coproman\API\CPError;

/**
 * Status: 
 * 	- 0: Error happened
 * 	- 1: functional
/**
 * 
 */
class TestSystemManager extends GenericReturnableObject {
	public $status;
	public $message;
}
?>