<?php
/**
 *  
 *
 *  @file   accessTestDb.php
 *  @brief  Send SQL requests to obtain a feedback from the test database
 *  @author Jaccoud Damien
 *  @date 10.09.22
 *
 *  
 ***********************************************/
namespace Coproman\AccessTestDB;
require_once '../config.php';

include_once PDO;
require_once CLASSES.'/GenericReturnableObject.php';
require_once CLASSES.'/CPError.php';
use \Coproman\API\GenericReturnableObject;
use Coproman\API\CPError;
use Coproman\API\SingleError;


class SQLRequestResult extends GenericReturnableObject {
    public $sqlResult;
}
/*
    Error codes : 
    - 405 : Access denied
    - 406 : Illegal request
    - 407 : SQL error

    Input: POST request
    Required parameters
        - test: true -> set the environment in the test system
        - sqlRequest: a valid SQL request -> An SQL request used to verify the data already modified. Only SELECT requests are allowed. 
*/
header('Content-type:text/json');
header('Access-Control-Allow-Origin: *');

$pdo->setAttribute(\PDO::ATTR_ERRMODE,\PDO::ERRMODE_EXCEPTION);

$sqlRequestResult = new SQLRequestResult();

//Check access
if(!$IS_TEST_SYSTEM) {
    accessError();
}

//Check if the request is valid
if(isset($_POST['sqlRequest']) && $_POST['sqlRequest'] == true) {
    $sql = $_POST['sqlRequest'];
    if(checkRequest($sql)) {
        try {
            $result = $pdo->query($sql);
            $sqlRequestResult->sqlResult = $result->fetchAll();
            //$sqlRequestResult->debug = "Don't forget to restrict access";
        } catch (Exception $e) {
            $sqlRequestResult->error = new CPError(407,'SQL Error', $e);
        } catch (PDOException $e) {
            $sqlRequestResult->error = new CPError(407,'SQL Error', $e);
        }
    } else {
        $sqlRequestResult->error = new CPError(406,'Illegal request');
        $sqlRequestResult->exit();
    }
} else {
    accessError();
}

$sqlRequestResult->exit(true);

/**
 * Check if the SQL request is allowed. 
 * 
 * This function restricts the requests that can be executed. Only SELECT are allowed! In addition, only ONE request is allowed at each time. 
 */
function checkRequest($request) {
    $command = explode(' ',strtoupper(trim($request)))[0];
    return $command == "SELECT";
}
/**
 * Generate the access error and return
 */
function accessError() {
    global $sqlRequestResult;
    $sqlRequestResult->error = new CPError(405,'Access denied');
    $sqlRequestResult->exit();
}
?>