<?php
//namespace Coproman\API\Synchronisation;

require_once '../config.php';
require_once CLASSES.'/Synchronisation/Synchronisation.php';
include_once PDO;
include_once FUNCTIONS;
use Coproman\API\Synchronisation\Synchronisation;



/**
 * Search in the object's array for the entity's parent entity
 */
function getParentEntity($object) {
    global $sync;
    $key = $sync->parentEntityKey;
    return ((array)$object)[$key];
}
/**
 * Search in the object's array for the parent's ID
 */
function getParentKey($object,$entity) {
    //global $sync;
    /*if(is_null($object)) {
        $sync->error = new CPError(430,'Null object',$object);
        $sync->exit();
    }*/
    $parentEntity = getParentEntity($object);
    if(!is_null($parentEntity)) {
        $keyParent = ((array)$object)[$entity."_".strtoupper($parentEntity)];
        return $keyParent;
    }
    return null;
}
/**
 * Indicate which right the current user has on an entity
 */
enum AccessRightState {
    ///User has full access to the entity
    const Allowed = 1;
    ///User doesn't have access to the entity. Returned when the parent or the entity doesn't belong to the logged  user
    const Denied = 0;
    ///User has access to insert the entity, but can't modify it. This value appears when the parent entity belongs to the logged user, but the current entity does not exist in the data base. 
    const AllowedAndNotExists = 2;
};
/**
 * Check if we can access to an entity. 
 * 
 * We can access to an entity if it belongs to the user and if it's parent exists. Otherwise, access is denied. This function also indicate if the entity has to be inserted or has to be modified. 
 * 
 * @param object The entity we want to check the access
 * 
 * @return An AccessRightState object indicating the state of the access
 */
function checkAccess($object) {
    global $pdo, $sync, $userID;
    $entity = ((array)$object)[$sync->entityKey];
    $parentEntity = getParentEntity($object);
    $entityID = ((array)$object)[$entity."_ID"];
    $parentID = getParentKey($object,$entity);
//echo $entityID.'<br/>';
    //Check if we have access
    if(!is_null($parentEntity)) {
        $sql = "SELECT 0 as SORT, 'parent' as TYPE, count(*) as NB FROM ";
        $sql .= checkColumn($parentEntity);
        $sql .= " WHERE ";
        $sql .= checkColumn($parentEntity."_USER")." = :user ";
        $sql .= " AND ";
        $sql .= checkColumn($parentEntity."_ID");
        $sql .= " = :parentID UNION ";

        $parameters = array('user' => $userID,'parentID' => $parentID, 'entityID' => $entityID);
    } else {
        $sql = "SELECT 0 as SORT, 'parent' as TYPE, 1 as NB FROM dual UNION ";
        $parameters = array('user' => $userID,'entityID' => $entityID);
    }
    $sql .= "SELECT 1 as SORT,'entity' as TYPE, count(*) as NB FROM ";
    $sql .= checkColumn($entity);
    $sql .= " WHERE ";
    $sql .= checkColumn($entity."_USER")." = :user ";
    $sql .= " AND ";
    $sql .= checkColumn($entity."_ID");
    $sql .= " = :entityID";
//echo $sql.'<br/>';
    $nbLinesRequest = $pdo->prepare($sql);
    $nbLinesRequest->execute($parameters);

    while($lign = $nbLinesRequest->fetch()) {
        //var_dump($lign);
        //echo '<br/>';
        if($lign['TYPE'] == 'parent' && $lign['NB'] == 0) {
            return AccessRightState::Denied;
        }
        if($lign['TYPE'] == 'entity' && $lign['NB'] == 0) {
            return AccessRightState::AllowedAndNotExists;
        }
    }
//echo '<br/>';
    //exit;
    return AccessRightState::Allowed;
}

/**
 * Check that a column name doesn't contain any character that could be associated to an SQL injection
 * 
 * @param {String} $val a candidate for the column name
 */
function checkColumn($val) {
    global $sync;
    $t = preg_match("/[^a-z_]{1,}/i",$val);//"[a-zA-Z_]", $val);
    if($t == 1) {
        $sync->debug["colCheckVal"] = $t;
        $sync->error = new CPError(411, "Invalid column name ".$val);
        $sync->exit();
    }
    return $val;
}

function insertEntity($value) {
    global $sync,$userID,$deviceID;
    $entity = ((array)$value)[$sync->entityKey];
    $id = ((array)$value)[$entity."_ID"];


    $keys = getUseFullKeys(array_keys((array)$value));
    $arrayValues = array();
    $sync->debug["insertKeys"] = $keys;
    $str = "INSERT INTO ".$entity." (";
    //$arrayValues["entity"] = ((array)$value)[$sync->entityKey];
    for($i = 0;$i < count($keys);$i++) {
        if($i > 0) {
            $str .= ",";
        }
        $str .= checkColumn($keys[$i]);
        //$arrayValues["col".$i] = $keys[$i];
    }
    $str .= ",".checkColumn($entity)."_USER";
    $str .= ",DEVICE_MODIF";//We add the columns for compatibility
    $str .= ") VALUES (";
    for($i = 0;$i < count($keys);$i++) {
        if($i > 0) {
            $str .= ",";
        }
        $str .= ":val".$i."";
        $arrayValues["val".$i] = prepareValue(((array)$value)[$keys[$i]]);
    }
    $str .= ",:user";
    $str .= ",:device_modif";
    $arrayValues["user"] = $userID;//Value come from canAccess.php
    //$arrayValues['last_modif'] = time();
    $arrayValues['device_modif'] = $deviceID;//Value come from canAccess.php
    $str .= ")";

    executeSQL($str, $arrayValues,$id, $entity);
}

function updateEntity($value) {
    global $sync,$deviceID,$userID;
    $entity = ((array)$value)[$sync->entityKey];
    $id = ((array)$value)[$entity."_ID"];
    $keys = getUseFullKeys(array_keys((array)$value), true);


    $arrayValues = array();
    $sync->debug["updateKeys"] = $keys;
    $sync->debug["updateAll"] = $value;

    $str = "UPDATE ".checkColumn($entity)." SET ";
    for($i = 0; $i < count($keys); $i++) {
        if($i > 0) {
            $str .= ",";
        }
        $str .= checkColumn($keys[$i])." = :val".$i;
        $arrayValues["val".$i] = prepareValue(((array)$value)[$keys[$i]]);
    }
    //Add the device for the modification
    $str .= ",DEVICE_MODIF"." = :device_id";
    $arrayValues['device_id'] = $deviceID;
    $str .= " WHERE ".checkColumn($entity."_ID")." = :id";
    $arrayValues["id"] = $id;

    $sync->debug["UpdateRequest"] = $str;
    $sync->debug["UpdateValues"] = $arrayValues;

    executeSQL($str,$arrayValues,$id, $entity);
}
?>