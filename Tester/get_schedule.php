<?php
  $API_URL = getenv("API_URL");

  $API_URL = "localhost:84/bin/api.php";
  $RawPost = file_get_contents("./test_data.json");
  // 実際はクライアントから送信されるが...テストとして json のやつを使う。
  $api_getter = curl_init();
  $post = $RawPost;

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

  echo "<h1>GET_SCHEDULE test</h1>";

  echo "<p>Request API URI: " . $API_URL . "</p>";
  echo "<h2>Body</h2>";
  echo $RawPost;

  echo "<h3>POST</h3>";
  var_dump($post);

  echo "<h2>Response</h2>";

  echo $api_resp;
?>