#!/usr/bin/env php
<?php

/*
*	
*	Fileinfo: sendMessage.php
*	Version: 0.1 
*	Creator: Constantin Lotz (info@constey.de)
*			 www.constey.de
*	Comment: This File fetches the Livestatus Data from Check_MK and creates Messages with	
*			 an Response option (InlineKeyboard) and an overall summary containing all host&service problems.
*			 It is based on the getUpdatesCLI.php from the PHP-TelegramBot.
*
*	Changelog:			 
*			   0.1 Initial
* 
*/

/**
 * README
 * This configuration file is intended to run the bot with the getUpdates method.
 * Uncommented parameters must be filled
 *
 * Bash script:
 * $ while true; do ./getUpdatesCLI.php; done
 */

// Load composer
require_once __DIR__ . '/vendor/autoload.php';

// Benötogt fuer Requests sendMEssage
use \Longman\TelegramBot\Request;

//Benötigt für Response Callback & Keyboard
use Longman\TelegramBot\Commands\UserCommand;
use Longman\TelegramBot\Entities\InlineKeyboard;


// Add you bot's API key and name
$bot_api_key  = 'YOUR_API_KEY_HERE';
$bot_username = 'YOUR_BOT_USERNAME_WITHOUT@';
$chat_id = "YOUR_CHAT_ID//GROUP";
$debug = false;
$sendMessagesStats = 0;

// Define all IDs of admin users in this array (leave as empty array if not used)
$admin_users = [
    YOUR_ADMIN_USER,
];

// Define all paths for your custom commands in this array (leave as empty array if not used)
$commands_paths = [
  __DIR__ . '/Commands/',
];

// Enter your MySQL database credentials
$mysql_credentials = [
    'host'     => 'localhost',
    'user'     => 'tg',
    'password' => 'tg',
    'database' => 'tg',
];


// Functions
function concatAndJsonDecode ($input) {
	global $debug;
	global $sendMessagesStats;
	if ($input == "") { echo "Error: No valid input given for concatAndJsonDecode";  die(1);}

	// All single lines of the array will be concat to one string containing all lines and then converted to json object.
	$allLines = "";
	foreach ($input as $lines) {
		//if ($debug) { echo 'Debug: Concat Line ; ' . $lines . "\n"; }
		$allLines = $allLines . "" . $lines;
	}
	//if ($debug) { echo 'Debug: Concat All Lines ; ' . $allLines . "\n"; }
	//if ($debug) { var_dump($allLines); }
	$inputJson = json_decode($allLines);
	// echo "all Lines after json:";
	// var_dump($inputJson);
	return $inputJson;

}

try {
    // Create Telegram API object
    $telegram = new Longman\TelegramBot\Telegram($bot_api_key, $bot_username);

    // Add commands paths containing your custom commands
    $telegram->addCommandsPaths($commands_paths);

    // Enable admin users
    $telegram->enableAdmins($admin_users);

    // Enable MySQL
    $telegram->enableMySql($mysql_credentials);

    // Logging (Error, Debug and Raw Updates)
    //Longman\TelegramBot\TelegramLog::initErrorLog(__DIR__ . "/{$bot_username}_error.log");
    //Longman\TelegramBot\TelegramLog::initDebugLog(__DIR__ . "/{$bot_username}_debug.log");
    //Longman\TelegramBot\TelegramLog::initUpdateLog(__DIR__ . "/{$bot_username}_update.log");

    // If you are using a custom Monolog instance for logging, use this instead of the above
    //Longman\TelegramBot\TelegramLog::initialize($your_external_monolog_instance);

    // Set custom Upload and Download paths
    //$telegram->setDownloadPath(__DIR__ . '/Download');
    //$telegram->setUploadPath(__DIR__ . '/Upload');

    // Here you can set some command specific parameters
    // e.g. Google geocode/timezone api key for /date command
    //$telegram->setCommandConfig('date', ['google_api_key' => 'your_google_api_key_here']);

    // Botan.io integration
    //$telegram->enableBotan('your_botan_token');

    // Requests Limiter (tries to prevent reaching Telegram API limits)
    $telegram->enableLimiter();
	
    // Handle telegram getUpdates request
//    $server_response = $telegram->handleGetUpdates();

//    if ($server_response->isOk()) {
//        $update_count = count($server_response->getResult());
//        echo date('Y-m-d H:i:s', time()) . ' - Processed ' . $update_count . ' updates';
//    } else {
//        echo date('Y-m-d H:i:s', time()) . ' - Failed to fetch updates' . PHP_EOL;
//        echo $server_response->printError();
//    }

//// GET DATA /////////////////////////////////////////////////
// ggf noch flappende Dienste ausblenden: flap_detection_enabled 

exec('lq "GET hosts\nColumns: host_name description state hard_state execution_time host_last_check host_last_time_up plugin_output\nFilter: acknowledged = 0\nFilter: scheduled_downtime_depth = 0\nFilter: state = 1\nFilter: state = 2\nOr: 2\nOutputFormat: json"
', $hostErrors); 
if ($hostErrors == NULL) { echo "Fehler beim Auslese der Hostprobleme"; } else {
	//echo $hostErrors;
	$hostErrors = concatAndJsonDecode($hostErrors);
	if ($debug) { echo 'Debug: CMK HstError ; ' . count($hostErrors) . "\n"; }
}
exec('lq "GET services\nColumns: host_name display_name state hard_state execution_time host_last_check host_last_time_up plugin_output\nFilter: acknowledged = 0\nFilter: host_acknowledged = 0\nFilter: scheduled_downtime_depth = 0\nFilter: host_downtimes = ""\nFilter: state = 1\nFilter: state = 2\nOr: 2\nOutputFormat: json"', $serviceErrors);
if ($serviceErrors == NULL) { echo "Fehler beim Auslese der Serviceprobleme."; } else {
	//echo $serviceErrors;
	$serviceErrors = concatAndJsonDecode($serviceErrors);
	if ($debug) { echo 'Debug: CMK SvcError ; ' . count($serviceErrors) . "\n"; }
}

// GET Livestatus overview
exec('lq "GET hosts\nStats: state = 0\nStats: state = 1\nStats: state = 2\nStats: state = 3\nOutputFormat: json"', $statusHosts);
if ($statusHosts == NULL) { echo "Fehler beim Auslese der Status Hosts."; } else {
	//echo $serviceErrors;
	$statusHosts = concatAndJsonDecode($statusHosts);
	if ($debug) { echo 'Debug: CMK HstSum ; ' . count($statusHosts) . "\n"; }
}
exec('lq "GET services\nStats: state = 0\nStats: state = 1\nStats: state = 2\nStats: state = 3\nOutputFormat: json"', $statusServices);
if ($statusServices == NULL) { echo "Fehler beim Auslese der Status Services."; } else {
	//echo $serviceErrors;
	$statusServices = concatAndJsonDecode($statusServices);
	if ($debug) { echo 'Debug: CMK SvcSum ; ' . count($statusServices) . "\n"; }
}
function generateInlineKeyboard ($xError) {
	// Generate Return Messages for Callback DATA
	global $debug;
	
	if (count($xError) >= 1) {
		// Array check
		$hostname = $xError[0];
		$service  = $xError[1];

		$actionACK  = '{"a":"0","h":"' . $hostname . '","s":"'. $service . '"}';
		$actionDT2  = '{"a":"1","h":"' . $hostname . '","s":"'. $service . '"}';
		$actionDT24 = '{"a":"2","h":"' . $hostname . '","s":"'. $service . '"}';
		if (strlen($actionACK) > 64) { echo 'Error: callbackString too long for telegram (64);' . $actionACK . "\n"; }
		if ($debug) { echo 'Debug: TG InlineKeyboard ACK; ' . $actionACK . "(" . strlen($actionACK) . ")\n"; }	
		if ($debug) { echo 'Debug: TG InlineKeyboard DT2; ' . $actionDT2 . "(" . strlen($actionDT2) . ")\n"; }
		if ($debug) { echo 'Debug: TG InlineKeyboard DT24; ' . $actionDT24 . "(" . strlen($actionDT24) . ")\n"; }	
						
		$inline_keyboard = new InlineKeyboard([
				// Example:
				// ['text' => 'Acknowledge', 'callback_data' => 'Acknowledge12345'],
				['text' => 'Acknowledge', 'callback_data' => $actionACK],
			], [
				
				['text' => 'Downtime 2h', 'callback_data' => $actionDT2],
				['text' => 'Downtime Host 24h', 'callback_data' => $actionDT24],
			]);
			
		if ($debug) { echo 'Debug: TG InlineKeyboard ; ' . $inline_keyboard . "\n"; }	
		return $inline_keyboard;
	} else {
		// Wrong input data
		echo "Error: generateInlineKeyboard ";
		var_dump($xError);
		die(1);
	}
}

function writeHostErrors ($hostErrors) {
	// Gibt alle Hostfehler einzeln aus
	global $chat_id;
	global $debug;
	global $sendMessagesStats;
	
	foreach ($hostErrors as $hError) {
		$timestamp = $hError[5];
		$datetimeFormat = 'd.m.Y H:i:s';

		$date = new \DateTime();
		$date->setTimestamp($timestamp);

		$text = "\xE2\x9D\x8C " . "*" . $hError[0] . "*" . "
				\xF0\x9F\x93\x85 Since:" . $date->format($datetimeFormat) . " 
				\xE2\x8F\xB3	 " . "Duration: xx Minutes" . "  
				\xF0\x9F\x93\x8B " . $hError[7] . "";
			
		// Generate Inline Keyboard
		$inline_keyboard = generateInlineKeyboard($hError);
		$result = Request::sendMessage(['chat_id' => $chat_id, 'parse_mode' => 'MARKDOWN','reply_markup' => $inline_keyboard, 'text' => $text]);
		if ($debug) { echo 'Debug: TG SM sendMessage ; ' . $result . "\n"; }
		if (!$result->isOk()) { echo 'Error: TG GM sendMessage ; ' . $result . "\n"; }  else { $sendMessagesStats++; }
			
	}
}
function writeServiceErrors ($serviceErrors) {
	// Gibt alle Servicefehler einzeln aus
	global $chat_id;
	global $debug;
	global $sendMessagesStats;
	
	foreach ($serviceErrors as $sError) {

		$timestamp = $sError[5];
		$datetimeFormat = 'd.m.Y H:i:s';

		$date = new \DateTime();
		$date->setTimestamp($timestamp);

		if ($sError[2] == 2) { $criticality = "\xE2\x9D\x8C"; } // Fehler
		if ($sError[2] == 1) { $criticality = "\xE2\x9A\xA0"; } // Warning
		$text = $criticality . " Service: *" . $sError[1] . "* " . $criticality . "
				\xF0\x9F\x96\xA5 Host: " . $sError[0] . "
				\xF0\x9F\x93\x85 Since:" . $date->format($datetimeFormat) . " 
				\xF0\x9F\x93\x8B " . $sError[7] . "";

		// Generate Inline Keyboard
		$inline_keyboard = generateInlineKeyboard($sError);
		$result = Request::sendMessage(['chat_id' => $chat_id, 'parse_mode' => 'MARKDOWN','reply_markup' => $inline_keyboard, 'text' => $text]);
		if ($debug) { echo 'Debug: TG SM sendMessage ; ' . $result . "\n"; }
		if (!$result->isOk()) { echo 'Error: TG GM sendMessage ; ' . $result . "\n"; }  else { $sendMessagesStats++; }
	}
}

//// Overview Post // Demo
/*	$text = "   * Monitoring Overview *   
			12000 | 5000 | 233 | 120 |
			
			   * Host Problems *   
			\xE2\x9D\x8C helic1 (1min)
			\xE2\x9D\x8C helic2 (62min)
			\xE2\x9D\x8C www.google.de (182min)
			   * Service Problems *   
			\xE2\x9D\x8C checkhttp (54min)
			└ connect to address 127.0.0.1 and port 4432 Connection refused
			\xE2\x9A\xA0 PING (1min)
			\xE2\x9A\xA0 Test2 (54min)";		   
	$result = Request::sendMessage(['chat_id' => $chat_id, 'parse_mode' => 'MARKDOWN', 'text' => $text]);
	echo $result;
*/


// Overview Post // Start
// $statusServices[0][0]; // Services OK
// $statusServices[0][1]; // Services Fehler
// $statusServices[0][2]; // Services Warning
// $statusServices[0][3]; // Services Unknown
	$text = "   * Monitoring Overview *   
			Hosts: \xE2\x9D\x8E" . $statusHosts[0][0] . " | \xE2\x9D\x8C" . $statusHosts[0][1] . "  | \xE2\x9A\xA0" . $statusHosts[0][2] . "  | \xE2\x9D\x93" . $statusHosts[0][3] . " 
			Servi: \xE2\x9D\x8E" . $statusServices[0][0] . " | \xE2\x9D\x8C" . $statusServices[0][1] . "  | \xE2\x9A\xA0" . $statusServices[0][2] . "  | \xE2\x9D\x93" . $statusServices[0][3] . " 
				
			";

// Overview Post // Host Probs
$text .= "* Host Problems *   
		 ";
foreach ($hostErrors as $hError) {
	if ($hError[2] == 2) { $criticality = "\xE2\x9D\x8C"; } // Fehler
	if ($hError[2] == 1) { $criticality = "\xE2\x9A\xA0"; } // Warning
	// Storing to Temp because auf removing illegal characters for telegram markdown language
	$tmpText =  $criticality . " " . $hError[0] . "
			";
			
	$tmpText = str_replace("_", "-", $tmpText);
	$tmpText = str_replace("*", "-", $tmpText);
	$tmpText = str_replace("[", "(", $tmpText);
	$tmpText = str_replace("]", ")", $tmpText);
	
	$text .= $tmpText;
	$tmpText = "";		
}

// Overview Post // Service Probs
$text .= "
			* Service Problems *   
		 ";
foreach ($serviceErrors as $sError) {
	if ($sError[2] == 2) { $criticality = "\xE2\x9D\x8C"; } // Fehler
	if ($sError[2] == 1) { $criticality = "\xE2\x9A\xA0"; } // Warning
	// Storing to Temp because auf removing illegal characters for telegram markdown language
	$tmpText = $criticality . " " . $sError[1] . " (". $sError[0] . ")
			└ " . substr($sError[7], 0, 30) . "
			└ " . substr($sError[7], 31, 30) . "...
			";
	$tmpText = str_replace("_", "-", $tmpText);
	$tmpText = str_replace("*", "-", $tmpText);
	$tmpText = str_replace("[", "(", $tmpText);
	$tmpText = str_replace("]", ")", $tmpText);
	
	$text .= $tmpText;
	$tmpText = "";
}

	///////////////////////////////// Lösche alte Nachrichten 
	// Ein Request wird benötigt um aktuelle MessageID zu bekommen.....
	$result = Request::sendMessage(['chat_id' => $chat_id, 'parse_mode' => 'MARKDOWN', 'text' => "-"]);
	//var_dump($result);	
	// TeleGramBot Objekte in normale Arrays konvertieren zum zugreifen:
	$tmpResult = (array)$result;
	$tmpResultOK = (array)$tmpResult["ok"];
	if ($tmpResultOK[0] == true) {
		// Result = OK
		//On success, it will return following JSON object: {"ok":true,"result":true}.
		//If you are trying to remove service message or other user's message, but bot is not an admin: {"ok":false,"error_code":400,"description":"Bad Request: message can't be deleted"}.
		//If you are trying to remove non-existent message or its already deleted: {"ok":false,"error_code":400,"description":"Bad Request: message to delete not found"}
		//echo "Abfrage geht";
		$tmpResult = (array)$tmpResult["result"];
		//var_dump($tmpResult);
		// echo $tmpResult["message_id"];
		
		for ($i = $tmpResult["message_id"]; $i >= 0; $i--) {
			// Delete all old Messages
			$result = Request::deleteMessage(['chat_id' => $chat_id, 'message_id' => $i]);
			$tmpResult = (array)$result;
			$tmpResult = (array)$tmpResult["ok"];
			//echo $result;
			if ($debug) { echo 'Debug: TG del Message ; ' . $i . PHP_EOL ; }
			
			if ($tmpResult[0] == false) {
				// Stoppe wenn eine Nachricht dazwischen nicht gefunden wurde.
				// echo "Löschen Fehlerhaft. Stoppe....";
				break;
			}
		}
		
	} else {
		echo "Fehler bei der Abfrage von Telegram";
	}

	// Ausgabe der Host & Servicefehler
	writeHostErrors ($hostErrors);
	writeServiceErrors ($serviceErrors);
	///////////////////////////////// Zeige Globalen Status an

	// Entferne Sonderzeichen wie _ da sonst Telegram aufgrund Markdown einen Fehler schmeißt
	// {"ok":false,"error_code":400,"description":"Bad Request: can't parse entities: Can't find end of the entity starting at byte offset 197"}
	
	$text = str_replace("_", "-", $text);
	if ($debug) { echo "String2Send2TG: $text\n"; var_dump($text); }
	$result = Request::sendMessage(['chat_id' => $chat_id, 'parse_mode' => 'MARKDOWN', 'text' => $text]);
	if ($debug) { echo 'Debug: TG GM sendMessage ; ' . $result . PHP_EOL ;; }
	if (!$result->isOk()) { echo 'Error: TG GM sendMessage ; ' . $result . PHP_EOL ; } else { $sendMessagesStats++; }

	/// Script END
	echo "Info: Messages sent:" . $sendMessagesStats;

} catch (Longman\TelegramBot\Exception\TelegramException $e) {
    echo $e->getMessage();
    // Log telegram errors
    Longman\TelegramBot\TelegramLog::error($e);
} catch (Longman\TelegramBot\Exception\TelegramLogException $e) {
    // Catch log initialisation errors
    echo $e->getMessage();
}

echo "\n";