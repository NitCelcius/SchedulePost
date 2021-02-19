<?php
// Requirement: PHP 8.0.0 or later
// TODO: PHP envilonment requirement
// WIP.
$GLOBALS["DB_URL"] = getenv("DB_URL");
$GLOBALS["DB_Username"] = getenv("DB_UserName");
$GLOBALS["DB_PassPhrase"] = getenv("DB_PassPhrase");

if (($GLOBALS["DefaultTimeZone"] = getenv("DefaultTimeZone")) === null) {
  $GLOBALS["DefaultTimeZone"] = "UTC";
}

// TODO: Get rid of those. Let envilonment variables hold those.
$GLOBALS["SessionTokenExpiry"] = "1000 minutes";
$GLOBALS["LongTokenExpiry"] = "10 days";

$GLOBALS["Connection"] = null;

// RIP JSON data structure.
//$ProfilePathFormat = "/Data/Profiles/{School_UUID}/{Group_UUID}.json";
//$TimeTablePathFormat = "/Data/Schedules/{School_UUID}/{Group_UUID}/{Year}/{Month}/{Day}/{Version}.json";

define("ACCOUNT_OK", 1);
define("ACCOUNT_SESSION_TOKEN_INVALID", -1);
define("ACCOUNT_SESSION_TOKEN_EXPIRED", -2);
define("ACCOUNT_LONG_TOKEN_INVALID", -3);
define("ACCOUNT_LONG_TOKEN_EXPIRED", -4);
define("ACCOUNT_CREDENTIALS_INVALID", -5);

define("INTERNAL_EXCEPTION", -100);

// Following ISO-8601.
define("DAY_SUNDAY",   0);
define("DAY_MONDAY",   1);
define("DAY_TUESDAY",  2);
define("DAY_WEDNESDAY", 3);
define("DAY_THURSDAY", 4);
define("DAY_FRIDAY",   5);
define("DAY_SATURDAY", 6);

function ReplaceArgs(string $Basement, array $Args) {
  return str_replace(array_keys($Args), array_values($Args), $Basement);
}

class DayEnum {
  private const Dic = array(
    "DAY_MONDAY"    => DAY_MONDAY,
    "DAY_TUESDAY"   => DAY_TUESDAY,
    "DAY_WEDNESDAY" => DAY_WEDNESDAY,
    "DAY_THURSDAY"  => DAY_THURSDAY,
    "DAY_FRIDAY"    => DAY_FRIDAY,
    "DAY_SATURDAY"  => DAY_SATURDAY,
    "DAY_SUNDAY"    => DAY_SUNDAY
  );

  // Converts DAY enum to STRING. If out of the range, returns null. 
  static function EnumToStr(int $Day_Of_The_Date) {
    foreach (DayEnum::Dic as $Str => $Enum) {
      if ($Day_Of_The_Date === $Enum) {
        return $Str;
      }
    }
    return null;
  }

  // Converts STRING to DAY enum. If out of the range, returns null.
  static function StrToEnum(string $Str) {
    if (array_key_exists($Str, DayEnum::Dic)) {
      return DayEnum::Dic[$Str];
    } else {
      return null;
    }
  }
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
    if ($Username === null && $PassPhrase === null) {
      $Username = $GLOBALS["DB_Username"];
      $PassPhrase = $GLOBALS["DB_PassPhrase"];
    } else if ($Username === null || $PassPhrase === null) {
      throw new BadMethodCallException("The username or passphrase provided is empty.");
    }

    try {
      if ($Force_reconnect) {
        $GLOBALS["Connection"] = null;
      }
      if ($GLOBALS["Connection"] === null) {
        $Connection = new PDO(
          sprintf("mysql:host=%s;dbname=%s;charset=utf8", 
          $GLOBALS["DB_URL"], "schedulepost"), 
          $GLOBALS["DB_Username"], 
          $GLOBALS["DB_PassPhrase"], 
          array(
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET CHARACTER SET 'utf8mb4'",
            PDO::MYSQL_ATTR_FOUND_ROWS => true
          )
        );
        $Connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $GLOBALS["Connection"] = $Connection;
        return $Connection;
      } else {
        return $GLOBALS["Connection"];
      }
    } catch (Exception $e) {
      throw new ConnectionException("Could not connect to the database: " . $e->getMessage() . " Could not connect to the database using provided credentials.");
      return false;
    }

    return false;
  }
}

class UserAuth {
  private $UserID;
  private $SessionToken;
  private $LongToken; // TODO: Is this suitable for security reason?
  private $GroupID;
  private $Error;

  // これ基本のコンストラクタ
  function __construct($UserID = null, $SessionToken = null) {
    $this->UserID = $UserID;
    $this->SessionToken = $SessionToken;
  }


  /**
   * Sign-in using EMAIL and PASSPHRASE. Basically this converts arguments and sign-in using  SignInFromUserIDAndLongToken.
   *
   * @param string $Mail
   * @param string $PassPhrase
   * @return true|false If succeed, returns true. Otherwise returns false. Refer to $GetError() for more info.
   * @todo Consider how to create UserAuth object from Mail & pass
   */
  function SignInFromMailAndPassphrase(string $Mail, string $PassPhrase) {
    // Prioritize Mail & Passphrase if exists.
    $Fetch = new Fetcher();
    $this->UserID = $Fetch->Mail2UserID($Mail);
    // Trying NOT to use passphrase in POST.
    $LongToken = $this->GetLongTokenFromPassPhrase($PassPhrase);
    if ($LongToken !== false && $LongToken !== NULL) {
      // NOTE: this may makes the user sign-in twice.
      if ($this->SignInFromUserIDAndLongToken($this->UserID, $LongToken)) {
        $this->LongToken = $LongToken;
        return true;
      }
    } else {
      $this->Error = ACCOUNT_CREDENTIALS_INVALID;
      return false;
    }
  }

  /**
   * Sign-in using UserID and LongToken.
   *
   * @param string $UserID
   * @param string $LongToken
   * @return true|false If succeed, returns true. Otherwise returns false. Refer to $GetError() for more info.
   */
  function SignInFromUserIDAndLongToken(string $UserID, string $LongToken) {
    $SessionToken = $this->GetSessionTokenFromLongToken($LongToken);
    if ($SessionToken !== false && $SessionToken !== NULL) {
      $this->UpdateLastActivity();
      $this->UserID = $UserID;
      $this->LongToken = $LongToken;
      $this->SessionToken = $SessionToken;

      return $this->SignIn();
    } else {
      $this->Error = ACCOUNT_CREDENTIALS_INVALID;
      return false;
    }
  }

  // Read-only.
  function GetUserID() {
    return $this->UserID;
  }

  // Read-only.
  function GetSessionToken() {
    return $this->SessionToken;
  }

  // Read-only.
  function GetLongToken() {
    return $this->LongToken;
  }

  // Returns the group UUID that the user belongs to.
  // Returns NULL if the user does not belong to any, and stores false.
  // If $Force_Update is set TRUE, updates the group ID.
  function GetGroupID(bool $Force_Update = false) {
    if ($Force_Update) {
      $this->GroupID = null;
    }
    if ($this->GroupID === null) {
      try {
        $Connection = DBConnection::Connect();
        $PDOstt = $Connection->prepare("select BelongGroupID from user_profile where BelongUserID = :UserID");
        $PDOstt->execute(array(
          ":UserID" => $this->UserID
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

  function SignIn(bool $Record_Activity = true) {
    $Connection = DBConnection::Connect();
    try {
      $PDOstt = $Connection->prepare("select SessionToken,LastActivityAt from schedulepost.accounts where UserID = :UserID");
      $PDOstt->bindValue(":UserID", $this->UserID);
      $PDOstt->execute();
      $Data = $PDOstt->fetch();
      if ($this->SessionToken != $Data["SessionToken"]) {
        $this->Error = ACCOUNT_SESSION_TOKEN_INVALID;
        return false;
      }
    } catch (PDOException $e) {
      throw new ConnectionException("Could not communicate with the database: " . $e->getMessage(), "DATABASE");
      return false;
    }
    if ($Data === null || $Data === false) {
      return false;
    } else {
      // Is expired?
      $CurrentTime = new DateTime();
      $Expiry = new DateTime($Data["LastActivityAt"]);
      $Expiry->add(DateInterval::createFromDateString($GLOBALS["SessionTokenExpiry"]));
      if ($Expiry > new $CurrentTime) {
        if ($Record_Activity) {
          $this->UpdateLastActivity();
        }
        return true;
      } else {
        $this->Error = ACCOUNT_SESSION_TOKEN_EXPIRED;
        return false;
      }
    }
  }

  private function UpdateLastActivity() {
    $Connection = DBConnection::Connect();
    $UpdateDateTime = new DateTime();
    $PDOstt = $Connection->prepare("update `accounts` SET `LastActivityAt`=:CurrentTime where UserID = :UserID");
    $PDOstt->bindValue(":UserID", $this->UserID);
    $PDOstt->bindValue(":CurrentTime", $UpdateDateTime->format("Y-m-d H:i:s"), PDO::PARAM_STR);
    $PDOstt->execute();
    $Data = $PDOstt->fetch();

    //TODO: Is there any errors in SQL?
    return ($Data !== false && ($PDOstt->rowCount() > 0)) ? true : false;
  }

  private function GetErrorCode() {
    switch ($this->Error) {
      case ACCOUNT_SESSION_TOKEN_EXPIRED: {
          return "ACCOUNT_SESSION_TOKEN_EXPIRED";
          break;
        }
      case ACCOUNT_SESSION_TOKEN_INVALID: {
          return "ACCOUNT_SESSION_TOKEN_INVALID";
          break;
        }
      case ACCOUNT_LONG_TOKEN_INVALID: {
          return "ACCOUNT_LONG_TOKEN_INVALID";
          break;
        }
      case ACCOUNT_LONG_TOKEN_EXPIRED: {
          return "ACCOUNT_LONG_TOKEN_EXPIRED";
          break;
        }
      case ACCOUNT_CREDENTIALS_INVALID: {
          return "ACCOUNT_CREDENTIALS_INVALID";
          break;
        }
    }

    return "ERROR_UNKNOWN";
  }

  // Do we even need this as a part of API feature?
  function GetError() {
    $Error = array(
      "Code" => $this->GetErrorCode(),
      //"Message" => null
    );

    /*
    switch ($this->Error) {
      case ACCOUNT_SESSION_TOKEN_EXPIRED: {
          $Error["Message"] = "The account session token is expired.";
          break;
      }
      case ACCOUNT_SESSION_TOKEN_INVALID: {
          $Error["Message"] = "The account session token is invalid.";
          break;
      }
      case 
    }
    */

    return $Error;
  }

  /**
   * @throws ConnectionException
   * @throws InvalidCredentialsException
   * @return string|false this function returns LONGTOKEN.
   */
  function GetLongTokenFromPassPhrase(string $PassPhrase) {
    //TODO: Obtain LongToken.
    $Connection = DBConnection::Connect();
    $VerifyHash = null;
    if ($this->UserID === null) {
      $this->Error = INTERNAL_EXCEPTION;
      throw new UnexpectedValueException("The UserID is not set. This is possibly a bug!");
    }

    try {
      $PDOstt = $Connection->prepare("select PassHash from schedulepost.accounts where UserID = :UserID");
      if ($PDOstt === false) {
        throw new ConnectionException("Could not connect to the database.", "Database: SchedulePost");
      }
      $PDOstt->bindValue(":UserID", $this->UserID);
      $PDOstt->execute();
      $Data = $PDOstt->fetch(PDO::FETCH_ASSOC);
      $VerifyHash = $Data["PassHash"];
    } catch (Exception $e) {
      throw new ConnectionException("Could not process connection properly: " . $e->getMessage(), "Internal function");
      return false;
    }

    if (password_verify($PassPhrase, $VerifyHash)) {
      try {
        $LongToken = bin2hex(openssl_random_pseudo_bytes(64));
        $PDOstt = $Connection->prepare("update accounts set LongToken = :LongToken,LongTokenGenAt = :LongTokenGenAt where UserID = :UserID");
        if ($PDOstt === false) {
          throw new ConnectionException("Could not connect to the database.", "Database: SchedulePost");
          return false;
        }
        $UpdateDateTime = new DateTime("now", new DateTimeZone($GLOBALS["DefaultTimeZone"]));
        $PDOstt->bindValue(":UserID", $this->UserID);
        $PDOstt->bindValue(":LongToken", $LongToken);
        $PDOstt->bindValue(":LongTokenGenAt", $UpdateDateTime->format("Y-m-d H:i:s"));
        $PDOstt->execute();
        $Data = $PDOstt->fetch(PDO::FETCH_ASSOC);

        // Perhaps this is unnecessary.
        $this->UpdateSessionToken();

        return $LongToken;
      } catch (ConnectionException $e) {
        throw $e;
        return false;
      } catch (Exception $e) {
        throw new ConnectionException("Could not process connection properly: " . $e->getMessage(), "Internal function");
        return false;
      }
    } else {
      throw new InvalidCredentialsException("The provided passphrase is invalid.", "PASSPHRASE");
      return false;
    }
  }

  function GetSessionTokenFromLongToken(string $LongToken) {
    $Connection = DBConnection::Connect();
    $PDOstt = $Connection->prepare("select LongTokenGenAt from schedulepost.accounts where UserID = :UserID AND LongToken = :LongToken");
    if ($PDOstt === false) {
      throw new ConnectionException("Could not connect to the database.", "Database: SchedulePost");
    }
    $PDOstt->bindValue(":UserID", $this->UserID);
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
      throw new InvalidCredentialsException("The user ID or long token provided is invalid.", "Database: SchedulePost");
      return false;
    }
  }

  function UpdateSessionToken() {
    try {
      $Connection = DBConnection::Connect();
      $Updater = $Connection->prepare("Update `accounts` set `LastSigninAt` = :LoginDateTime,`SessionToken` = :SessionToken WHERE UserID = :UserID");
      $Token = bin2hex(openssl_random_pseudo_bytes(32));
      $LoginDateTime = new DateTime("now", new DateTimeZone($GLOBALS["DefaultTimeZone"]));
      $Updater->bindValue(":LoginDateTime", $LoginDateTime->format("Y-m-d H:i:s"), PDO::PARAM_STR);
      $Updater->bindValue(":SessionToken", $Token);
      $Updater->bindValue(":UserID", $this->UserID, PDO::PARAM_STR);
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
    if (preg_match("/[0-9a-f]{32}/", $this->UserID)) {
      return true;
    } else {
      return false;
    }
  }
}

class Fetcher {
  private $Connection;
  private $User;

  function __construct(UserAuth $Auth = null) {
    if ($Auth !== null) {
      if ($Auth->SignIn()) {
        $this->User = $Auth;
      } else {
        throw new UnexpectedValueException("Authentication data is invalid so Fetcher cannot use the provided credentials.");
      }
    }
  }

  function Connect() {
    $this->Connection = DBConnection::Connect();
    //TODO: TOKEN 種類とって where 以下を変更する。
  }

  function Mail2UserID(string $Email) {
    $Connection = DBConnection::Connect();
    $PDOstt = $Connection->prepare("select UserID from schedulepost.accounts where Mail = :Mail");
    if ($PDOstt === false) {
      throw new ConnectionException("Could not connect to the database.", "Database: SchedulePost");
    }
    $PDOstt->bindValue(":Mail", $Email);
    $PDOstt->execute();
    $Data = $PDOstt->fetch(PDO::FETCH_ASSOC);
    if ($Data !== NULL && $Data !== false) {
      return $Data["UserID"];
    } else {
      throw new InvalidArgumentException("The email is not registered.");
      return null;
    }
  }


  function IsPermitted(UserAuth $User, string $Command) {
  }

  function GetTimetable(string $GroupID, DateTime $Date, int $Revision = null) {
    //TODO: Need to verify things here, but ignoring for now
    $Base = $this->GetDefaultTimetable($GroupID, ((int)$Date->format("w")));
    $Diff = $this->GetTimetableDiff($GroupID, $Date, $Revision);

    return array_merge($Base, $Diff);
  }

  function GetDefaultTimetable(string $GroupID, int $Day_Of_The_Date) {
    switch ($Day_Of_The_Date) {
      case DAY_SUNDAY:
      case DAY_MONDAY:
      case DAY_TUESDAY:
      case DAY_WEDNESDAY:
      case DAY_THURSDAY:
      case DAY_FRIDAY:
      case DAY_SATURDAY:
        break;
      default:
        throw new UnexpectedValueException("The day of the date is out of range. Make sure you have provided the correct day.");
    }

    $PDO = DBConnection::Connect();
    $PDOstt = $PDO->prepare("select Body from default_timetable where BelongGroupID = :GroupID");
    $PDOstt->bindValue(":GroupID", $GroupID, PDO::PARAM_STR);
    $PDOstt->execute();
    $Result = $PDOstt->fetch();

    if ($Result === null || $Result === false) {
      throw new ConnectionException("Could not connect to the database properly.");
      return false;
    }

    $DefaultTimeTable = json_decode($Result[0], true);
    $DayStr = DayEnum::EnumToStr($Day_Of_The_Date);

    if ($DefaultTimeTable === false || $DefaultTimeTable === null) {
      throw new UnexpectedValueException("The JSON of default timetable is malformed.");
    } else if (!array_key_exists($DayStr, $DefaultTimeTable)) {
      throw new OutOfBoundsException("The default timetable does not contain the index: \"" . $DayStr . "\"");
    }

    return $DefaultTimeTable[$DayStr];
  }

  function GetTimetableDiff(string $GroupID, DateTime $Date, int $Revision = null) {
    $PDO = DBConnection::Connect();
    if ($Revision === null) {
      $PDOstt = $PDO->prepare("select Revision,Body from timetable where BelongGroupID = :GroupID and Date = :Date order by 'Revision' DESC");
    } else {
      $PDOstt = $PDO->prepare("select Revision,Body from timetable where BelongGroupID = :GroupID and Date = :Date and Revision = :Revision order by 'Revision' DESC");
      $PDOstt->bindValue(":Revision", $Revision, PDO::PARAM_INT);
    }
    $PDOstt->bindValue(":GroupID", $GroupID);
    $PDOstt->bindValue(":Date", $Date->format("Y-m-d"), PDO::PARAM_STR);
    $PDOstt->execute();
    $Result = $PDOstt->fetchAll();

    if ($Result === null || $Result === false) {
      throw new ConnectionException("Could not connect to the database properly.");
      return false;
    }
    $Diff = json_decode($Result["0"]["Body"], true);

    if ($Diff === false) {
      throw new UnexpectedValueException("The JSON of the specified timetable is malformed.");
    }

    return $Diff;
  }
}

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

  /* Please note that SchedulePost API does not support any GET method. */

  //Authenticate here
  //Probs insert this part on request header

  switch ($Recv["Action"]) {
    case "SIGN_IN": {
        $UserID = null;
        $Resp = array(
          "Result" => false,
          "ReasonCode" => "UNEXPECTED_ARGUMENT",
          "ReasonText" => "The information provided to sign-in is insuffcient."
        );

        if (array_key_exists("Mail", $Recv["Auth"]) && array_key_exists("PassPhrase", $Recv["Auth"])) {
          try {
            $User = new UserAuth();
            switch ($User->SignInFromMailAndPassphrase($Recv["Auth"]["Mail"], $Recv["Auth"]["PassPhrase"])) {
              case true:
                $Resp = array(
                  "Result" => true,
                  "UserID" => $User->GetUserID(),
                  "SessionToken" => $User->GetSessionToken(),
                  "LongToken" => $User->GetLongToken()
                );
                break;
              
              case false:
                break;
            }

          } catch (ConnectionException $e) {
            $Resp = array(
              "Result" => false,
              "ReasonCode" => "INTERNAL_EXCEPTION",
              "ReasonText" => "There was an internal exception whlist trying to sign in:  " . $e->getMessage()
            );
            error_log("SIGNIN: An error occurred whlist trying to sign in using passphrase. " . $e->getMessage() . " Stack trace:" . $e->getTraceAsString());
          } catch (InvalidCredentialsException $e) {
            $Resp = array(
              "Result" => false,
              "ReasonCode" => "INVALID_CREDENTIALS",
              "ReasonText" => "The passphrase provided is invalid. " . $e->getMessage()
            );
          } catch (InvalidArgumentException $e) {
            $Resp = array(
              "Result" => false,
              "ReasonCode" => "INVALID_CREDENTIALS",
              "ReasonText" => "The e-mail address provided is invalid. " . $e->getMessage()
            );
          } catch (Exception $e) {
            $Resp = array(
              "Result" => false,
              "ReasonCode" => "INTERNAL_EXCEPTION",
              "ReasonText" => "There was an internal exception whlist trying to sign in. " . $e->getMessage()
            );
          }
        } else {
          if (array_key_exists("UserID", $Recv["Auth"]) && array_key_exists("LongToken", $Recv["Auth"])) {
            try {
              $User = new UserAuth();
              switch ($User->SignInFromUserIDAndLongToken($Recv["Auth"]["UserID"], $Recv["Auth"]["LongToken"])) {
                case true:
                  $Resp = array(
                    "Result" => true,
                    "UserID" => $User->GetUserID(),
                    "SessionToken" => $User->GetSessionToken(),
                    "LongToken" => $User->GetLongToken()
                  );
                  break;

                case false:
                  break;
              }
            } catch (ConnectionException $e) {
              $Resp = array(
                "Result" => false,
                "ReasonCode" => "INTERNAL_EXCEPTION",
                "ReasonText" => "There was an internal exception whlist trying to sign in:  " . $e->getMessage()
              );
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
          }
        }
        break;
      }

    case "GET_SCHEDULE": {
        $User = new UserAuth($Recv["Auth"]["UserID"], $Recv["Auth"]["SessionToken"]);
        if (!$User->SignIn()) {
          $Error = $User->GetError();
          $Resp = array(
            "Result" => false,
            "ReasonCode" => $Error["Code"],
            "ReasonText" => "Could not sign in with the provided credentials. " . ", " . $Error["Code"]
          );
          break;
        }

        $Date = new DateTime($Recv["Date"]);
        $Fetcher = new Fetcher($User);
        $Fetcher->GetDefaultTimetable(
          $User->GetGroupID(),
          // Note here: Because PHP Datetime::format() format character "w" follows ISO-8601, DayEnum corresponds to it.
          (int)$Date->format("w")
        );
        $Result = json_encode($Fetcher->GetTimetable($User->GetGroupID(), $Date), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($Result != false) {
          $Resp = array(
            "Result" => true,
            "ReasonCode" => "",
            "Body" => $Result
          );
        }
        break;
      }

    case "GET_USER_PROFILE": {
        $User = new UserAuth($Recv["Auth"]["UserID"], $Recv["Auth"]["SessionToken"]);
        if (!$User->SignIn()) {
          $Error = $User->GetError();
          $Resp = array(
            "Result" => false,
            "ReasonCode" => $User->GetError()["Code"],
            "ReasonText" => "Could not sign in with the provided credentials."
          );
          break;
        }

        // Fetch user profile(raw)
        $Connection = DBConnection::Connect();
        $PDOstt = $Connection->prepare("select BelongGroupID, BelongSchoolID, DisplayName from user_profile where BelongUserID = :UserID");
        $PDOstt->bindValue(":UserID", $User->GetUserID());
        $PDOstt->execute();
        $Data = $PDOstt->fetch();
        if ($Data === false || $Data === null) {
          $Resp = array(
            "Result" => false,
            "ReasonCode" => "INTERNAL_EXCEPTION",
            "ReasonText" => "There was an internal error while trying to fetch user profile."
          );
          break;
        }
        $UserDisplayName = $Data["DisplayName"];
        $GroupID = $Data["BelongGroupID"];
        $SchoolID = $Data["BelongSchoolID"];

        // Fetch school name
        $PDOstt = $Connection->prepare("select DisplayName from school_profile where SchoolID = :SchoolID");
        $PDOstt->bindValue(":SchoolID", $SchoolID);
        $PDOstt->execute();
        $Data = $PDOstt->fetch();
        if ($Data === false || $Data === null) {
          $Resp = array(
            "Result" => false,
            "ReasonCode" => "INTERNAL_EXCEPTION",
            "ReasonText" => "There was an internal error while trying to fetch user profile."
          );
          break;
        }
        $SchoolDisplayName = $Data["DisplayName"];

        // Fetch group name
        $PDOstt = $Connection->prepare("select DisplayName from group_profile where GroupID = :GroupID");
        $PDOstt->bindValue(":GroupID", $GroupID);
        $PDOstt->execute();
        $Data = $PDOstt->fetch();
        if ($Data === false || $Data === null) {
          $Resp = array(
            "Result" => false,
            "ReasonCode" => "INTERNAL_EXCEPTION",
            "ReasonText" => "There was an internal error while trying to fetch user profile."
          );
          break;
        }
        $GroupDisplayName = $Data["DisplayName"];

        $Resp = array(
          "Result" => true,
          "Profile" => array(
            "School" => array(
              "ID" => $SchoolID,
              "DisplayName" => $SchoolDisplayName,
            ),
            "Group" => array(
              "ID" => $GroupID,
              "DisplayName" => $GroupDisplayName
            ),
            "User" => array(
              "ID" => $User->GetUserID(),
              "DisplayName" => $UserDisplayName
            )
          )
        );
      }

      /*
      case "REFRESH_SESSION_TOKEN": {
        $User = new UserAuth($Recv["Auth"]["UserID"], $Recv["Auth"]["SessionToken"]);
        if (!$User->SignIn()) {
          $Resp = array(
            "Result" => false,
            "ReasonCode" => "INVALID_CREDENTIALS",
            "ReasonText" => " Could not sign in with the provided credentials." . $User->GetError()["Code"] . ", " . $User->GetError()["Message"]
          );
          break;
        }

        $Resp = array(
          "Result" => true
        );
      }
      */
  }
  break;
}

echo json_encode($Resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

exit;
