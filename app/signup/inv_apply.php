<!DOCTYPE html>
<html lang="ja">

<head>
  <title>SIGN UP</title>
  <link href="/bin/web/theme.css" rel="stylesheet">
  <link href="/app/signup/inv_up.css" rel="stylesheet">
  <link href="/app/signup/signup_general.css" rel="stylesheet">
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
    <h1>登録中</h1>
    <hr>
    <p>登録しています。</p>

  </article>

  <footer>(C)2021 SchedulePost</footer>
</body>

<script src="/bin/web/lib.js"></script>
<script src="/app/signup/index.js" async></script>

</html>

<?php
if (!isset($_POST)) {
  exit();
}
require "bin/lib.php";

// These might be removed
error_reporting(E_ALL);
ini_set("log_errors", "On");
ini_set("display_errors", 0);
session_start();

while (true) {
  if ($_POST["Tk"] !== $_SESSION["Tk"]) {
    http_response_code(403);
    header("Location: /app/signup/index.php?emsg=INV_ARGS");
  }

  $Uname = $_POST["UserName"];

  if ($Uname == null || strlen($Uname) >= 33) {
    http_response_code(403);
    header("Location: /app/signup/index.php?emsg=INV_UNAME");
  }

  $Connection = DBConnection::Connect();
  $PDOstt = $Connection->prepare("select GroupID, ExpiresAt,Uses,Group_Permission,School_Permission,CreatedBy from invitations where InviToken = :InviFullToken");
  $PDOstt->bindValue(":InviFullToken", $_SESSION["InviToken"]);
  $PDOstt->execute();
  $Dt = $PDOstt->fetch();

  $GroupID = $Dt["GroupID"];
  $InviToken = $_SESSION["InviToken"];
  $GroupPermission = $Dt["Group_Permission"];
  $SchoolPermission = $Dt["School_Permission"];
  $InviFrom = $Dt["CreatedBy"];

  if ($Dt === null || $Dt === false) {
    http_response_code(400);
    header("Location: /app/signup/index.php?emsg=INVI_INVA");
    break;
  }

  $CDate = new DateTime();
  $ExpDate = new DateTime($Dt["ExpiresAt"]);

  if ($ExpDate < $CDate) {
    http_response_code(400);
    header("Location: /app/signup/index.php?emsg=INVI_INVA");
    break;
  }

  $Usesleft = intval($Dt["Uses"]);
  if ($Usesleft <= 0) {
    http_response_code(400);
    header("Location: /app/signup/index.php?emsg=INVI_INVA");
    break;
  }

  $PDOstt = $Connection->prepare("select DisplayName, BelongSchoolID from group_profile where GroupID = :GroupID");
  $PDOstt->bindValue(":GroupID", $GroupID);
  $PDOstt->execute();
  $Dt = $PDOstt->fetch();

  if ($Dt === false || $Dt === null) {
    http_response_code(400);
    header("Location: /app/signup/index.php?emsg=GROUP_GONE");
    break;
  }

  $GroupName = $Dt["DisplayName"];
  $SchoolID = $Dt["BelongSchoolID"];

  $PDOstt = $Connection->prepare("select Mail,PassHash,DisplayName from temp_accounts where Token = :Token");
  $PDOstt->bindValue("Token", $_SESSION["TmpActk"]);
  $PDOstt->execute();
  $Data = $PDOstt->fetch();

  if ($Data === false || $Data === null) {
    http_response_code(400);
    header("Location: /app/signup/index.php?emsg=INV_ARGS");
    break;
  }

  $UID = null;
  for ($i = 0; $i < 100; $i++) {
    $TryUID = bin2hex(openssl_random_pseudo_bytes(16));
    $PDOstt = $Connection->prepare("select UserID from accounts where UserID = :UID");
    $PDOstt->bindValue(":UID", $TryUID);
    $PDOstt->execute();
    $Dt = $PDOstt->fetch();
    if ($Dt === false || $PDOstt->rowCount() === 0) {
      $UID = $TryUID;
      break;
    }
  }
  if ($UID === null) {
    http_response_code(400);
    header("Location: /app/signup/index.php?emsg=INV_ARGS");
    break;
  }

  $Mail = $Data["Mail"];
  $PassHash = $Data["PassHash"];
  $CreationTime = new DateTime();

  $PDOstt = $Connection->prepare("insert into user_profile (`BelongUserID`, `BelongGroupID`, `BelongSchoolID`, `DisplayName`) VALUES (:UID, :GID, :SID, :UserName)");
  $PDOstt->bindValue(":UID", $UID);
  $PDOstt->bindValue(":GID", $GroupID);
  $PDOstt->bindValue(":SID", $SchoolID);
  $PDOstt->bindValue(":UserName", $Uname);
  $Res = $PDOstt->execute();
  if ($Res === false) {
    error_log("SIGN_UP: Could not update user_profile.");
    http_response_code(400);
    header("Location: /app/signup/index.php?emsg=INV_ARGS");
    break;
  }

  $PDOstt = $Connection->prepare("insert into school_permissions (`UserID`, `TargetSchoolID`, `Permissions`, `LastUpdateBy`) VALUES (:UID, :SID, :Permission, :Updater)");
  $PDOstt->bindValue(":UID", $UID);
  $PDOstt->bindValue(":SID", $SchoolID);
  $PDOstt->bindValue(":Permission", $SchoolPermission);
  $PDOstt->bindValue(":Updater", $InviFrom);
  $Res = $PDOstt->execute();
  if ($Res === false) {
    error_log("SIGN_UP: Could not update school_permissions.");
    http_response_code(400);
    header("Location: /app/signup/index.php?emsg=INV_ARGS");
    break;
  }

  $PDOstt = $Connection->prepare("insert into group_permissions (`UserID`, `TargetGroupID`, `Permissions`, `LastUpdateBy`) VALUES (:UID, :GID, :Permission, :Updater)");
  $PDOstt->bindValue(":UID", $UID);
  $PDOstt->bindValue(":GID", $GroupID);
  $PDOstt->bindValue(":Permission", $GroupPermission);
  $PDOstt->bindValue(":Updater", $InviFrom);
  $Res = $PDOstt->execute();
  if ($Res === false) {
    error_log("SIGN_UP: Could not update group_permissions.");
    http_response_code(400);
    header("Location: /app/signup/index.php?emsg=INV_ARGS");
    break;
  }

  $PDOstt = $Connection->prepare("insert into accounts (`UserID`, `Mail`, `PassHash`, `CreatedAt`) VALUES (:UID, :Mail, :PassHash, :CreatedAt)");
  $PDOstt->bindValue(":UID", $UID);
  $PDOstt->bindValue(":Mail", $Mail);
  $PDOstt->bindValue(":PassHash", $PassHash);
  $PDOstt->bindValue(":CreatedAt", $CreationTime->format("Y-m-d H:i:s"));
  $Res = $PDOstt->execute();
  if ($Res === false) {
    error_log("SIGN_UP: Could not update accounts.");
    http_response_code(400);
    header("Location: /app/signup/index.php?emsg=INV_ARGS");
    break;
  }

  $PDOstt = $Connection->prepare("update invitations SET `Uses` = Uses-1 where InviToken = :InvToken");
  $PDOstt->bindValue(":InvToken", $InviToken);
  $Res = $PDOstt->execute();
  if ($Res === false) {
    error_log("SIGN_UP: Could not update invitations.");
    http_response_code(400);
    header("Location: /app/signup/index.php?emsg=INV_ARGS");
    break;
  }

  http_response_code(200);
  header("Location: /app/signup/complete.php");
  break;
}
?>