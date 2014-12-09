<?php

// define the classes we will use 
// -- note for now these are rather simplistic -- we should protect the properties, use setters and getters and constructors

class Msgin {
	public $transactionType;
	public $userID;
	public $securityToken;
	public $payload;
}
class Msgout {
	public $status;
	public $messages;
	Public $payload;
}
class DbRecord {
	public $iD;
	public $lastName;
	public $firstName;
	public $blurb;
	public $conControl;
}

// 
// initialize msgout -- note better practice use constructor and getters and setters
// 

$msgout = new Msgout;
$msgout->messages = array();
$msgout->messages[] = "Beginning Transaction";
$msgout->payload = new DbRecord;

//
// set up our own error handler
//

set_error_handler("customErrorHandler");

//
// get inputs
// 

$msgin = new Msgin;
$msgin->payload = new DbRecord;

getPostedInput($msgin,$msgout);
$msgout->messages[] = "Transaction Type: " . $msgin->transactionType;

//
// echo back the message sequence number
//

$msgout->msqseq = $msgin->msgseq;

//
// sleep as requested
//

$msgout->messages[] = "Sleeping for: " . $msgin->sleep;
usleep($msgin->sleep*1000);


//
// you will need to provide userid, password and database in the $conn= statement below

// 
// Get and check database connection
// 

$conn  = new mysqli("localhost", "your db userid", "your db password","your db");
if ($conn->connect_error) {trigger_error('Database connection failed: '  . $conn->connect_error, E_USER_ERROR);}

// 
// mysql set autocommit to false so that a transaction will be begun with first SQL statement
// 

if (!$conn->autocommit(FALSE)) {trigger_error('Set autocommit failed' . $sql . ' Error: ' . $conn->error, E_USER_ERROR);}

//
// check user authentication
//

if (!checkUserAuthentication($msgin)) {trigger_error('User authentication error ', E_USER_ERROR);}

//
// check user authorization
//

if (!checkUserAuthorization($msgin)) {trigger_error('User authorization error ',  E_USER_ERROR);}

//
// switch on transactionType
//

switch ($msgin->transactionType) {
	case 'CRTUSR':
	createUser($conn,$msgin,$msgout);
	break;
	
	case 'RETUSR':
	retrieveUser($conn,$msgin,$msgout,'current');
	break;
	
	case 'RETUSRN':
	retrieveUser($conn,$msgin,$msgout,'next');
	break;
	
	case 'RETUSRP':
	retrieveUser($conn,$msgin,$msgout,'previous');
	break;
	
	case 'UPDUSR':
	updateUser($conn,$msgin,$msgout);
	break;
	
	case 'DELUSR':
	deleteUser($conn,$msgin,$msgout);
	break;
	
	default:
	trigger_error('Invalid transaction type: '  . $msgin->transactionType, E_USER_ERROR);
	break;
}

//
// send results back to requester
//

sendMsgout($msgout);

//
// tidy up and exit
//

$conn->close();
exit; // end main line

function getPostedInput($msgin,$msgout) {
	$rawInput = file_get_contents("php://input");
	$msgout->messages[] = "Raw: " . $rawInput;
	$inputObj = json_decode($rawInput);
	$msgin->transactionType = isset($inputObj->transactionType) ? $inputObj->transactionType : "";
	$msgin->msgseq = isset($inputObj->msgseq) ? $inputObj->msgseq : 0;
	$msgin->sleep = isset($inputObj->sleep) ? $inputObj->sleep : 0;
	$msgin->holdLocks = isset($inputObj->holdLocks) ? $inputObj->holdLocks : 0;
	$msgin->userID = isset($inputObj->userID) ? $inputObj->userID : "";
	$msgin->securityToken = isset($inputObj->securityToken) ? $inputObj->securityToken : "";
	$msgin->payload->iD = isset($inputObj->payload->iD) ? $inputObj->payload->iD : "";
	$msgin->payload->firstName = isset($inputObj->payload->firstName) ? $inputObj->payload->firstName : "";
	$msgin->payload->lastName = isset($inputObj->payload->lastName) ? $inputObj->payload->lastName : "";
	$msgin->payload->blurb = isset($inputObj->payload->blurb) ? $inputObj->payload->blurb : "";
	$msgin->payload->conControl = isset($inputObj->payload->conControl) ? $inputObj->payload->conControl : "";
}; // end getPostedinput

function createUser($conn,$msgin,$msgout) {
	$stmt = $conn->prepare("INSERT INTO  users SET FirstName = ? , LastName = ?, Blurb = ?, ConControl = 0");
	if (!$stmt) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}
//
// Bind the input variables to the prepared statement
//
	if (!$stmt->bind_param('sss',
		$msgin->payload->firstName,
		$msgin->payload->lastName,
		$msgin->payload->blurb)) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}
// execute the prepared statement
		if (!$stmt->execute()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}

	$msgout->payload = $msgin->payload;

// get the iD which is autoincremented

	$newiD = $conn->insert_id;
	$msgout->payload->iD = $newiD;

	if (!$conn->commit()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}

	$msgout->payload->conControl = 0;

	$msgout->messages[] = "Create Successful -- new ID is:" . $newiD;
	$msgout->status = "OK";
} // end createUser

function retrieveUser($conn,$msgin,$msgout,$type) {
	$msgout->messages[] = "Beginning Retrieve";

// 
// build and execute query to retrieve single record (as iD is primary key)
// 

	$userRecord = new DbRecord;
	switch ($type) {
		case 'current':
			$stmt_select = $conn->prepare("SELECT ID, FirstName, LastName, Blurb, ConControl FROM users where ID = ?");
			# code...
			break;
		
		case 'next':
			$stmt_select = $conn->prepare("SELECT ID, FirstName, LastName, Blurb, ConControl FROM users where ID > ? ORDER BY ID LIMIT 1");
			# code...
			break;
		
		case 'previous':
			$stmt_select = $conn->prepare("SELECT ID, FirstName, LastName, Blurb, ConControl FROM users where ID < ? ORDER BY ID DESC LIMIT 1");
			# code...
			break;
		
		default:
			trigger_error('Type not valid: '  . $type, E_USER_ERROR);
			# code...
			break;
	}
	$stmt_select->bind_param('i',$msgin->payload->iD);
	if (!$stmt_select->execute()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}
	if (!$stmt_select->bind_result(
		$userRecord->iD,
		$userRecord->firstName,
		$userRecord->lastName,
		$userRecord->blurb,
		$userRecord->conControl
		)) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}

//
// we'll be doing subsequent database calls so we need to free up the buffer with a call to store_result
// note that mysqli differs from msql in this regard
//

		if (!$stmt_select->store_result()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}

//
// fetch the first row
//
	if( $stmt_select->num_rows !== 0) {

		if (!$stmt_select->fetch()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}

//
// set the msgout payload values
//

		$msgout->payload->iD = $userRecord->iD;
		$msgout->payload->firstName = $userRecord->firstName;
		$msgout->payload->lastName = $userRecord->lastName;
		$msgout->payload->blurb = $userRecord->blurb;
		$msgout->payload->conControl = $userRecord->conControl;
		$msgout->status = "OK";

		$msgout->messages[] = "Retrieval Successful";
	} else {
		$msgout->messages[] = "Not found on retrieve " . $type . " ID: " . $msgin->payload->iD;
		$msgout->status = 'Not Found';
	}

} // end retrieveUser

function updateUser($conn,$msgin,$msgout) {
	$msgout->messages[] = "Beginning Update";

// 
// build and execute query to retrieve single record (as iD is primary key)
// SELECT .. FOR UPDATE to lock the row we will be updating
// 

	$userRecord = new DbRecord;
	$stmt_select = $conn->prepare("SELECT FirstName, LastName, Blurb, ConControl FROM users where ID= ? FOR UPDATE");
	$stmt_select->bind_param('i',$msgin->payload->iD);
	if (!$stmt_select->execute()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}
	if (!$stmt_select->bind_result($userRecord->firstName,
		$userRecord->lastName,
		$userRecord->blurb,
		$userRecord->conControl
		)) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}

//
// we'll be doing subsequent database calls so we need to free up the buffer with a call to store_result
// note that mysqli differs from msql in this regard
//

	if (!$stmt_select->store_result()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}

//
// go to sleep to hold locks as requested
//

	$msgout->messages[] = "Holding locks for: " . $msgin->holdLocks;
	sleep($msgin->holdLocks);


//
// fetch the first row
//
	if( $stmt_select->num_rows !== 0) {

		if (!$stmt_select->fetch()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}

	//
	// validate that the record has not changed since last retrieved by the user by checking the ConControl value
	// note this is by convention -- all programs that update a row should increment ConControl
	// if the record has been changed notify the user and roll back the transaction 
	// and return the current data base values in the messages
	//

		if (($msgin->payload->conControl + 0) === ($userRecord->conControl + 0)) {
			$stmt = $conn->prepare("UPDATE users SET FirstName = ? , LastName = ?, Blurb = ?, ConControl = ? Where ID = ?");
			if (!$stmt) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}

	// Increment the conControl value which we will store in the database to indicate the row has been updated

			$conControl = $msgin->payload->conControl + 1;

	// Bind the input variables to the prepared statement

			if (!$stmt->bind_param('sssis',
				$msgin->payload->firstName,
				$msgin->payload->lastName,
				$msgin->payload->blurb,
				$conControl,
				$msgin->payload->iD)) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}

	// execute the prepared statement

				if (!$stmt->execute()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}

	// commit our updated values which ends the transaction

			if (!$conn->commit()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}
			$msgout->payload = $msgin->payload;
			$msgout->payload->conControl = $userRecord->conControl+1;
			$msgout->messages[] = "Update Successful";
			$msgout->status = "OK";
		} else {
			$msgout->messages[] = "Another user has updated the record since last retrieved";
			$msgout->messages[] = "Retrieved values were: " . $userRecord->firstName . " " . $userRecord->lastName . " " . $userRecord->blurb;
			$msgout->messages[] = "Your update has been rolled back. Please press Update to try again"; 
			$msgout->status = "Conflict";
			$msgout->payload->firstName = $userRecord->firstName;
			$msgout->payload->lastName = $userRecord->lastName;
			$msgout->payload->blurb = $userRecord->blurb;
			$msgout->payload->conControl = $userRecord->conControl;

	// rollback our updated values which ends the transaction

			if (!$conn->rollback()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}
		}
	} else {
		$msgout->messages[] = "On update row must have been deleted, ID =: " . $msgin->payload->iD;
		$msgout->status = 'Not Found';
		if (!$conn->rollback()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}
	}
} // end updateUser

function deleteUser($conn,$msgin,$msgout) {
	$msgout->messages[] = "Beginning Delete";

// 
// build and execute query to retrieve single record (as iD is primary key)
// SELECT .. FOR UPDATE to lock the row we will be updating
// 

	$userRecord = new DbRecord;
	$stmt_select = $conn->prepare("SELECT FirstName, LastName, Blurb, ConControl FROM users where ID= ? FOR UPDATE");
	$stmt_select->bind_param('i',$msgin->payload->iD);
	if (!$stmt_select->execute()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}
	if (!$stmt_select->bind_result($userRecord->firstName,
		$userRecord->lastName,
		$userRecord->blurb,
		$userRecord->conControl
		)) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}

//
// we'll be doing subsequent database calls so we need to free up the buffer with a call to store_result
// note that mysqli differs from mysql in this regard
//

	if (!$stmt_select->store_result()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}

//
// go to sleep to hold locks as requested
//

	$msgout->messages[] = "Holding locks for: " . $msgin->holdLocks;
	sleep($msgin->holdLocks);

//
// fetch the first row
//
	if( $stmt_select->num_rows !== 0) {

		if (!$stmt_select->fetch()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}

	//
	// validate that the record has not changed since last retrieved by the user by checking the ConControl value
	// note this is by convention -- all programs that update a row should increment ConControl
	// if the record has been changed notify the user and roll back the transaction 
	// and return the current data base values in the messages
	//

		if (($msgin->payload->conControl + 0) === ($userRecord->conControl + 0)) {
			$stmt = $conn->prepare("DELETE FROM users where ID= ?");
			$stmt->bind_param('i',$msgin->payload->iD);
			$msgout->messages[] = "About to delete: " . $msgin->payload->iD;
			if (!$stmt->execute()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}
			if (!$conn->commit()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}

//
// set the msgout payload values
//

			$msgout->payload->iD = "";
			$msgout->payload->firstName = "";
			$msgout->payload->lastName = "";
			$msgout->payload->blurb = "";
			$msgout->payload->conControl = "";
			$msgout->status = "OK";
			$msgout->messages[] = "Delete Successful";;
		} else {
			$msgout->messages[] = "Another user has updated the record since last retrieved";
			$msgout->messages[] = "Retrieved values were: " . $userRecord->firstName . " " . $userRecord->lastName . " " . $userRecord->blurb;
			$msgout->messages[] = "Your delete has been rolled back. Please press Update to try again"; 
			$msgout->status = "Conflict";
			$msgout->payload->firstName = $userRecord->firstName;
			$msgout->payload->lastName = $userRecord->lastName;
			$msgout->payload->blurb = $userRecord->blurb;
			$msgout->payload->conControl = $userRecord->conControl;

// rollback our updated values which ends the transaction

			if (!$conn->rollback()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}
		}
	} else {
		$msgout->messages[] = "On update row must have been deleted, ID =: " . $msgin->payload->iD;
		$msgout->status = 'Not Found';
		if (!$conn->rollback()) {trigger_error('Wrong SQL: '  . ' Error: ' . $conn->error, E_USER_ERROR);}
	}
} // end deleteUser

function customErrorHandler($error_level, $error_message, $error_file, $error_line, $error_context) {
	global $msgout;
	$msgout->status = "ERROR";
	$message = "myErrorHandler level: $error_level message: $error_message file: $error_file line: $error_line"; 
	error_log($message);
	$msgout->messages[] = $message;
	sendMsgout($msgout);
	die();
} // end customErrorHandler

function sendMsgout($msgout) {
	header('Content-Type: application/json');
	echo json_encode($msgout);
} // end sendMsgout
function checkUserAuthentication($msgin) {
	return TRUE;
} // end checkUserAuthentication
function checkUserAuthorization($msgin) {
	return TRUE;
} // end checkUserAuthorization

?>