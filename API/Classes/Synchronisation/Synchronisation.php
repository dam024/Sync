<?php
/**
 *
 *
 *  @file   Synchronization.php
 *  @brief  Object representation of what will be returned by the Synchronization API
 *  @author Jaccoud Damien
 *  @date 08.10.22
 *
 *
 ***********************************************/

namespace Coproman\API\Synchronisation;

require_once CLASSES.'/CPError.php';
require_once CLASSES.'/GenericReturnableObject.php';
use \Coproman\API\GenericReturnableObject;
use \Coproman\API\CPError;

class Synchronisation extends GenericReturnableObject {
	public $entityKey = "entity";
	public $lastModified = "LAST_MODIF";
	public $connection = false;
	//Name of the parentEntity
	public $parentEntityKey = "parentEntity";
	//public $debug = array();
	public $return = array('unsynchronized'=> array(),'modified' => array(), 'deleted' => array(),'conflicts' => array());

	public function exit() {
		//unset($this->debug);
		unset($this->entityKey);
		unset($this->parentEntityKey);
		unset($this->lastModified);
		parent::exit();
	}

	public function appendModifiedObject($newObj) {
		array_push($this->return['modified'],$newObj);
	}
	public function appendModifiedObjects($newObjs) {
		$this->debug["arrayFirst"] = $this->return['modified'];
		$this->debug['arraySecond'] = $newObjs;
		$this->return['modified'] = array_merge($this->return['modified'],$newObjs);
	}
}

?>