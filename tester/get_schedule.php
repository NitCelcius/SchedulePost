<?php
  echo "<h1>GET_SCHEDULE test</h1>";
  echo "<p>Request API URI: " . getenv("API_URL") . "</p>";
  echo "<h2>Body</h2>";
  echo $RawPost = file_get_contents("./test_data.json");
  // 実際はクライアントから送信されるが...テストとして json のやつを使う。
  $post = $RawPost;
  $api_getter = curl_init();

  $Post_Obj = json_encode($post);

  // POST する

  curl_setopt_array($api_getter, [
    CURLOPT_URL => getenv("API_URL"),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POSTFIELDS => $Post_Obj
  ]);
  curl_setopt($api_getter, CURLOPT_URL, getenv("API_URL"));
  $api_resp = curl_exec($api_getter);

  echo "<h2>Response</h2>";
  echo $api_resp;
  curl_close($api_getter);

?>