<?php
error_reporting(E_ALL);
ini_set("log_errors", "On");
ini_set("display_errors", 0);
session_destroy();
?>

<!DOCTYPE html>
<html lang="ja">

<head>
  <title>WELCOME</title>
  <link href="/bin/web/theme.css" rel="stylesheet">
  <link href="/app/signup/signup_general.css" rel="stylesheet">
  <link href="/app/signup/index.css" rel="stylesheet">

  <meta lang="ja">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <meta charset="utf-8">
  <meta name="theme-color" content="#CEEDA9">
  <meta name="description" content="Welcome to SchedulePost">
</head>

<body>
  <article>
    <picture class="WelcomePic">
      <source srcset="/resources/images/Schedulepost-header.webp" type="image/webp">
      <img class="WelcomePic" src="/resources/images/Schedulepost-header.png">
    </picture>
    <p id="Err" style="display: none;" onclick="ClearErr()"></p>

    <h1>登録完了</h1>
    <p>登録が完了しました。</p>
    <p>利用をはじめるには、もう一度ログインしてください。</p>
    <hr>
    <a href="/login.html?auth_callback=/app/index.html">メイン画面を開く</a>
  </article>

  <footer>(C)2021 SchedulePost</footer>
</body>

<script src="/bin/web/lib.js"></script>
<script src="/app/signup/index.js" async></script>

</html>