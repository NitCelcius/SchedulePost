<?php
require "bin\lib.php";
var_dump($_POST);

// These might be removed
error_reporting(E_ALL);
ini_set("log_errors", "On");
ini_set("display_errors", 0);

while (true) {
  if ($_POST["Passphrase"] !== $_POST["Passphrase_Re"]) {
    http_response_code(400);
    header("Location: /app/signup/index.html?emsg=PASS_CONF");
    break;
  }

  $Mail = $_POST["id"];
  $Passphrase = $_POST["Passphrase"];
  $InviEZ = $_POST["Inviation"];
  $InviToken = $_POST["Inviation_Long"];


  $Connection = DBConnection::Connect();
  $PDOstt = $Connection->prepare("select GroupID, ExpiresAt,Uses from invitations where InviEZ = :InviCode");
  $PDOstt->bindValue(":InviCode", $InviEZ);

  $PDOstt = $Connection->prepare("select UserID from accounts where Mail = :Mail");
  $PDOstt->bindValue(":Mail",);
  $PDOstt->execute();
  $Data = $PDOstt->fetch();
  break;
}
