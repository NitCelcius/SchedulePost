<?php
if (!isset($_POST)) {
  exit();
}
require "/bin/lib.php";

// These might be removed
error_reporting(E_ALL);
ini_set("log_errors", "On");
ini_set("display_errors", 0);
session_start();

while (true) {
  if ($_POST["tk"] !== $_SESSION["Tk"]) {
    http_response_code(403);
    header("Location: /app/signup/index.php?emsg=INV_ARGS");
  }

  if ($_POST["Passphrase"] === null || $_POST["Passphrase_Re"] === null || $_POST["Passphrase"] !== $_POST["Passphrase_Re"]) {
    http_response_code(400);
    header("Location: /app/signup/index.php?emsg=PASS_CONF");
    break;
  }

  $Mail = $_POST["id"];
  $Passphrase = $_POST["Passphrase"];
  $InviEZ = $_POST["Invitation_Code"];
  //$InviToken = $_POST["Inviation_Long"];

  $GroupName = "";
  $SchoolName = "";

  $Connection = DBConnection::Connect();
  $PDOstt = $Connection->prepare("select GroupID, ExpiresAt,Uses,InviToken from invitations where InviEZ = :InviCode");
  $PDOstt->bindValue(":InviCode", $InviEZ);
  $PDOstt->execute();
  $Dt = $PDOstt->fetch();

  $InviToken = $Dt["InviToken"];

  $_SESSION["InviToken"] = $InviToken;

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

  $PDOstt = $Connection->prepare("select UserID from accounts where Mail = :Mail");
  $PDOstt->bindValue(":Mail", $Mail);
  $PDOstt->execute();
  $Data = $PDOstt->fetch();

  if ($Data !== null && $Data !== false) {
    http_response_code(400);
    header("Location: /app/signup/index.php?emsg=MAIL_USED");
    break;
  }

  $DestGID = $Dt["GroupID"];
  $PDOstt = $Connection->prepare("select DisplayName, BelongSchoolID from group_profile where GroupID = :GroupID");
  $PDOstt->bindValue(":GroupID", $DestGID);
  $PDOstt->execute();
  $Dt = $PDOstt->fetch();

  if ($Dt === false || $Dt === null) {
    http_response_code(400);
    header("Location: /app/signup/index.php?emsg=GROUP_GONE");
    break;
  }

  $GroupName = $Dt["DisplayName"];
  $DestSID = $Dt["BelongSchoolID"];

  $PDOstt = $Connection->prepare("select DisplayName from school_profile where SchoolID = :SchoolID");
  $PDOstt->bindValue(":SchoolID", $DestSID);
  $PDOstt->execute();
  $Dt = $PDOstt->fetch();

  $SchoolName = $Dt["DisplayName"];

  $Actk = bin2hex(openssl_random_pseudo_bytes(32));
  $_SESSION["TmpActk"] = $Actk;

  $PDOstt = $Connection->prepare("insert into temp_accounts (`Token`, `Mail`, `PassHash`, `State`, `ReferToken`) VALUES (:Token, :Mail, :PassHash, :State, :ReferToken)");
  $PDOstt->bindValue(":Token", $Actk);
  $PDOstt->bindValue(":Mail", $Mail);
  $PDOstt->bindValue(":PassHash", password_hash($Passphrase, PASSWORD_BCRYPT));
  $PDOstt->bindValue(":State", "INVI_CONFIRM");
  $PDOstt->bindValue(":ReferToken", $InviToken);
  $Res = $PDOstt->execute();

  if (!$Res) {
    http_response_code(400);
    header("Location: /app/signup/index.php?emsg=INV_ARGS");
    break;
  }

  $tk =
  bin2hex(openssl_random_pseudo_bytes(32));
  $_SESSION["Tk"] = $tk;

  break;
}
?>

<!DOCTYPE html>
<html lang="ja">

<head>
  <title>SIGN UP</title>
  <link href="/bin/web/theme.css" rel="stylesheet">
  <link href="/app/signup/inv_up.css" rel="stylesheet">
  <link href="/app/signup/signup_general.css" rel="stylesheet">
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
    <h1>登録確認</h1>
    <hr>
    <div class="Column_Wrap" id="Welcome">
    <div id="Welcome_Left">
      <div id="Signup_Info">
      <h3 id="School_Name"><?php echo $SchoolName;?></h3>
      <h2 id="Group_Name"><?php echo $GroupName;?></h2>
      <p>へようこそ。</p>
      </div>
    </div>
    <form id="Add_Form" name="Add_Form" action="/app/signup/inv_apply.php" method="post">
      <label>登録する名前を入力
        <input type="text" name="UserName">
        <input type="hidden" name="Tk" value="<?php echo $tk; ?>">
        <button>GO</button>
      </label>
    </form>
    </div>
    <p class="Center_Desc">GO を押すと登録されます。</p>
    
  </article>

  <footer>(C)2021 SchedulePost</footer>
</body>

<script src="/bin/web/lib.js"></script>
<script src="/app/signup/index.js" async></script>

</html>