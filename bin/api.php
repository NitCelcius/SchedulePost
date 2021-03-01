<?php
// Requirement: PHP 8.0.0 or later
// TODO: PHP envilonment requirement
// WIP.

//error_reporting(0);

$GLOBALS["DB_URL"] = getenv("DB_URL");
$GLOBALS["DB_Username"] = getenv("DB_UserName");
$GLOBALS["DB_PassPhrase"] = getenv("DB_PassPhrase");

if (($GLOBALS["DefaultTimeZone"] = getenv("DefaultTimeZone")) === null) {
  $GLOBALS["DefaultTimeZone"] = "UTC";
}

// TODO: Get rid of those. Let envilonment variables hold those.
$GLOBALS["SessionTokenExpiry"] = "30 minutes";
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
define("ACCOUNT_INSUFFCIENT_PERMISSION", -10);


define("INTERNAL_EXCEPTION", -100);

// Following ISO-8601.
define("DAY_SUNDAY",   0);
define("DAY_MONDAY",   1);
define("DAY_TUESDAY",  2);
define("DAY_WEDNESDAY", 3);
define("DAY_THURSDAY", 4);
define("DAY_FRIDAY",   5);
define("DAY_SATURDAY", 6);

define("DEST_SCHOOL", 1);
define("DEST_GROUP", 2);

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

// This might be rewritten into JSON but do not want to be modified.
class Permissions {
  //Perhaps this is not really necessary.
  const Dic = array(
    "Administration.Admin" => array(
      "Default" => false,
      "DefaultAllowRoles" => array("Admin")
    ),
    // SCHOOL
    "Timetable.View" => array(
      "Default" => true,
      "DefaultAllowRoles" => array("Admin", "Teacher", "Student"),
      "Elevation" => true
    ),
    "Timetable.ViewOthers" => array(
      "Default" => false,
      "DefaultAllowRoles" => array("Admin", "Teacher"),
      "Elevation" => true
    ),
    "Timetable.CreateBase" => array(
      "Default" => false,
      "DefaultAllowRoles" => array("Admin", "Teacher"),
      "Elevation" => true
    ),
    "Timetable.Edit" => array(
      "Default" => false,
      "DefaultAllowRoles" => array("Admin", "Teacher"),
      "Elevation" => true
    )
  );

  static function IsExist(string $Permission) {
    return array_key_exists($Permission, Permissions::Dic);
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

class InsuffcientPermissionException extends Exception {
  private $Message;

  public function __construct($Message, $Location = null, $Prev = null) {
    $this->Message = $Message;
    $this->Location = $Location;
    parent::__construct($Message, 0, null);
  }

  public function __toString() {
    return "Insuffcient permission. Additional information: " . $this->Message . " Location: " . $this->Location;
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
          sprintf(
            "mysql:host=%s;dbname=%s;charset=utf8",
            $GLOBALS["DB_URL"],
            "schedulepost"
          ),
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
  private $SchoolID;
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

  // Returns the school UUID that the user belongs to.
  // Returns NULL if the user does not belong to any, and stores false.
  // If $Force_Update is set TRUE, updates the school ID.
  function GetSchoolID(bool $Force_Update = false) {
    if ($Force_Update) {
      $this->SchoolID = null;
    }
    if ($this->SchoolID === null) {
      try {
        $Connection = DBConnection::Connect();
        $PDOstt = $Connection->prepare("select BelongSchoolID from user_profile where BelongUserID = :UserID");
        $PDOstt->execute(array(
          ":UserID" => $this->UserID,
        ));
        $Data = $PDOstt->fetch(PDO::FETCH_ASSOC);
      } catch (PDOException $e) {
        throw new ConnectionException("Could not connect to the database, or could not process properly. " . $e->getMessage(), "DATABASE");
      }
      if (array_key_exists("BelongSchoolID", $Data) && $Data["BelongSchoolID"] !== NULL) {
        $this->SchoolID = $Data["BelongSchoolID"];
        return $this->SchoolID;
      } else {
        $this->SchoolID = false;
        return null;
      }
    } else if ($this->SchoolID === false) {
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
      //var_dump($Data);
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
      $CurrentTime = new DateTime("now", new DateTimeZone($GLOBALS["DefaultTimeZone"]));;
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

  /**
   * See if the user has the permission.
   *
   * @param string $Action - The action to look-up permission.
   * @param integer $TargetType Group/School. Use DEST_GROUP or DEST_SCHOOL.
   * @param string $TargetID The target UUID.
   * @return bool If permitted, returns true. Otherwise false.
   * @var Permissions::Dic
   */
  function IsPermitted(string $Action, int $TargetType, string $TargetID) {
    // TODO: TO get rid of Permissions table, switch target table to lookup.
    if ($TargetID === null) {
      throw new InvalidArgumentException("The targetID is not correct.");
      return null;
    }
    $Connection = DBConnection::Connect();
    switch ($TargetType) {
      case DEST_SCHOOL: {
          $PDOstt = $Connection->prepare("select Permissions from schedulepost.school_permissions where UserID = :UserID AND TargetSchoolID = :TargetID");
          break;
        }
      case DEST_GROUP: {
          $PDOstt = $Connection->prepare("select Permissions from schedulepost.group_permissions where UserID = :UserID AND TargetGroupID = :TargetID");
          break;
        }
      default: {
          throw new InvalidArgumentException("The targettype is not correct.");
          return null;
          break;
        }
    }
    $PDOstt->bindValue(":UserID", $this->UserID);
    $PDOstt->bindValue(":TargetID", $TargetID);

    $PDOstt->execute();
    $Data = $PDOstt->fetch();
    $Fetcher = new Fetcher();
    $Permission = null;
    if ($Data === false) {
      // Nothing, use default.
    } else {
      $Permission = json_decode($Data["Permissions"], true);
    }

    if ($Permission === null) {
      return false;
    } else {
      if (Permissions::IsExist($Action)) {
        switch ($TargetType) {
          case DEST_GROUP: {
              $TargetSchoolID = $Fetcher->LookupSchoolID($TargetID);
              if ($TargetSchoolID != null) {
                switch ($this->IsPermitted($Action, DEST_SCHOOL, $TargetSchoolID)) {
                  case true: {
                      // The school says true so leave it to group.
                      break;
                    }
                  case false: {
                      // The school says no, so the group is denied.
                      return false;
                      break;
                    }
                  case null: { // Permission inherited or not specified

                      // The school says nothing, so leave it to group.
                      break;
                    }
                    // Passed.
                    // NEGATIVE CONSENSUS: If group says no, it's no. just like that
                }
              }
              if (array_key_exists($Action, $Permission)) {
                if ($Permission[$Action] === true) {
                  //The group says yes, so yes.
                  return true;
                } else if ($Permission[$Action] === false) {
                  //The group says no, so no.
                  return false;
                }
              }
              // The permission isn't specified, go for default. Check roles.
              $IdentityMatches = false;
              foreach (Permissions::Dic[$Action]["DefaultAllowRoles"] as $AllowedIdentity) {
                if ($Permission["Identity"] === $AllowedIdentity) {
                  $IdentityMatches = true;
                }
              }

              if ($IdentityMatches) {
                return true;
              } else {
                // Really nothing.
                die("default!");
                return Permissions::Dic[$Action]["Default"];
              }

              break;
            }
          case DEST_SCHOOL: {
              if (array_key_exists($Action, $Permission)) {
                if ($Permission[$Action] === true) {
                  return true;
                } else if ($Permission[$Action] === false) {
                  return false;
                }
                // If inherited, go for default permission.
              }

              // The permission isn't specified, go for default.
              $IdentityMatches = false;
              foreach (Permissions::Dic[$Action]["DefaultAllowRoles"] as $AllowedIdentity) {
                if ($Permission["Identity"] === $AllowedIdentity) {
                  $IdentityMatches = true;
                }
              }

              if ($IdentityMatches) {
                return true;
              } else {
                return null;
              }
              break;
            }

          default: {
              throw new UnexpectedValueException("That target type is out of range.");
              return null;
            }
        }
        // Nothing returned, deny.
        error_log("The permission $Action for $TargetID (Type: $TargetType) is invalid.");
        return false;
      } else {
        throw new UnexpectedValueException("The permission does not exist.");
        return false;
      }

      //TODO: Prioritize elevation.
      /*
    if (Permissions::IsExist($Action)) {
      // Completely nothing.
      if ($Permission === null) {
        return false;
      } else {
        // If not specified in group, check elevation
        if ($TargetType == DEST_GROUP) {
          if (Permissions::Dic[$Action]["Elevation"]) {
          if ($this->GetSchoolID() !== null) {
            // If school says - 
            switch ($this->IsPermitted($Action, DEST_SCHOOL, $this->GetSchoolID())) {
              case true:
                break;
            }
          } else {
            return null; // Leave that to group.   
          }
        }
        } else {

        }
        if (array_key_exists($Action, $Permission)) {
          if ($Permission[$Action] === true) {
            return true;
          } else if ($Permission[$Action] === false) {
            return false;
          }
        } else {
          // If permission is not specified, search IDENTITY default from Dictionary.
          // If the identity is allowed in default, approve action.
          $IdentityMatches = false;
          foreach (Permissions::Dic[$Action]["Default"] as $AllowedIdentity) {
            if ($Permission["Identity"] === $AllowedIdentity) {
              $IdentityMatches = true;
            }
          }
          if ($IdentityMatches) {
            return true;
          } else {
            
            }
            // No elevation
            return false;
          }
        */
    }
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
    if ($this->UserID === null || $LongToken === null) {
      throw new UnexpectedValueException("Provide UserID or Longtoken.");
    }

    $Connection = DBConnection::Connect();
    $PDOstt = $Connection->prepare("select LongTokenGenAt from schedulepost.accounts where UserID = :UserID AND LongToken = :LongToken");
    if ($PDOstt === false) {
      // TODO: Is this error handling correct?
      error_log("An error occurred in GetSessionTokenFromLongToken: " . print_r($Connection->errorinfo(), true));
      throw new ConnectionException("Could not connect to the database.", "Database: SchedulePost");
    }
    $PDOstt->bindValue(":UserID", $this->UserID);
    $PDOstt->bindValue(":LongToken", $LongToken);
    $PDOstt->execute();

    $Data = $PDOstt->fetch(PDO::FETCH_ASSOC);
    if ($Data === false) {
      // TODO: Is this error handling correct?
      throw new InvalidCredentialsException("The user ID or long token provided is invalid.");
      return false;
    }

    if (array_key_exists("LongTokenGenAt", $Data) && $Data["LongTokenGenAt"] !== NULL) {
      $CurrentTime = new DateTime("now", new DateTimeZone($GLOBALS["DefaultTimeZone"]));;
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

  function GetTimetable(string $GroupID, DateTime $Date, int $Revision = null) {
    $Data = array();
    //TODO: Need to verify things here, but ignoring for now
    $Base = $this->GetDefaultTimetable($GroupID, ((int)$Date->format("w")));
    $Diff = $this->GetTimetableDiff($GroupID, $Date, $Revision);
    $Data = array_merge(
      array(
        "Date" => $Date->format("d-m-Y"),
        "Revision" => $Diff["Revision"],
        "GroupID" => $GroupID
      ),
      $Base,
      $Diff["Body"]
    );

    return $Data;
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
        break;
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

    $DefaultTimeTable = json_decode($Result[0], true, 512, JSON_FORCE_OBJECT);
    $DayStr = DayEnum::EnumToStr($Day_Of_The_Date);

    if ($DefaultTimeTable === false || $DefaultTimeTable === null) {
      throw new UnexpectedValueException("The JSON of default timetable is malformed.");
    } else if (!array_key_exists($DayStr, $DefaultTimeTable)) {
      throw new OutOfBoundsException("The default timetable does not contain the index: \"" . $DayStr . "\"");
    }

    return $DefaultTimeTable[$DayStr];
  }

  /**
   * Get timetale DIFF string.
   *
   * @param string Target group.
   * @param DateTime Target date.
   * @param integer Target revision. If unset(null), get the latest version.
   * @return array Keys: "Revision" and "Body"
   */
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
    $Diff = array(
      "Revision" => $Result["0"]["Revision"],
      "Body" => json_decode($Result["0"]["Body"], true, 512, JSON_FORCE_OBJECT)
    );

    if ($Diff === false) {
      throw new InvalidArgumentException("The JSON of the specified timetable is malformed.");
    }

    return $Diff;
  }

  function LookupSchoolID(string $GroupID) {
    $PDO = DBConnection::Connect();
    $PDOstt = $PDO->prepare("select BelongSchoolID from schedulepost.group_profile where GroupID = :GroupID");
    $PDOstt->bindValue(":GroupID", $GroupID);
    $PDOstt->execute();
    $Result = $PDOstt->fetch();

    if ($Result === false) {
      throw new ConnectionException("Could not connect to the database.");
      return false;
    } else if ($Result === null) {
      return null;
    }

    return $Result["BelongSchoolID"];
  }
}

class Messages {
  public const ErrorCodes = array(
    "ERROR_UNKNOWN" => "The API did not respond properly to your request.",
    "INPUT_MALFORMED" => "The provided input or argument or both is malformed.",
    "UNEXPECTED_ARGUMENT" => "The input data contains unexpected value.",
    "INTERNAL_EXCEPTION" => "There was an internal exception occurred. Please try again.",
    "INVALID_CREDENTIALS" => "The provided credential is invalid.",
    "INSUFFCIENT_PERMISSION" => "You do not have sufficient permission to do that."
  );

  static function GenerateErrorJSON(string $Code, $Message = null) {
    if ($Message === null) {
      $Message = Messages::GetErrorMessage($Code);
    }
    return array(
      "Result" => false,
      "ReasonCode" => $Code,
      "ReasonText" => $Message
    );
  }

  static function GetErrorMessage(string $Code) {
    if (array_key_exists($Code, Messages::ErrorCodes)) {
      $Message = Messages::ErrorCodes[$Code];
    } else {
      $Message = "Error information not provided.";
    }
  }
}

$Resp = Messages::GenerateErrorJSON("ERROR_UNKNOWN", "The API could not respond properly to your request.");

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
  //var_dump($Recv);

  switch ($Recv["Action"]) {
    case "SIGN_IN": {
        $UserID = null;
        $Resp = Messages::GenerateErrorJSON("UNEXPECTED_ARGUMENT", "The information provided to signin is insuffcient.");

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
                  //TODO: add "valid until"
                );
                break;

              case false:
                break;
            }
          } catch (ConnectionException $e) {
            $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal exception whlist trying to sign in:  " . $e->getMessage());
            error_log("SIGNIN: An error occurred whlist trying to sign in using passphrase. " . $e->getMessage() . " Stack trace:" . $e->getTraceAsString());
          } catch (InvalidCredentialsException $e) {
            $Resp = Messages::GenerateErrorJSON("INVALID_CREDENTIALS", "The passphrase provided is invalid. " . $e->getMessage());
          } catch (InvalidArgumentException $e) {
            $Resp = Messages::GenerateErrorJSON("INVALID_CREDENTIALS", "The Email address provided is invalid. " . $e->getMessage());
          } catch (Exception $e) {
            $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal exception whlist trying to sign in. " . $e->getMessage());
          }
        } else {
          if (array_key_exists("UserID", $Recv["Auth"]) && array_key_exists("LongToken", $Recv["Auth"])) {
            try {
              $User = new UserAuth($Recv["Auth"]["UserID"]);
              switch ($User->SignInFromUserIDAndLongToken($Recv["Auth"]["UserID"], $Recv["Auth"]["LongToken"])) {
                case true:
                  $Resp = array(
                    "Result" => true,
                    "UserID" => $User->GetUserID(), // TODO: Is it necessary?
                    "SessionToken" => $User->GetSessionToken(),
                    "LongToken" => $User->GetLongToken() // TODO: Is it necessary?
                  );
                  break;

                case false:
                  break;
              }
            } catch (ConnectionException $e) {
              $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal exception whlist trying to sign in:  " . $e->getMessage());
              error_log("SIGNIN: An error occurred whlist trying to sign in using long token. " . $e->getMessage() . " Stack trace:" . $e->getTraceAsString());
            } catch (InvalidCredentialsException $e) {
              $Resp = Messages::GenerateErrorJSON("INVALID_CREDENTIALS", "The long token provided is invalid. " . $e->getMessage());
            } catch (Exception $e) {
              $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal exception whlist trying to sign in. " . $e->getMessage());
            }
          }
        }
        break;
      }
    // Could be a problem: ACTIVITY CHECK may not be necessary as it only checks token validity.
      case "ACTIVITY_CHECK": {
        $User = new UserAuth($Recv["Auth"]["UserID"], $Recv["Auth"]["SessionToken"]);
        if (!$User->SignIn()) {
          $Error = $User->GetError();
          $Resp = array(
            "Result" => false,
            "ReasonCode" => $Error["Code"],
            "ReasonText" => "Could not sign in with the provided credentials. " . ", " . $Error["Code"]
          );
          break;
        } else {
          $Resp = array(
            "Result" => true
          );
        }
        break;
    }

    case "GET_SCHEDULE": {
        try {
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

          if (array_key_exists("GroupID", $Recv)) {
          } else {
            $TargetGroupID = $User->GetGroupID();
          }

          if ($User->IsPermitted("Timetable.View", DEST_GROUP, $TargetGroupID)) {
          } else {
            throw new InsuffcientPermissionException("You cannot view the timetable of that group.");
          }

          $Fetcher->GetDefaultTimetable(
            $User->GetGroupID(),
            // Note here: Because PHP Datetime::format() format character "w" follows ISO-8601, DayEnum corresponds to it.
            (int)$Date->format("w")
          );
          $Result = json_encode($Fetcher->GetTimetable($User->GetGroupID(), $Date), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT);

          if ($Result != false) {
            $Resp = array(
              "Result" => true,
              "ReasonCode" => "",
              "Body" => $Result
            );
          }
        } catch (InsuffcientPermissionException $e) {
          $Resp = Messages::GenerateErrorJSON("INSUFFCIENT_PERMISSION", $e->getMessage());
        }
        break;
      }
    case "GET_TIMETABLE_RAW": {
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

        $Date = null;
        if (array_key_exists("Date", $Recv)) {
          $Date = new DateTime($Recv["Date"]);
        }
        $Fetcher = new Fetcher($User);

        if (array_key_exists("GroupID", $Recv)) {
        } else {
          $TargetGroupID = $User->GetGroupID();
        }

        switch ($Recv["Type"]) {
          case "Base": {
              if ($User->IsPermitted("Timetable.View", DEST_GROUP, $TargetGroupID)) {
                // NOT DAY OF THE WEEK, REALLY?
                $IndexOfTheWeek = null;
                if (array_key_exists("Date",$Recv)) {
                  $IndexOfTheWeek = (int)$Date->format("w");
                } else if (array_key_exists("DayOfTheWeek", $Recv)) {
                  $IndexOfTheWeek = DayEnum::StrToEnum($Recv["DayOfTheDate"]);
                } else {
                  $Resp = Messages::GenerateErrorJSON("UNEXPECTED_ARGUMENT", "Specify at least one of DATE or DayOfTheWeek.");
                  break;
                }
                $Timetable = $Fetcher->GetDefaultTimetable(
                  $TargetGroupID,
                  // Note here: Because PHP Datetime::format() format character "w" follows ISO-8601, DayEnum corresponds to it.
                  $IndexOfTheWeek
                );
                $Resp = array(
                  "Result" => true,
                  "Body" => $Timetable
                );
              } else {
                $Resp = Messages::GenerateErrorJSON("INSUFFCIENT_PERMISSION");
                break;
              }
              break;
            }
          case "Diff": {
              if ($User->IsPermitted("Timetable.View", DEST_GROUP, $TargetGroupID)) {

                $Target_Revision = null;
                if (array_key_exists("Revision", $Recv)) {
                  $Target_Revision = $Recv["Revision"];
                }
                $Diff = $Fetcher->GetTimetableDiff($TargetGroupID, $Date, $Target_Revision);

                $Resp = array(
                  "Result" => true,
                  "Body" => $Diff["Body"],
                  "Revision" => $Diff["Revision"]
                );

                break;
              }
            }
          default: {
              break;
            }
        }


        $Result = json_encode($Fetcher->GetTimetable($User->GetGroupID(), $Date), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT);

        if ($Result != false) {
          $Resp = array(
            "Result" => true,
            "Body" => $Result
          );
        }
        break;
      }
    case "GET_SCHOOL_CONFIG": {
        // Need to check permissions here.
        $User = new UserAuth($Recv["Auth"]["UserID"], $Recv["Auth"]["SessionToken"]);
        if (!$User->SignIn()) {
          $Error = $User->GetError();
          $Resp = Messages::GenerateErrorJSON($User->GetError()["Code"], "Could not sign in with the provided credentials.");
          break;
        }

        // Fetch school profile(raw)
        $Connection = DBConnection::Connect();
        $PDOstt = $Connection->prepare("select DisplayName, Config from school_profile where SchoolID = :SchoolID");
        $PDOstt->bindValue(":SchoolID", $User->GetSchoolID());
        $PDOstt->execute();
        $Data = $PDOstt->fetch();
        if ($Data === false || $Data === null) {
          $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal error while trying to fetch school profile.");
          break;
        }

        $SchoolConfig = json_decode($Data["Config"], true);
        if ($SchoolConfig === null) {
          $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION");
          error_log(sprintf("JSON: The school ID %s has a malformed CONFIG JSON! Please fix it manually.", $User->GetSchoolID()));
          break;
        }

        $Resp = array(
          "Result" => true,
          "Item" => null,
          "Content" => null
        );

        switch ($Recv["Item"]) {
          case "Subjects": {
              $Resp["Item"] = "Subjects";
              if (array_key_exists("Subjects", $SchoolConfig)) {

                $Resp["Content"] = $SchoolConfig["Subjects"];
              } else {
                $Resp["Content"] = null;
              }
              break;
            }

          default: {
              $Resp = array(
                "Result" => false,
                "ReasonCode" => "UNEXPECTED_ARGUMENT",
                "ReasonText" => "The item type requested is not defined in the system. There might be a typo in your code!"
              );
              break;
            }
        }
        break;
      }

    case "GET_USER_PROFILE": {

        $User = new UserAuth($Recv["Auth"]["UserID"], $Recv["Auth"]["SessionToken"]);
        if (!$User->SignIn()) {
          $Error = $User->GetError();
          $Resp = Messages::GenerateErrorJSON($User->GetError()["Code"], "Could not sign in with the provided credentials.");
          break;
        }

        // Fetch user profile(raw)
        $Connection = DBConnection::Connect();
        $PDOstt = $Connection->prepare("select BelongGroupID, BelongSchoolID, DisplayName from user_profile where BelongUserID = :UserID");
        $PDOstt->bindValue(":UserID", $User->GetUserID());
        $PDOstt->execute();
        $Data = $PDOstt->fetch();
        if ($Data === false || $Data === null) {
          $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal error while trying to fetch user profile.");
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
          $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal error while trying to fetch user profile.");
          break;
        }
        $SchoolDisplayName = $Data["DisplayName"];

        // Fetch group name
        $PDOstt = $Connection->prepare("select DisplayName from group_profile where GroupID = :GroupID");
        $PDOstt->bindValue(":GroupID", $GroupID);
        $PDOstt->execute();
        $Data = $PDOstt->fetch();
        if ($Data === false || $Data === null) {
          $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal error while trying to fetch user profile.");
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
        break;
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

echo json_encode($Resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT);

exit;
