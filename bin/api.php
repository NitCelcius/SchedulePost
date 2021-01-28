<?php
// Requirement: PHP 8.0.0 or later
// TODO: PHP envilonment requirement
// WIP.

$GLOBALS["DB_URL"] = getenv("DB_URL");
$GLOBALS["DB_Username"] = getenv("DB_UserName");
$GLOBALS["DB_PassPhrase"] = getenv("DB_PassPhrase");

$GLOBALS["SessionTokenExpiry"] = "10 minutes";
$GLOBALS["LongTokenExpiry"] = "10 days";

function ReplaceArgs(string $Basement, array $Args) {
  return str_replace(array_keys($Args), array_values($Args), $Basement);
}

$ProfilePathFormat = "/Data/Profiles/{School_UUID}/{Group_UUID}.json";
$TimeTablePathFormat = "/Data/Schedules/{School_UUID}/{Group_UUID}/{Year}/{Month}/{Day}/{Version}.json";

$Resp = array(
  "Result" => false,
  "ReasonCode" => "ERROR_UNKNOWN",
  "ReasonText" => "The API did not respond properly to your request."
);

// BASICではよくある、 while(true) -> break. try~catch(exception e)~finally ができるやり方。
while (true) {
  $Recv = json_decode(file_get_contents("php://input"), true);

  if ($Recv === null) {
    $Resp["ReasonCode"] = "INPUT_MALFORMED";
    $Resp["ReasonText"] = "The provided JSON was malformed so the API could not recognize.";
    break;
  }

  //Authenticate here
  //Probs insert this part on request header


  switch ($Recv["Action"]) {
    case "SIGN_IN": {
        $User = new UserAuth($Recv["Auth"]["UserID"]);

        if ($Recv["Auth"]["LongToken"] !== NULL) {

          try {
            $SessionToken = $User->SignInFromLongToken($Recv["Auth"]["LongToken"]);
            if ($SessionToken !== false && $SessionToken !== NULL) {
              $Resp = array(
                "Result" => true,
                "SessionToken" => $SessionToken
              );
            } else {
              $Resp = array(
                "Result" => false,
                "ReasonCode" => "INVALID_CREDENTIALS",
                "ReasonText" => "The long token provided is invalid, or expired."
              );
            }
          } catch (ConnectionException $e) {
            $Resp = array(
              "Result" => false,
              "ReasonCode" => "INTERNAL_EXCEPTION",
              "ReasonText" => "There was an internal exception whlist trying to sign in:  " . $e->getMessage()
            );
            echo ("SIGNIN: An error occurred whlist trying to sign in using long token. " . $e->getMessage() . " Stack trace:" . $e->getTraceAsString());
            error_log("SIGNIN: An error occurred whlist trying to sign in using long token. " . $e->getMessage() . " Stack trace:" . $e->getTraceAsString());
          } catch (InvalidCredentialsException $e) {
            $Resp = array(
              "Result" => false,
              "ReasonCode" => "INVALID_CREDENTIALS",
              "ReasonText" => "The long token provided is invalid. " . $e->getMessage()
            );
          } catch (Exception $e) {
            $Resp = array(
              "Result" => false,
              "ReasonCode" => "INTERNAL_EXCEPTION",
              "ReasonText" => "There was an internal exception whlist trying to sign in. " . $e->getMessage()
            );
          }
        } else {
          // Trying NOT to use passphrase in POST.
          $User->SignInFromPassPhrase($Recv["Auth"]["PassPhrase"]);
        }
      }

    case "GET_SCHEDULE": {
        $User = new UserAuth($Recv["Auth"]["UserID"]);
        $User->UpdateSessionToken();
        if ($User->SignIn()) {
        }

        break;
      }
  }
  $School_UUID = $Recv["Auth"]["School_UUID"];
  $Group_UUID = $Recv["Auth"]["Group_UUID"];

  // This could be request time or use requested data?
  // Should be converted to LOCAL TIMEZONE (of school)
  $TimeObj = new DateTime($Recv["Options"]["Date"]);

  // Revision. Must be fetched before, not constant
  $RecentRev = 1;
  /*
  $TimeTablePath = ReplaceArgs($TimeTablePathFormat, array(
    "{School_UUID}" => $School_UUID,
    "{Group_UUID}" => $Group_UUID,
    "{Year}" => $TimeObj->format('Y'),
    "{Month}" => $TimeObj->format('n'),
    "{Day}" => $TimeObj->format('j'),
    "{Version}" => $RecentRev
  ));
  */

  break;
}

class ConnectionException extends Exception {
  private $Message;

  public function __construct($Message, $Location = null, $Prev = null) {
    $this->Message = $Message;
    $this->Location = $Location;
    parent::__construct($Message, 0, null);
  }

  public function __toString() {
    return "Connection Error. Additional information: " . $this->Message . " Location: " . $this->Location;
  }
}

class InvalidCredentialsException extends Exception {
  private $Message;
  public function __construct($Message, $Location = null, $Prev = null) {
    $this->Message = $Message;
    $this->Location = $Location;
    parent::__construct($Message, 0, null);
  }

  public function __toString() {
    return "Invalid credentials provided. Additional information: " . $this->Message . " Location: " . $this->Location;
  }
}

class DBConnection {

  static function Connect(string $Username = null, string $PassPhrase = null, bool $Force_reconnect = false) {
    static $CacheConnection;
    if ($Username === null && $PassPhrase === null) {
      $Username = $GLOBALS["DB_Username"];
      $PassPhrase = $GLOBALS["DB_PassPhrase"];
    } else if ($Username === null || $PassPhrase === null) {
      throw new BadMethodCallException("The username or passphrase provided is empty.");
    }
    try {
      if ($Force_reconnect) {
        $CacheConnection = null;
      }
      if ($CacheConnection === null) {
        $Connection = new PDO(sprintf("mysql:host=%s;dbname=%s;charset=utf8", $GLOBALS["DB_URL"], "schedulepost"), $GLOBALS["DB_Username"], $GLOBALS["DB_PassPhrase"], array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET CHARACTER SET 'utf8mb4'"));
        $Connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $CacheConnection = $Connection;
        return $Connection;
      } else {
        return $CacheConnection;
      }
    } catch (Exception $e) {
      throw new ConnectionException("Could not connect to the database: " . $e->getMessage() . " Could not connect to the database using provided credentials.");
      return false;
    }
    return false;
  }
}

class UserAuth {
  private $UUID;
  private $SessionToken;
  private $GroupID;

  function __construct($UserID, $SessionToken = null) {
    $this->UUID = $UserID;
    $this->SessionToken = $SessionToken;
    if (!($this->Validate())) {
      throw new UnexpectedValueException("User UUID or Token is invalid.");
    }
  }

  // Read-only.
  function GetUUID() {
    return $this->UUID;
  }

  // Read-only.
  function GetSessionToken() {
    return $this->SessionToken;
  }

  // Returns the group UUID that the user belongs to.
  // Returns NULL if the user does not belong to any, and stores false.
  // If $Force_Update is set TRUE, updates the group ID.
  function GetGroupID(bool $Force_Update = false) {
    if ($Force_Update) { $this->GroupID = null; }
    if ($this->GroupID === null) {
      try {
        $Connection = DBConnection::Connect();
        $PDOstt = $Connection->prepare("select BelongGroupID from user_profile where BelongUserID = :UserID");
        $PDOstt->execute(array(
          ":UserID" => $this->UUID
        ));
        $Data = $PDOstt->fetch(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
        throw new ConnectionException("Could not connect to the database, or could not process properly. " . $e->getMessage(), "DATABASE");
      }
      if (array_key_exists("BelongGroupID", $Data) && $Data["BelongGroupID"] !== NULL) {
        $this->GroupID = $Data["BelongGroupID"];
        return $this->GroupID;
      } else {
        $this->GroupID = false;
        return null;
      }
    } else if ($this->GroupID === false) {
      return null;
    } else {
      return $this->GroupID;
    }
  }

  function SignIn() {
    $Connection = DBConnection::Connect();
    try {
      $PDOstt = $Connection->prepare("select UserID from schedulepost.accounts where UserID = :UserID AND SessionToken = :Token");
      $PDOstt->bindValue(":UserID", $this->UUID);
      $PDOstt->bindValue(":Token", $this->SessionToken);
      $Result = $PDOstt->execute();
      if ($Result === null) {
        return false;
      } else {
        return true;
      }
    } catch (Exception $e) {
    }
  }

  function Mail2UUID(string $Email) {
  }

  function SignInFromPassPhrase(string $PassPhrase) {
    $Connection = DBConnection::Connect();
    try {
      $PDOstt = $Connection->prepare("select PassHash from schedulepost.accounts where UserID = :UserID");
      if ($PDOstt === false) {
        throw new ConnectionException("Could not connect to the database.", "Database: SchedulePost");
      }
      $PDOstt->bindValue(":UserID", $this->UUID);
      $PDOstt->execute();
      $Data = $PDOstt->fetch(PDO::FETCH_ASSOC);
      if (password_verify($PassPhrase, $Data["PassHash"])) {
        $this->UpdateSessionToken();
        return $this->Token;
      } else {
        throw new InvalidCredentialsException("The provided passphrase is invalid.", "PASSPHRASE");
        return false;
      }
    } catch (Exception $e) {
      throw new ConnectionException("Could not process connection properly: " . $e->getMessage(), "Internal function");
      return false;
    }
  }

  function SignInFromLongToken(string $LongToken) {
    $Connection = DBConnection::Connect();
    $PDOstt = $Connection->prepare("select LongTokenGenAt from schedulepost.accounts where UserID = :UserID AND LongToken = :LongToken");
    if ($PDOstt === false) {
      throw new ConnectionException("Could not connect to the database.", "Database: SchedulePost");
    }
    $PDOstt->bindValue(":UserID", $this->UUID);
    $PDOstt->bindValue(":LongToken", $LongToken);
    $PDOstt->execute();

    $Data = $PDOstt->fetch(PDO::FETCH_ASSOC);
    if ($Data["LongTokenGenAt"] !== NULL) {
      $CurrentTime = new DateTime();
      $Expiry = new DateTime($Data["LongTokenGenAt"]);
      $Expiry->add(DateInterval::createFromDateString($GLOBALS["LongTokenExpiry"]));
      if ($CurrentTime < $Expiry) {
        $this->UpdateSessionToken();
        return $this->Token;
      } else {
        throw new InvalidCredentialsException("Long token is expired.", "LONG_TOKEN");
        return false;
      }
    } else {
      throw new InvalidCredentialsException("The UUID or long token provided is invalid.", "Database: SchedulePost");
      return false;
    }
  }

  function UpdateSessionToken() {
    try {
      $Connection = DBConnection::Connect();
      $Updater = $Connection->prepare("Update `accounts` set `LastSigninAt` = :LoginDateTime,`SessionToken` = :SessionToken WHERE UserID = :UserID");
      $Token = bin2hex(openssl_random_pseudo_bytes(32));
      $LoginDateTime = new DateTime("now", new DateTimeZone("UTC"));
      $Updater->bindValue(":LoginDateTime", $LoginDateTime->format("Y-m-d H:i:s"), PDO::PARAM_STR);
      $Updater->bindValue(":SessionToken", $Token);
      $Updater->bindValue(":UserID", $this->UUID, PDO::PARAM_STR);
      $Data = $Updater->execute();
      if ($Data === false) {
        throw new ConnectionException("Database refused to update.", "Database: SchedulePost");
      }
      $this->Token = $Token;
      return true;
    } catch (Exception $e) {
      throw new ConnectionException("Could not update database: " . $e->getMessage(), "Internal function");
      return false;
    }
  }

  // 形式上合っているかどうか。$Token どうしようかな...。
  function Validate() {
    if (preg_match("/[0-9a-f]{32}/", $this->UUID)) {
      return true;
    } else {
      return false;
    }
  }
}

class Fetcher {
  private $Connection;
  private $User;

  function __construct(UserAuth $Auth) {
    if ($Auth->Validate()) {
      $this->User = $Auth;
    } else {
      throw new UnexpectedValueException("Authentication data is invalid so Fetcher cannot use the provided credentials.");
    }
  }

  function Connect() {
    try {
      $this->Connection = DBConnection::Connect();
      //TODO: TOKEN 種類とって where 以下を変更する。
    } catch (Exception $e) {
      var_dump($e);
      exit;
    }
  }

  function IsPermitted(UserAuth $User, string $Command) {
  }

  function GetTokenValidity() {
    $this->Connection->prepare();
  }

  function GetSchedule(string $User, string $GroupID, DateTime $Date) {
  }
}

echo "<br>";
echo json_encode($Resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
