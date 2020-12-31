<?php
// WIP.
echo "hi";

$Raw = file_get_contents("php://input");

echo $Raw;

// BASICではよくある、 while(true) -> break. try~catch(exception e)~finally ができるやり方。
while (true) {
  $Result = array(
    "Head" => array(
      "Result" => false,
      "ReasonCode" => "ERROR_UNKNOWN",
      "ReasonText" => "The API did not respond properly to your request."
      )
    );
    
    if (($recv = json_decode($Raw, true, 10)) === null) {
      $Result["ReasonCode"] = "INPUT_MALFORMED";
      $Result["ReasonCode"] = "The provided JSON was malformed so the API could not recognize.";
    }
    
    var_dump($Recv);
    
    break;
  }
  echo json_encode($Result);
  