<?php
  echo "Logging in...";

  $API_URL = getenv("API_URL");

  // Even now, I use localhost - cause it's cool. 5 ports in use.
  $API_URL = "localhost:84/bin/api.php";
  // 実際はクライアントから送信されるが...テストとして json のやつを使う。
  $api_getter = curl_init();
  $post = json_encode(array(
    "Action" => "SIGN_IN",
    "Auth" => array(
      "Mail" => $_POST["Mail"],
      "PassPhrase" => $_POST["PassPhrase"]
    )
  ), JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

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
  /*
  echo "<h1>Sent</h1>";
  echo var_dump($post);
  echo "<h1>Recv</h1>";
  echo $api_resp;
  */

  if ($RespObj["Result"]) {
    setcookie("UserID",  $RespObj["UserID"], time()+60*60*24*30, "/");
    setcookie("SessionToken",  $RespObj["SessionToken"], time()+60*10, "/");
    setcookie("LongToken",  $RespObj["LongToken"], time()+60*60*24*30, "/");

    // not sure if it's okay
    var_dump($_GET);
    header("Location: ". $_GET["auth_callback"], true);
  } else {
    var_dump($api_resp);
  }
  exit();