<?php
  // WIP.
  echo "hi";

  $raw = file_get_contents("php://input");

  echo $raw;

  $result = array(
    "Head" => array(
      "Result" => false,
      "ReasonCode" => "ERROR_UNKNOWN",
      "ReasonText" => "The API did not respond properly to your request."
    )
  );

  if (($recv = json_decode($raw, true, 10)) === null) {
    echo "????";
  }
  
  var_dump($recv);

  echo json_encode($result);
?>