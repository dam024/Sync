<?php
/**
 *
 *
 *  @file   canAccess.php
 *  @brief  Manage synchronization
 *  @author Jaccoud Damien
 *  @date 02.10.22
 *
 *
 ***********************************************/

 /**
Error codes : 
    - 0 : no error
    - 405 : When informations are missing
    - 406 : Wrong information
    - 407 : Data not allowed
    - 408 : Invalid data
    - 410 : SQL Error
    - 411 : Invalid column name
 */ 


/*
    - Check of access : I should do a better check of access. When the current request doesn't get any result, I should determine if the entity doesn't exist of if I don't have access to it. This way would let me treat the case of non existence in a better way : 
        - In case of Insert : if the parent doesn't exist, push the entity at the end of the array and save the key of the entity in a dictionnary. If I pass through the  entity a second time and the parent still doesn't exist, problem. If I'm not allowed to access to the parent, leave like it is actually. 
        - In case of Update : If the entity doesn't exist, try to insert the entity
        - In case of delete : If the entity doesn't exist, do nothing. Deletion forbidden if the parent exists. 
*/

require_once '../config.php';
require_once CLASSES.'/Synchronisation/Synchronisation.php';
require_once CLASSES.'/Synchronisation/synchronizationFunctions.php';
use Coproman\API\Account\Account;
use Coproman\API\CPError;
use Coproman\API\Account\User;
use Coproman\API\Account\Device;
use Coproman\API\Synchronisation\Synchronisation;

include_once PDO;
include_once FUNCTIONS;

include_once CAN_ACCESS;
updateSynchStatus(SynchStatus::connexionSuccess);


header('Content-type:text/json');
//$array = json_decode($_POST["data"]);
/*foreach ($array["new"] as $key => $value) {
    $array["new"][$key] = json_decode($value);
}*/
$sync = new Synchronisation();
//$sync->modifs = array();
$sync->debug['sendBackAll'] = $_POST['sendBackAll'];
$sync->connection = true;
if(isset($_POST['testConnection']) && $_POST['testConnection']) {//This is a special state to check if we are connected
    $sync->exit();
}
if(isset($_POST["data"])) {
    $data = json_decode($_POST["data"],$associative=true);

    if(!is_array($data)) {
        $data = json_decode($data,$associative=true);
    }
    setInputToSync($_POST['data']);
    updateSynchStatus(SynchStatus::startSync);
    //On traite les objets à ajouter
    //$newObjects = getValueFrom($data,'new',$sync,$sync,"Access denied", 401);
    //$updatedObjects = getValueFrom($data,'update',$sync,"Access denied", 402);
    $changedObjects = getValueFrom($data,'change',$sync,"Access denied", 402);
    $deletedObjects = getValueFrom($data,'delete',$sync,"Access denied", 403);
    //checkAccess($newObjects, $updatedObjects, $deletedObjects);

    $sync->exitIfError();

    $pdo->beginTransaction();
    $sync->debug['nbNew'] = count($changedObjects);


    //--------------    CHANGES   ------------------
    $copy = $changedObjects;
    do {
        $keep = array();
        $length = count($copy);
        foreach($copy as $key => $value) {
            switch(checkAccess($value)) {

                case AccessRightState::Allowed:
                $entity = ((array)$value)[$sync->entityKey];
                $id = ((array)$value)[$entity."_ID"];
                $dbVersion;
                if(canUpdate($entity,$id,((array)$value)[$sync->lastModified],$dbVersion)) {
                    updateEntity($value);
                }
                break;

                case AccessRightState::AllowedAndNotExists:
                insertEntity($value);
                break;

                case AccessRightState::Denied:
                array_push($keep,$value);
                break;
            }
        }
        $copy = $keep;
    } while($length <> count($copy));

    foreach($keep as $key => $value) {
        $entity = ((array)$value)[$sync->entityKey];
        $id = ((array)$value)[$entity."_ID"];
        $unSync = array('reason' => 'Access denied', 'reasonCode' => 405,'id' => $id,'entity' => $entity);
        array_push($sync->return['unsynchronized'],$unSync);
    }
    /*//Insertion
    $copy = $newObjects;
    do {
        $keep = array();
        $length = count($copy);
        foreach ($copy as $key => $value) {
            if(!checkAccess($value,true)) {
                //$unSync = array('reason' => 'Access denied', 'reasonCode' => 405,'id' => $id,'entity' => $entity);
                //array_push($sync->return['unsynchronized'],$unSync);
                array_push($keep,$value);
                continue;
            }
            insertEntity($value);
        }
        $copy = $keep;
    } while($length <> count($copy));
    //Now, we send back the objects we could not synchronise
    foreach($keep as $key => $value) {
        $entity = ((array)$value)[$sync->entityKey];
        $id = ((array)$value)[$entity."_ID"];
        $unSync = array('reason' => 'Access denied', 'reasonCode' => 405,'id' => $id,'entity' => $entity);
        array_push($sync->return['unsynchronized'],$unSync);
    }

    //Gestion des updates
    foreach ($updatedObjects as $key => $value) {
        $entity = ((array)$value)[$sync->entityKey];
        $id = ((array)$value)[$entity."_ID"];
        if(checkAccess($value) != AccessRightState::Allowed) {
            $unSync = array('reason' => 'Access denied', 'reasonCode' => 405,'id' => $id,'entity' => $entity);
            
            array_push($sync->return['unsynchronized'],$unSync);
            continue;
        }
        $dbVersion;

        if(canUpdate($entity,$id,((array)$value)[$sync->lastModified],$dbVersion)) {
            updateEntity($value);
        }
    }*/

    //Gestion des suppressions
    foreach($deletedObjects as $key => $value) {
        $entity = $value['entity'];
        $id = $value[$entity.'_ID'];
        if(!checkAccess($value,false)) {
            $unSync = array('reason' => 'Access denied', 'reasonCode' => 405,'id' => $id,'entity' => $entity);
            array_push($sync->return['unsynchronized'],$unSync);
            continue;
        }
        prepareToDelete($entity,$id,$deviceID);
        $str = "DELETE FROM ".checkColumn($entity)." WHERE ".checkColumn($entity."_ID")." = :id";
        $arrayValues = array('id' => $id);

        //$sync->debug["DeleteRequest"] = $str;
        //$sync->debug["DeleteValues"] = $arrayValues;

        executeSQL($str, $arrayValues, $id,$entity);
    }
    //$pdo->rollback();

    //$sync->appendModifiedObjects(getModifications($deviceID));

    generateModifications($deviceID,(isset($_POST['sendBackAll']) && $_POST['sendBackAll'] == "true"));
    generateDeletedElements($deviceID);
}

updateDeviceLastSync($deviceID); //PUT BACK !!!!!!!!!
if($pdo->inTransaction()) {
    if(!isset($_POST['test']) || !$_POST['test']) {
        $pdo->commit();
    } else {
        $pdo->rollback();
    }
}
updateSynchStatus(SynchStatus::success);
$sync->exit();

function getUseFullKeys($keys,$removeID=false) {
    global $sync;
    $array = array();
    $sync->debug["test"] = $keys;
    foreach($keys as $key=>$value) {
        //echo substr($value,-2)." => ".(substr($value,-2) != "ID").", ".!$removeID." ";
        if($value != $sync->entityKey && $value != $sync->parentEntityKey && (!$removeID || substr($value,-2) != "ID")) {
            //echo "pass ";
            array_push($array,$value);
        }
    }
    return $array;
}

function prepareValue($val) {
    if(is_bool($val)) {
        return (int)$val;
    }
    return $val;
}

function executeSQL($str, $arrayValues,$id,$entity) {
    global $pdo,$sync;
    $insertQuery = $pdo->prepare($str);
    //array_push($sync->modifs,array('entity' => $entity, 'id' => $id));
    try {
        $insertQuery->execute($arrayValues);
    } catch (PDOException $e) {
        $sync->error = new CPError(410,[$e, $insertQuery->queryString]);
        $sync->debug["error"] = $insertQuery->errorInfo();
        $sync->exit();
    }
}

function  updateDeviceLastSync($device) {
    global $pdo;
    $sql = "UPDATE DEVICES SET LAST_SYNC = CURRENT_TIMESTAMP WHERE DEVICE_ID=:id";
    $values = array('id' => $device);
    //executeSQL($sql,$values,'','');
    $query = $pdo->prepare($sql);
    $query->execute($values);
}
function canUpdate($entity,$id,$lastModified,&$lign) {
    global $pdo;
    //We get the date of the last modification of the object
    $sql = "SELECT * FROM ".checkColumn($entity)." WHERE ".checkColumn($entity."_ID")." = :id";
    $values = array('id' => $id);
    $query = $pdo->prepare($sql);
    $query->execute($values);
    if($lign = $query->fetch()) {
        $dbDate = $lign['LAST_MODIF'];
        return $lastModified>$dbDate;
    }
    return true;
}



function generateModifications($device,$sendBackAll) {
    global $pdo,$sync;
    $sync->debug['sendBackAllVerif'] = $sendBackAll;
    //First, get all entities, so that we have one request per table, because the name of the columns are different
    $entities = getEntities();
    //var_dump($entities);
    //Then, for each entity, execute the SQL and build the result
    $parameters = array('device_id' => $device);
    foreach ($entities as $key => $entity) {
        $sql = "SELECT $[table_name].* from  $[table_name]
                left join DEVICES on DEVICE_MODIF = DEVICE_ID
                where DEVICE_USER = (select DEVICE_USER from DEVICES where DEVICE_ID = :device_id) ";
                if(!$sendBackAll) {
                    $sql .= "and LAST_MODIF > (select LAST_SYNC from DEVICES where DEVICE_ID = :device_id)
                    and DEVICE_ID <> :device_id;";
                }
        $query = $pdo->prepare(str_replace('$[table_name]',checkColumn($entity),$sql));
        $sync->debug['requestString'] = $query->queryString;
        try {
            $query->execute($parameters);
        } catch (PDOException $e) {
            $sync->error = new CPError(410,[$e, $query->queryString,$parameters]);
            $sync->debug["error"] = $query->errorInfo();
            $sync->exit();
        }

        while($lign = $query->fetch()) {
            $sync->appendModifiedObject(buildObject($lign,$entity));
        }
    }   
}

function buildObject($sqlResult,$entity) {
    $excludedKeys = array('LAST_SYNC','DEVICE_MODIF',strtoupper($entity).'_USER');
    $arr = array();
    foreach($sqlResult as $key => $value) {
        if(in_array($key,$excludedKeys)) {
            continue;
        }
        $arr[$key] = $sqlResult[strtoupper($key)];
    }
    $arr['entity'] = $entity;
    return $arr;
}
function prepareToDelete($entity,$id,$deviceID) {
    global $pdo,$sync;
    $sql = "INSERT INTO DeleteTracker (`DeleteTracker_DATE`,`DeleteTracker_ENTITY`,`DeleteTracker_ObjID`,`DeleteTracker_DEVICE`)
        SELECT CURRENT_TIMESTAMP as DeleteTracker_DATE, ':entity' as DeleteTracker_ENTITY, ':id' as DeleteTracker_ObjID, :device_id as DeleteTracker_DEVICE FROM :entity
        -- left join DEVICES on DEVICE_ID = :entity_DEVICE
        where :entity_ID = :id";
    $query = $pdo->prepare(str_replace(':entity',checkColumn($entity),$sql));
    try {
        $query->execute(array('id' => $id,'device_id' => $deviceID));
    } catch (PDOException $e) {
        $sync->error = new CPError(410,[$e, $query->queryString,array('id' => $id)]);
        $sync->debug["error"] = $query->errorInfo();
        $sync->exit();
    }
}

function generateDeletedElements($deviceID) {
    global $pdo,$sync;
    $sql = "SELECT DeleteTracker_ENTITY as entity,DeleteTracker_ObjID as id from DeleteTracker
        left join DEVICES on DEVICE_ID = DeleteTracker_DEVICE
        where 
        DeleteTracker_DATE > (select LAST_SYNC from DEVICES where DEVICE_ID = :device_id) 
        and DEVICE_USER = (select DEVICE_USER from DEVICES where DEVICE_ID = :device_id) and DeleteTracker_DEVICE <> :device_id";
    $query = $pdo->prepare($sql);
    try {
        $query->execute(array('device_id' => $deviceID));
    } catch (PDOException $e) {
        $sync->error = new CPError(410,[$e, $query->queryString,array('device_id' => $deviceID)]);
        $sync->debug["error"] = $query->errorInfo();
        $sync->exit();
    }
    $sync->return['deleted'] = $query->fetchAll();
}
?>