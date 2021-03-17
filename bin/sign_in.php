<?php
// This might not be necessary, in fact.
$GLOBALS["API_URL"] = getenv("SP_API_URL");

$GLOBALS["DB_URL"] = getenv("SP_DB_URL");
$GLOBALS["DB_Username"] = getenv("SP_DB_USER");
$GLOBALS["DB_PassPhrase"] = getenv("SP_DB_PASSPHRASE");
$GLOBALS["DB_PORT"] = getenv("SP_DB_PORT");
$GLOBALS["DB_NAME"] = getenv("SP_DB_NAME");
//type false exactly!!
$GLOBALS["PUBLIC_MODE"] = (getenv("SP_PUBLIC_MODE") === "false") ? false : true;

if (($GLOBALS["DefaultTimeZone"] = getenv("SP_TIMEZONE")) === null) {
  $GLOBALS["DefaultTimeZone"] = "UTC";
}
date_default_timezone_set($GLOBALS["DefaultTimeZone"]);

$GLOBALS["SessionTokenExpiry"] = getenv("SP_SESSIONTOKENEXPIRY") ?? "30 minutes";
$GLOBALS["LongTokenExpiry"] = getenv("SP_LONGTOKENEXPIRY") ?? "14 days";

$GLOBALS["Connection"] = null;

define("SQL_FORBIDDEN_CHARS", ";");

if (strpbrk($GLOBALS["DB_URL"], SQL_FORBIDDEN_CHARS)) {
  http_response_code(503);
  error_log("Database URL contains at least one character that cannot be used! Please check your envilonment variables. Forbidden characters are: " . SQL_FORBIDDEN_CHARS);
}

if (!is_numeric($GLOBALS["DB_PORT"]) && !intval($GLOBALS["DB_PORT"]) == floatval($GLOBALS["DB_PORT"])) {
  http_response_code(503);
  error_log("Database port is invalid! Please specify it in your envilonment variables.");
  exit("ERROR: The server is not yet set up.");
}

if (strpbrk($GLOBALS["DB_Username"], SQL_FORBIDDEN_CHARS)) {
  http_response_code(503);
  error_log("Database username contains at least one character that cannot be used! Please check your envilonment variables. Forbidden characters are: " . SQL_FORBIDDEN_CHARS);
  exit("ERROR: The server is not yet set up.");
}

if (strpbrk($GLOBALS["DB_PassPhrase"], SQL_FORBIDDEN_CHARS)) {
  http_response_code(503);
  error_log("Database username contains at least one character that cannot be used! Please check your envilonment variables. Forbidden characters are: " . SQL_FORBIDDEN_CHARS);
  exit("ERROR: The server is not yet set up.");
}

if (strpbrk($GLOBALS["DB_NAME"], SQL_FORBIDDEN_CHARS)) {
  http_response_code(503);
  error_log("Database username contains at least one character that cannot be used! Please check your envilonment variables. Forbidden characters are: " . SQL_FORBIDDEN_CHARS);
  exit("ERROR: The server is not yet set up.");
}

if (DateInterval::createFromDateString($GLOBALS["SessionTokenExpiry"]) === false) {
  http_response_code(503);
  error_log("The envilonment variable SP_SESSIONTOKENEXPIRY is invalid! (must be a PHP recognizable date string)");
  exit("ERROR: The server is not yet set up.");
}

if (DateInterval::createFromDateString($GLOBALS["LongTokenExpiry"]) === false) {
  http_response_code(503);
  error_log("The envilonment variable SP_LONGTOKENEXPIRY is invalid! (must be a PHP recognizable date string)");
  exit("ERROR: The server is not yet set up.");
}

$INPUT = json_decode(file_get_contents("php://input"), true) ?? array();

$Res = (array_key_exists("Mail", $INPUT) && $INPUT["Mail"] != "" && array_key_exists("PassPhrase", $INPUT) && $INPUT["PassPhrase"] != "");
switch ($Res) {
  case true: {

      // 実際はクライアントから送信されるが...テストとして json のやつを使う。
      $api_getter = curl_init();
      $post = json_encode(array(
        "Action" => "SIGN_IN",
        "Auth" => array(
          "Mail" => $INPUT["Mail"],
          "PassPhrase" => $INPUT["PassPhrase"]
        )
      ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

      // POST する
      curl_setopt_array($api_getter, [
        CURLOPT_URL => $GLOBALS["API_URL"],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_HTTPHEADER => array("Content-Type: application/json")
      ]);
      $api_resp = curl_exec($api_getter);
      curl_close($api_getter);
      $RespObj = json_decode($api_resp, true);

      if ($RespObj["Result"] === true) {
        $SessionExpiry = new DateTime("now", new DateTimeZone($GLOBALS["DefaultTimeZone"]));
        $LongExpiry = new DateTime("now", new DateTimeZone($GLOBALS["DefaultTimeZone"]));
        $SessionExpiry->add(DateInterval::createFromDateString($GLOBALS["SessionTokenExpiry"]));
        $LongExpiry->add(DateInterval::createFromDateString($GLOBALS["LongTokenExpiry"]));

        setcookie("UserID", $RespObj["UserID"], array(
          "expires" => time() + 60 * 60 * 24 * 365,
          "path" => "/",
          "secure" => $GLOBALS["PUBLIC_MODE"],
          "httponly" => false,
          "samesite" => "Strict"
        ));
        setcookie("Session", $RespObj["SessionToken"], array(
          "expires" => ($SessionExpiry->getTimestamp()),
          "path" => "/",
          "secure" => $GLOBALS["PUBLIC_MODE"],
          "httponly" => true,
          "samesite" => "Strict"
        ));
        setcookie("LongToken", $RespObj["LongToken"], array(
          "expires" => ($LongExpiry->getTimestamp()),
          "path" => "/",
          "secure" => $GLOBALS["PUBLIC_MODE"],
          "httponly" => true,
          "samesite" => "Strict"
        ));
        $Result = array(
          "Result" => true,
          "UserID" => $RespObj["UserID"]
        );
        
      } else {
        error_log("sign_in.php: error");
        error_log(print_r($RespObj, true));
        http_response_code(403);
        $Result = array(
          "Result" => false,
          "ReasonCode" => "INTERNAL_EXCEPTION",
          "ReasonText" => "Could not sign in: " . $RespObj["ReasonCode"] . ": " . $RespObj["ReasonText"]
        );
      }
      break;
    }

  case false: {
      http_response_code(400);
      $Result = array(
        "Result" => false,
        "ReasonCode" => "INPUT_MALFORMED",
        "ReasonText" => "Provide credentials information."
      );
      break;
    }

  default: {
      break;
    }
}

echo json_encode($Result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT);
exit();
