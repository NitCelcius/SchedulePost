<?php
// This might not be necessary, in fact.
$API_URL = getenv("SP_API_URL");
$Result = array(
  "Result" => "false",
  "ReasonCode" => "ERROR_UNKNOWN",
  "ReasonText" => "An unknown error occurred."
);

$GLOBALS["SessionTokenExpiry"] = getenv("SP_SESSIONTOKENEXPIRY") ?? "30 minutes";
$GLOBALS["LongTokenExpiry"] = getenv("SP_LONGTOKENEXPIRY") ?? "14 days";

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
        CURLOPT_URL => $API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $post,
        CURLOPT_HTTPHEADER => array("Content-Type: application/json")
      ]);
      $api_resp = curl_exec($api_getter);
      curl_close($api_getter);
      $RespObj = json_decode($api_resp, true);
      //var_dump($RespObj);

      if ($RespObj["Result"] === true) {
        $SessionExpiry = new DateTime("now");
        $LongExpiry = new DateTime("now");
        $SessionExpiry->add(DateInterval::createFromDateString($GLOBALS["SessionTokenExpiry"]));
        $LongExpiry->add(DateInterval::createFromDateString($GLOBALS["LongTokenExpiry"]));

        setcookie("UserID", $RespObj["UserID"], array(
          "expires" => time() + 60 * 60 * 24 * 365,
          "path" => "/",
          "secure" => true,
          "httponly" => false,
          "samesite" => "Strict"
        ));
        setcookie("Session", $RespObj["SessionToken"], array(
          "expires" => ($SessionExpiry->getTimestamp()),
          "path" => "/",
          "secure" => true,
          "httponly" => true,
          "samesite" => "Strict"
        ));
        setcookie("LongToken", $RespObj["LongToken"], array(
          "expires" => ($LongExpiry->getTimestamp()),
          "path" => "/",
          "secure" => true,
          "httponly" => true,
          "samesite" => "Strict"
        ));
        $Result = array(
          "Result" => true,
          "UserID" => $RespObj["UserID"]
        );
      } else {
        error_log("sign_in.php: error");
        error_log(print_r($api_resp, true));
        $Result = array(
          "Result" => false,
          "ReasonCode" => "INTERNAL_EXCEPTION",
          "ReasonText" => "Could not sign in: " . $RespObj["ReasonCode"] . ": " . $RespObj["ReasonText"]
        );
      }
      break;
    }

  case false: {
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
