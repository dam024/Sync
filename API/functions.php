<?php
/**
 *  
 *
 *  @file   functions.php
 *  @brief  Contain functions used at many places of the API
 *  @author Jaccoud Damien
 *  @date 10.09.22
 *
 *  
 ***********************************************/
require_once '../config.php';
include_once PDO;

require_once CLASSES.'/CPError.php';
use Coproman\API\CPError;

$synchRequest = -1;

function getValueFrom($array,$key,$obj,$msg,$code = 405) {
    if(isset($array[$key])) {
        return $array[$key];
    } else {
        $obj->error = new CPError($code,$msg);
        return null;
    }
}


//  MARK: Synchronisation 
enum SynchStatus {
    const attempt = 'attempt';
    const connexionSuccess = 'connexionSuccess';
    const startSync = 'startSync';
    const success = 'success';
};

function newSynchronisation($userID, $deviceID) {
    global $pdo, $synchRequest;
    /*//Generate the primary key
    $prim = $pdo->query("SELECT max(SR_ID) as pk FROM SynchronisationRequests");
    if($lign = $prim->fetch()) {
        $synchRequest = (is_null($lign['pk'])) ? 1 : $lign['pk']+1;
    }
    var_dump($synchRequest);*/
    //Get the data if they exist

    //Insert request
    //$insert = $pdo->prepare("INSERT INTO SynchronisationRequests (SR_ID, SR_USER, SR_DEVICE, SR_STATUS) VALUES (:id, :user, :device, :status)");
    //$insert->execute(array('id' => $synchRequest, 'user' => $userID, 'device' => $deviceID, 'status' => SynchStatus::attempt));
    //var_dump($pdo->lastInsertId());
    $insert = $pdo->prepare("INSERT INTO SynchronisationRequests (SR_USER, SR_DEVICE, SR_STATUS) VALUES (:user, :device, :status)");
    $insert->execute(array('user' => $userID, 'device' => $deviceID, 'status' => SynchStatus::attempt));
    $synchRequest = $pdo->lastInsertId();
}
/*
    Update synchronisation informations
*/
function updateSynchStatus($status) {
    global $pdo, $synchRequest;

    $update = $pdo->prepare("UPDATE SynchronisationRequests SET SR_STATUS = :status WHERE SR_ID = :id");
    $update->execute(array('status' => $status, 'id' => $synchRequest));
}
/*
    Update sychronisation to set input
*/
function setInputToSync($input) {
    global $pdo, $synchRequest;

    $update = $pdo->prepare("UPDATE SynchronisationRequests SET SR_InputData = :input WHERE SR_ID = :id");
    $update->execute(array('input' => $input, 'id' => $synchRequest));
}
/*
    Update Synchronisation to set output
*/
function setOutputToSync($output) {
    global $pdo, $synchRequest;

    $update = $pdo->prepare("UPDATE SynchronisationRequests SET SR_OutputData = :output WHERE SR_ID = :id");
    $update->execute(array('output' => $output, 'id' => $synchRequest));
}
/**
 * Get all entities of the data model
 */
function getEntities() {
    global $pdo;
    $result = $pdo->query("SELECT Entity_TableName FROM Entites ORDER BY ENTITY_ORDER,ENTITY_TableName");
    $rslt = array();
    while($lign = $result->fetch()) {
        array_push($rslt,$lign['ENTITY_TABLENAME']);
    }
    return $rslt;
}



?>
