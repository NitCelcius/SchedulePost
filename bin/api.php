<?php
// Requirement: PHP 8.0.0 or later
// TODO: PHP envilonment requirement
// WIP.

declare(strict_types=1);
echo "hi";

$Raw = file_get_contents("php://input");

$ProfilePathFormat = "/Data/Profiles/{School_UUID}/{Group_UUID}.json";
$TimeTablePathFormat = "/Data/Schedules/{School_UUID}/{Group_UUID}/{Year}/{Month}/{Day}/{Version}.json";

// BASICではよくある、 while(true) -> break. try~catch(exception e)~finally ができるやり方。
while (true) {
  $Result = array(
    "Head" => array(
      "Result" => false,
      "ReasonCode" => "ERROR_UNKNOWN",
      "ReasonText" => "The API did not respond properly to your request."
      )
    );

    $Recv = json_decode($Raw, true);
    if ($Recv === null) {
      $Result["ReasonCode"] = "INPUT_MALFORMED";
      $Result["ReasonCode"] = "The provided JSON was malformed so the API could not recognize.";
    }
    var_dump($Recv);

    //Authenticate here
    $School_UUID = $Recv["Auth"]["School_UUID"];
    $Group_UUID = $Recv["Auth"]["Group_UUID"];
    
    break;
  }
  echo json_encode($Result);

  // This could be request time or use requested data?
  // Should be converted to LOCAL TIMEZONE (of school)
  $TimeObj = new DateTime();

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
  echo "<br><br>";

  function ReplaceArgs(string $Basement, array $Args) {
    return str_replace(array_keys($Args), array_values($Args), $Basement);
  }


?>