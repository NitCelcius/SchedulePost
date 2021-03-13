<?php
session_start();
$Tk = bin2hex(openssl_random_pseudo_bytes(32));
$_SESSION["Tk"] = $Tk;
?>

<!DOCTYPE html>
<html lang="ja">

<head>
  <title>SIGN UP</title>
  <link href="/bin/web/theme.css" rel="stylesheet">
  <link href="/app/signup/signup_general.css" rel="stylesheet">
  <link href="/app/signup/index.css" rel="stylesheet">

  <meta lang="ja">
  <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
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

    <h1>ようこそ</h1>
    <p>SchedulePost は、生徒と教師の時間割・学校行事の管理をサポートするサービスです。</p>
    <hr>
    <h3>注意</h3>
    <p>SchedulePost は開発中のサービスです。このサービスで発生したいかなる損害に対して責任は負えませんが、なるべく不具合のないように頑張っております。</p>
    <p>開発版のため、招待コードが必要です。</p>
    <hr>
    <form name="Signup" id="SignupForm" action="/app/signup/inv_up.php" method="post">
      <label>ID(現在はメールアドレスでなくても登録できます)
        <br>
        <input required type="text" name="id" type="email" placeholder="メールアドレスかID"></label>
      <label>パスワードは 6文字 以上である必要があります。</label>
      <label>
        パスワード
        <br>
        <input required type="password" name="Passphrase" oninput="ChkBasePass(this)">
      </label>
      <label>
        同じパスワードをもう一度入力
        <br>
        <input required type="password" name="Passphrase_Re" oninput="ChkPass(this)">
      </label>
      <label>招待コード
        <br>
        <input required type="text" name="Invitation_Code">
      </label>
      <input type="hidden" name="tk" value="<?php echo $Tk; ?>">
      <button type="submit">次へ</button>
    </form>
    <hr>
  </article>

  <footer>(C)2021 SchedulePost</footer>
</body>

<script src="/bin/web/lib.js"></script>
<script src="/app/signup/index.js" async></script>

</html>