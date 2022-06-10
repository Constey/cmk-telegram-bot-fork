<?php

/*
*	
*	Fileinfo: callbackCMK.php
*	Version: 0.1 
*	Creator: Constantin Lotz (info@constey.de)
*			 www.constey.de
*	Comment: This File handles the callbacks generated in Telegram which have been saved
*			 in the Mysql Database and executed those actions in Check_MK. (Acknowledge/Downtime)
*
*	Changelog:			 
*			   0.1 Initial
* 
*/

// Mysql Connection Info
$dbAdress = "localhost"; 
$dbName = "tg";
$dbUser = "tg";
$dbPass = "tg";

// Login Data CMK
$cmkUrl = "http://192.168.119.130/test/check_mk/";
$cmkUser = "api";
$cmkSecret = "YOUR_CMK_API_SECRET";

// Prepare Array
$arrOutput= array();


function selectDatabase () {
	global $dbAdress;
	global $dbUser;
	global $dbName;
	global $dbPass;
	global $arrOutput;
	
	$link = mysqli_connect($dbAdress, $dbUser, $dbPass, $dbName);
	if ($link == false) {
			die("ERROR: Could not connect. " . mysqli_connect_error());
	}
	// Select all unhandled callback queries
	$sql = "SELECT data, message_id FROM callback_query where game_short_name = '' ORDER BY message_id DESC";
	if ($res = mysqli_query($link, $sql)) {
			if (mysqli_num_rows($res) > 0) {
					while ($row = mysqli_fetch_array($res)) {
						//$data = $row[0];
						$data = array($row[0], $row[1]);
						array_push($arrOutput, $data);
					}
					mysqli_free_result($res);
			}
			else {
					echo "Info: No Callbacks for CMK. No matching records are found.\n";
			}
	}
	else {
			echo "ERROR: Could not able to execute $sql. " .mysqli_error($link) . PHP_EOL ;
	}
	mysqli_close($link);
}


function updateDatabase ($messageID) {
	global $dbAdress;
	global $dbUser;
	global $dbName;
	global $dbPass;
	
	$link = mysqli_connect($dbAdress, $dbUser, $dbPass, $dbName);

	if ($link == false) {
			die("ERROR: Could not connect. " . mysqli_connect_error());
	}
	// UPDATE `callback_query` SET `game_short_name` = 'done' WHERE `callback_query`.`id` = 2801180104457698816;
	$sql = "UPDATE callback_query SET game_short_name=CURRENT_TIMESTAMP WHERE message_id=" . $messageID;
	$sql = mysqli_real_escape_string($link, $sql);

	if(mysqli_query($link, $sql)){ 
		echo "Info: Mysql UpdateDatabase - Record $messageID was updated successfully.\n"; 
	} else { 
		echo "ERROR: Could not able to execute for $messageID statement $sql. "  
								. mysqli_error($link); 
	}  
	mysqli_close($link); 
}


function callCMKApi($action, $messageId) {
	global $cmkUrl;
	global $cmkUser;
	global $cmkSecret;
// http://192.168.119.130/test/check_mk/view.py?output_format=JSON&_username=api&_secret=KOQJLIYGJYPQAIBRMVWV&_do_confirm&_transid=-1&_do_actions=yes&host=downHost&view_name=hostproblems&_ack_comment=testTelegram&_ack_expire_days=0&_ack_expire_hours=0&_ack_expire_minutes=3&_ack_notify=on&_ack_sticky=on&_acknowledge=Acknowledge&actions=yes&filled_in=confim

	$baseURL = $cmkUrl . "view.py?output_format=JSON&_username=" . $cmkUser . "&_secret=" . $cmkSecret . "&_do_confirm&_transid=-1&_do_actions=yes&";
	$completeURL = $baseURL . $action;
	//echo "\n" . $completeURL;
	$ch = curl_init($completeURL); // cURL ínitialisieren
	curl_setopt($ch, CURLOPT_HEADER, 0); // Header soll nicht in Ausgabe enthalten sein
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	//curl_setopt($ch, CURLOPT_POST, 1); // POST-Request wird abgesetzt
	//curl_setopt($ch, CURLOPT_POSTFIELDS, 'feld1=wort1+wort2&amp;feld2=true'); // POST-Felder festlegen, die gesendet werden sollen
	$result = curl_exec($ch); // Ausführen
	curl_close($ch); // Objekt schließen und Ressourcen freigeben
	//var_dump($result);

	if ( strstr( $result, 'Successfully' ) ) {
	  // Command successfully executed
	  echo "Info: Successfully executed a command at cmk for message_id=$messageId. \n";
	  updateDatabase($messageId);
	} else {
	  // Error with contacting CMK
	  echo "Error: Contacting CMK not successfull. Data:" . $result . PHP_EOL ;
	}
}


// Fetch Todo from Mysql
selectDatabase();
//var_dump($arrOutput);

//$arrOutput = "";

foreach ($arrOutput as $value) {
	//$value[0] = json string {"a":"2","h":"downHost","s":"PING"}
	//$value[1] = message_id 
	//var_dump($value);
	$messageId = $value[1];
	$json = json_decode($value[0]);
	//var_dump(json_decode($json));
	if (json_last_error() != 0) { echo "Error in Json String:" . $json; break; };
	//var_dump(json_decode($json, true));
	//echo "a:" . $json -> { "a" } ;
	
	switch ($json -> { "a" }) {
    case 0:
		// Acknowledge
        //echo "acknowledge";
		if ($json -> { "s" } != "") {
			// Service
			$actionString = "service=" .  urlencode($json -> { "s" }) . "&host=" .  urlencode($json -> { "h" }) . "&view_name=service&_ack_comment=MobileApi-" . $messageId . "&_ack_expire_days=0&_ack_expire_hours=0&_ack_expire_minutes=0&_ack_notify=on&_ack_sticky=on&_acknowledge=Acknowledge&actions=yes&filled_in=confim";
			callCMKApi($actionString, $messageId);
		} else {
			// Host
			$actionString = "host=" .  urlencode($json -> { "h" }) . "&view_name=hostproblems&_ack_comment=MobileApi-" . $messageId . "&_ack_expire_days=0&_ack_expire_hours=0&_ack_expire_minutes=0&_ack_notify=on&_ack_sticky=on&_acknowledge=Acknowledge&actions=yes&filled_in=confim";
			callCMKApi($actionString, $messageId);
		}
		break;
    case 1:
		// Downtime 2h
        //echo "dt2h";
		if ($json -> { "s" } != "") {
			$actionString = "service=" .  urlencode($json -> { "s" }) . "&host=" .  urlencode($json -> { "h" }) . "&view_name=service&_down_comment=MobileApi-" . $messageId . "&_down_from_now=yes&_down_minutes=120&actions=yes&filled_in=confim";
			callCMKApi($actionString, $messageId);
		} else {
			$actionString = "host=" .  urlencode($json -> { "h" }) . "&view_name=hostproblems&_down_comment=MobileApi-" . $messageId . "&_down_from_now=yes&_down_minutes=120&actions=yes&filled_in=confim";  
			callCMKApi($actionString, $messageId);
		}
        break;
    case 2:
		// Downtime 24h
        //echo "dt24h";
		if ($json -> { "s" } != "") {
			$actionString = "service=" .  urlencode($json -> { "s" }) . "&host=" .  urlencode($json -> { "h" }) . "&view_name=service&_down_comment=MobileApi-" . $messageId . "&_down_from_now=yes&_down_minutes=1440&actions=yes&filled_in=confim";
			callCMKApi($actionString, $messageId);
		} else {
			$actionString = "host=" .  urlencode($json -> { "h" }) . "&view_name=hostproblems&_down_comment=MobileApi-" . $messageId . "&_down_from_now=yes&_down_minutes=1440&actions=yes&filled_in=confim";   
			callCMKApi($actionString, $messageId);
		}
        break;
}
	
	
	// Reminder
	//  $actionACK  = '{"a":"0","h":"' . $hostname . '","s":"'. $service . '"}';
	//	$actionDT2  = '{"a":"1","h":"' . $hostname . '","s":"'. $service . '"}';
	//	$actionDT24 = '{"a":"2","h":"' . $hostname . '","s":"'. $service . '"}';
}

//updateDatabase("2801180104457698816");
?>

