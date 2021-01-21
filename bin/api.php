<?php
// Requirement: PHP 8.0.0 or later
// TODO: PHP envilonment requirement
// WIP.

declare(strict_types=1);

$GLOBALS["DB_URI"] = getenv("DB_URI");

function ReplaceArgs(string $Basement, array $Args) {
  return str_replace(array_keys($Args), array_values($Args), $Basement);
}

$ProfilePathFormat = "/Data/Profiles/{School_UUID}/{Group_UUID}.json";
$TimeTablePathFormat = "/Data/Schedules/{School_UUID}/{Group_UUID}/{Year}/{Month}/{Day}/{Version}.json";

$Result = array(
  "Head" => array(
    "Result" => false,
    "ReasonCode" => "ERROR_UNKNOWN",
    "ReasonText" => "The API did not respond properly to your request."
  )
);
// BASICではよくある、 while(true) -> break. try~catch(exception e)~finally ができるやり方。
while (true) {
    $Recv = json_decode(file_get_contents("php://input"), true);

    if ($Recv === null) {
      $Result["ReasonCode"] = "INPUT_MALFORMED";
      $Result["ReasonText"] = "The provided JSON was malformed so the API could not recognize.";
      break;
    }
    
    //Authenticate here
    //Probs insert this part on request header
    $Auth = new UserAuth($Result["Auth"]["UserID"]);

    switch ($Result["Action"]) {
      case "GET_SCHEDULE": {

        break;
      }
    }
    $School_UUID = $Recv["Auth"]["School_UUID"];
    $Group_UUID = $Recv["Auth"]["Group_UUID"];
  
    // This could be request time or use requested data?
    // Should be converted to LOCAL TIMEZONE (of school)
    $TimeObj = new DateTime($Recv["Options"]["Date"]);
    
    // Revision. Must be fetched before, not constant
    $RecentRev = 1;
    $TimeTablePath = ReplaceArgs($TimeTablePathFormat, array(
      "{School_UUID}" => $School_UUID,
      "{Group_UUID}" => $Group_UUID,
      "{Year}" => $TimeObj->format('Y'),
      "{Month}" => $TimeObj->format('n'),
      "{Day}" => $TimeObj->format('j'),
      "{Version}" => $RecentRev
    ));
    echo "<h3>Timetable File Path</h3>";
    echo $TimeTablePath;
    
    break;
}

class UserAuth {
  private $UUID;
  private $Token;

  function __construct($UserID, $Token = null)  {
    $this->UUID = $UserID;
    $this->Token = $Token;
  }
}

class Fetcher {
  private $Connection;

  function Init() {

  }

  function Connect() {
    try {
      $this->Connection = new PDO($GLOBALS["API_URI"], $GLOBALS["DB_UserName"], $GLOBALS["DB_PassPhrase"]);
    } catch (Exception $e) {
      var_dump($e);
      exit;
    }

  }

  function IsPermitted(UserAuth $User, string $Command) {
    
  }

  function GetSchedule(string $User, string $GroupID) {
    
  }
}

echo "<br>";
echo json_encode($Result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  
?>