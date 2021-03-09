<?php
// Requirement: PHP 8.0.0 or later
// TODO: PHP envilonment requirement
// WIP.

error_reporting(0);

$GLOBALS["DB_URL"] = getenv("SP_DB_URL");
$GLOBALS["DB_Username"] = getenv("SP_DB_USER");
$GLOBALS["DB_PassPhrase"] = getenv("SP_DB_PASSPHRASE");
$GLOBALS["DB_NAME"] = getenv("SP_DB_NAME");
//type false exactly!!
$GLOBALS["PUBLIC_MODE"] = (getenv("SP_PUBLIC_MODE") === "false") ? false : true;

if (($GLOBALS["DefaultTimeZone"] = getenv("SP_TIMEZONE")) === null) {
  $GLOBALS["DefaultTimeZone"] = "UTC";
}
date_default_timezone_set($GLOBALS["DefaultTimeZone"]);

$GLOBALS["SessionTokenExpiry"] = getenv("SP_SESSIONTOKENEXPIRY") ?? "30 minutes";
$GLOBALS["LongTokenExpiry"] = getenv("SP_LONGTOKENEXPIRY") ?? "14 days";

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
    ),
    "Config.Subjects.View" => array(
      "Default" => true,
      "DefaultAllowRoles" => array("Admin", "Teacher", "Students"),
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
            $GLOBALS["DB_NAME"]
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
      error_log("Could not connect to the database with provided credentials: " . $e->getMessage());

      throw new ConnectionException("Could not connect to the database: " . " Could not connect to the database using provided credentials.");
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
  private $SessionTokenExpiry;
  private $LongTokenExpiry;

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

  function GetSessionTokenExpiry(bool $Force_Update = false) {
    if ($Force_Update) {
      $this->SessionTokenExpiry = null;
    }
    if ($this->SessionTokenExpiry !== null) {
      return $this->SessionTokenExpiry;
    } else {
      $Connection = DBConnection::Connect();
      $PDOstt = $Connection->prepare("select LastActivityAt from accounts where UserID = :UserID");
      if ($PDOstt === false) {
        // TODO: Is this error handling correct?
        error_log("An error occurred in GetSessionTokenExpiry: Could not prepare SQL. Info:" . implode(",", $Connection->errorinfo()));
        throw new ConnectionException("Could not connect to the database.", "Database: SchedulePost");
        return false;
      }
      $PDOstt->bindValue(":UserID", $this->UserID);
      $PDOstt->execute();

      $Data = $PDOstt->fetch(PDO::FETCH_ASSOC);
      if ($Data === false) {
        // TODO: Is this error handling correct?
        throw new InvalidCredentialsException("An error occurred in GetSessionTokenExpiry: Could not fetch LastActivityAt column. The specified user ID $this->UserID may be invalid!");
        return false;
      }

      if (array_key_exists("LastActivityAt", $Data) && $Data["LastActivityAt"] !== NULL) {
        $Expiry = new DateTime($Data["LastActivityAt"]);
        $Expiry->add(DateInterval::createFromDateString($GLOBALS["SessionTokenExpiry"]));
        $this->SessionTokenExpiry = $Expiry;
        return $Expiry;
      }
    }
  }

  function GetLongTokenExpiry(bool $Force_Update = false) {
    if ($Force_Update) {
      $this->LongTokenExpiry = null;
    }
    if ($this->LongTokenExpiry !== null) {
      return $this->LongTokenExpiry;
    } else {
      $Connection = DBConnection::Connect();
      $PDOstt = $Connection->prepare("select LastSigninAt from accounts where UserID = :UserID");
      if ($PDOstt === false) {
        // TODO: Is this error handling correct?
        error_log("An error occurred in GetSessionTokenExpiry: Could not prepare SQL. Info:" . implode(",", $Connection->errorinfo()));
        throw new ConnectionException("Could not connect to the database.", "Database: SchedulePost");
        return false;
      }
      $PDOstt->bindValue(":UserID", $this->UserID);
      $PDOstt->execute();

      $Data = $PDOstt->fetch(PDO::FETCH_ASSOC);
      if ($Data === false) {
        // TODO: Is this error handling correct?
        throw new InvalidCredentialsException("An error occurred in GetLongTokenExpiry: Could not fetch LastSigninAt column. The specified user ID $this->UserID may be invalid!");
        return false;
      }

      if (array_key_exists("LastSigninAt", $Data) && $Data["LastSigninAt"] !== NULL) {
        $Expiry = new DateTime($Data["LastSigninAt"]);
        $Expiry->add(DateInterval::createFromDateString($GLOBALS["LongTokenExpiry"]));
        $this->LongTokenExpiry = $Expiry;

        return $Expiry;
      }
    }
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
    $Data = null;
    try {
      $PDOstt = $Connection->prepare("select SessionToken,LastActivityAt from accounts where UserID = :UserID");
      $PDOstt->bindValue(":UserID", $this->UserID);
      $PDOstt->execute();
      $Data = $PDOstt->fetch();
      //var_dump($Data);
      if ($this->SessionToken != $Data["SessionToken"]) {
        $this->Error = ACCOUNT_SESSION_TOKEN_INVALID;
        return false;
      }
    } catch (PDOException $e) {
      error_log("SignIn(): An error occurred whilst connecting to the database: $e->getMessage()");
      throw new ConnectionException("Could not communicate with the database: " . $e->getMessage(), "DATABASE");
      return false;
    }
    if ($Data === null || $Data === false) {
      if ($Data === null) {
        error_log("Error in SignIn(): While signin, $this->UserID was specified as UserID but it did not exist!");
      }
      return false;
    } else {
      // Is expired?
      $CurrentTime = new DateTime("now", new DateTimeZone($GLOBALS["DefaultTimeZone"]));;
      $Expiry = new DateTime($Data["LastActivityAt"]);
      $Expiry->add(DateInterval::createFromDateString($GLOBALS["SessionTokenExpiry"]));
      if ($Expiry > $CurrentTime) {
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
  function IsPermitted(string $Action, int $TargetType, string $TargetID, bool $Elevated = false) {
    // TODO: TO get rid of Permissions table, switch target table to lookup.
    if ($TargetID === null) {
      throw new InvalidArgumentException("The targetID is not correct.");
      return null;
    }
    $Connection = DBConnection::Connect();
    switch ($TargetType) {
      case DEST_SCHOOL: {
          $PDOstt = $Connection->prepare("select Permissions from school_permissions where UserID = :UserID AND TargetSchoolID = :TargetID");
          break;
        }
      case DEST_GROUP: {
          $PDOstt = $Connection->prepare("select Permissions from group_permissions where UserID = :UserID AND TargetGroupID = :TargetID");
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
                switch ($this->IsPermitted($Action, DEST_SCHOOL, $TargetSchoolID, true)) {
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
                if ($Elevated) {
                  return null;
                } else {
                  return Permissions::Dic[$Action]["Default"];
                }
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
      error_log("Error in GetLongTokenFromPassPhrase(): function was called without UserID specified (Check User instance!)");
      throw new UnexpectedValueException("The UserID is not set. This is possibly a bug!");
      return false;
    }

    try {
      $PDOstt = $Connection->prepare("select PassHash from accounts where UserID = :UserID");
      if ($PDOstt === false) {
        error_log("Error whilst trying to fetch from table 'accounts'. UserID:'$this->UserID'");
        throw new ConnectionException("Could not connect to the database.", "Database: SchedulePost");
      }
      $PDOstt->bindValue(":UserID", $this->UserID);
      $PDOstt->execute();
      $Data = $PDOstt->fetch(PDO::FETCH_ASSOC);
      $VerifyHash = $Data["PassHash"];
    } catch (Exception $e) {
      error_log("Error whilst trying to fetch from table 'accounts'. UserID:'$this->UserID', Info:" . implode(",", $PDOstt->errorInfo()));
      throw new ConnectionException("Could not process connection properly.", "Internal function");
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
        error_log("Error whilst trying to sign-in. UserID:'$this->UserID', Exception: " . $e->getCode() . " : " . $e->getMessage());
        throw new ConnectionException("Could not process connection properly.", "Internal function");
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
    $PDOstt = $Connection->prepare("select LongTokenGenAt from accounts where UserID = :UserID AND LongToken = :LongToken");
    if ($PDOstt === false) {
      // TODO: Is this error handling correct?
      error_log("An error occurred in GetSessionTokenFromLongToken: Could not prepare SQL. Info:" . implode(",", $Connection->errorinfo()));
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
    // TODO: May have to fix this kind of error handling.
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
        error_log("An error occurred in UpdateSessionToken(): The table `accounts` was not updated. TargetUserID: $this->UserID" . ", " . implode(",", $Updater->errorinfo()));

        throw new ConnectionException("Database refused to update.", "Database: SchedulePost");
      }

      $Expiry = $LoginDateTime->add(DateInterval::createFromDateString($GLOBALS["SessionTokenExpiry"]));
      $this->Token = $Token;
      $this->SessionTokenExpiry = $Expiry;
      return true;
    } catch (Exception $e) {
      error_log("An error occurred in UpdateSessionToken(): " . $e->getMessage() . " TargetUserID: $this->UserID" . ", " . implode(",", $Updater->errorinfo()));
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
    $PDOstt = $Connection->prepare("select UserID from accounts where Mail = :Mail");
    if ($PDOstt === false) {
      error_log("Could not prepare SQL for `accounts`: this is possibly a bug!");
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
    if ($Base === null) {
      $Base = array("TimeTable" => array());
    } else if ($Base === false) {
      error_log("GetTimeTable(): failed since GetDefaultTimetable returned an error.");
      return false;
    }
    $Diff = $this->GetTimetableDiff($GroupID, $Date, $Revision);
    if ($Diff === false) {
      error_log("GetTimeTable(): failed since GetTimetableDiff returned an error.");
      return false;
    }
    if ($Diff["Revision"] !== -1) {
      if (isset($Diff["Override"]) && $Diff["Override"]) {
        $Data = array_merge(
          array(
            "Date" => $Date->format("Y-m-d"),
            "Revision" => $Diff["Revision"],
            "GroupID" => $GroupID
          ),
          $Diff["Body"]
        );
      } else {
        $Data = array_merge(
          array(
            "Date" => $Date->format("Y-m-d"),
            "Revision" => $Diff["Revision"],
            "GroupID" => $GroupID
          ),
          $Base,
          $Diff["Body"]
        );
      }
    } else {
      $Data = array_merge(
        array(
          "Date" => $Date->format("Y-m-d"),
          "Revision" => $Diff["Revision"],
          "GroupID" => $GroupID
        ),
        $Base
      );
    }

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
      error_log("An error occurred in GetDefaultTimetable(): trying to fetch column Body in `default_timetable`. TargetGroupID: $GroupID" . ", " . implode(",", $PDOstt->errorinfo()));

      throw new ConnectionException("Could not connect to the database properly.");
      return false;
    }

    $DefaultTimeTable = json_decode($Result[0], true, 512, JSON_FORCE_OBJECT);
    $DayStr = DayEnum::EnumToStr($Day_Of_The_Date);

    if ($DefaultTimeTable === false || $DefaultTimeTable === null) {
      error_log("An error occurred in GetDefaultTimetable(): The JSON of default timetable of GroupID $GroupID is invalid or not defined!");
      throw new UnexpectedValueException("The JSON of default timetable is malformed.");
    } else if (!array_key_exists($DayStr, $DefaultTimeTable)) {
      error_log("An error occurred in GetDefaultTimetable(): The JSON of default timetable of GroupID $GroupID does not have index $DayStr !");

      // throw new OutOfBoundsException("The default timetable does not contain the index: \"" . $DayStr . "\"");
      return null;
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

    if ($Result === false) {
      error_log("An error occurred in GetTimetableDiff(): Could not fetch data from table `timetable`. TargetGroupID:$GroupID, Date:" . $Date->format("Y-m-d") . ", Revision: $Revision, Info: " . implode(',', $PDOstt));
      throw new ConnectionException("Could not connect to the database properly.");
      return false;
    }

    if (empty($Result)) {
      $Diff = array(
        "Revision" => -1,
        "Body" => null
      );
    } else {
      $FoundFlag = false;
      $TargetIndex = 0;
      $MaxIndex = null;
      $MaxRev = -1;
      // Though it's inefficient, all results same
      for ($TargetIndex = 0; $TargetIndex < count($Result); $TargetIndex++) {
        if ($Result[$TargetIndex]["Revision"] === $Revision) {
          $MaxIndex = $TargetIndex;
          $FoundFlag = true;
          break;
        }
        if ($Revision === null) {
          if ($Result[$TargetIndex]["Revision"] > $MaxRev) {
            $MaxRev = intval($Result[$TargetIndex]["Revision"]);
            $MaxIndex = $TargetIndex;
            $FoundFlag = true;
          }
        }
      }
      if (!$FoundFlag) {
        $Diff = array(
          "Revision" => $Revision ?? -1,
          "Body" => null
        );
      } else {
        $Diff = array(
          "Revision" => $MaxRev,
          "Body" => json_decode($Result[$MaxIndex]["Body"], true, 512, JSON_FORCE_OBJECT)
        );
      }
    }

    if ($Diff === false) {
      error_log("An error occurred in GetTimetableDiff(): The JSON data for timetable diff of GroupID $GroupID is invalid! Date:" . $Date->format("Y-m-d") . ", Revision: $Revision");

      throw new InvalidArgumentException("The JSON of the specified timetable is malformed.");
      return false;
    }

    return $Diff;
  }

  function LookupSchoolID(string $GroupID) {
    $PDO = DBConnection::Connect();
    $PDOstt = $PDO->prepare("select BelongSchoolID from group_profile where GroupID = :GroupID");
    $PDOstt->bindValue(":GroupID", $GroupID);
    $PDOstt->execute();
    $Result = $PDOstt->fetch();

    if ($Result === false) {
      error_log("An error occurred in LookupSchoolID(): Error whilst trying to fetch from `group_profile`. TargetGroupID:$GroupID,  Info:" . implode(",", $PDOstt));

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
    "INSUFFCIENT_PERMISSION" => "You do not have sufficient permission to do that.",
    "ACCOUNT_SESSION_TOKEN_EXPIRED" => "",
    "ACCOUNT_SESSION_TOKEN_INVALID" => "",
    "ACCOUNT_LONG_TOKEN_INVALID" => "",
    "ACCOUNT_LONG_TOKEN_EXPIRED" => "",
    "ACCOUNT_CREDENTIALS_INVALID" => "",
    "ILLEGAL_CALL" => "Some of necessary arguments are missing or malformed.",
    "SIGNIN_REQUIRED" => "You need to be signed in to do that."
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

function json_api_encode($Obj) {
  // default JSON encode
  return json_encode($Obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

// BASICではよくある、 while(true) -> break. try~catch(exception e)~finally ができるやり方。

while (true) {
  $Recv = json_decode(file_get_contents("php://input"), true);
  $User = null;
  error_log("Got ".print_r($Recv));

  if ($Recv === null || $Recv === false) {
    $Resp = array(
      "ReasonCode" => "INPUT_MALFORMED",
      "ReasonText" => "The provided JSON is malformed."
    );
    break;
  }

  $uid = null;
  $ses = null;
  if ($Recv["Action"] != "SIGN_IN") {
    if (isset($_COOKIE["UserID"]) && isset($_COOKIE["Session"])) {
      $uid = $_COOKIE["UserID"];
      $ses = $_COOKIE["Session"];
      $User = new UserAuth($uid, $ses);
    } else if (array_key_exists("Auth", $Recv) && array_key_exists("SessionToken", $Recv["Auth"]) && $Recv["Auth"]["SessionToken"] != "" && array_key_exists("UserID", $Recv["Auth"]) && $Recv["Auth"]["UserID"] != "") {
      $uid = $Recv["Auth"]["UserID"];
      $ses = $Recv["Auth"]["SessionToken"];
      $User = new UserAuth($uid, $ses);
    } else {
      $User = null;
    }

    if ($User !== null && !$User->SignIn()) {
      $Error = $User->GetError();
      $Resp = array(
        "Result" => false,
        "ReasonCode" => $Error["Code"],
        "ReasonText" => "Could not sign in with the provided credentials. " . ", " . $Error["Code"]
      );
      break;
    }
  }

  $Resp = Messages::GenerateErrorJSON("ERROR_UNKNOWN", "The API could not respond properly to your request. " . serialize($Recv));
  /* Please note that SchedulePost API does not support any GET method. */

  //Authenticate here
  //Probs insert this part on request header
  //var_dump($Recv);

  switch ($Recv["Action"]) {

    case "SIGN_IN": {
        $UserID = null;
        $Resp = Messages::GenerateErrorJSON("UNEXPECTED_ARGUMENT", "The information provided to signin is insuffcient.");

        if (array_key_exists("Auth", $Recv)) {
          $Mail = array_key_exists("Mail", $Recv["Auth"]) ? $Recv["Auth"]["Mail"] : null;
          $PassPhrase = array_key_exists("PassPhrase", $Recv["Auth"]) ? $Recv["Auth"]["PassPhrase"] : null;

          $UserID = array_key_exists("UserID", $Recv["Auth"]) ? $Recv["Auth"]["UserID"] : (array_key_exists("UserID", $_COOKIE) ? $_COOKIE["UserID"] : null);
          $LongToken = array_key_exists("LongToken", $Recv["Auth"]) ? $Recv["Auth"]["LongToken"] : (array_key_exists("LongToken", $_COOKIE) ? $_COOKIE["LongToken"] : null);
        } else {
          $Mail = null;
          $PassPhrase = null;
          $UserID =
            array_key_exists("UserID", $_COOKIE) ? $_COOKIE["UserID"] : null;
          $LongToken = array_key_exists("LongToken", $_COOKIE) ? $_COOKIE["LongToken"] : null;
        }

        if ($Mail != null && $PassPhrase != null) {
          try {
            $User = new UserAuth();
            switch ($User->SignInFromMailAndPassphrase($Mail, $PassPhrase)) {
              case true:
                $Resp = array(
                  "Result" => true,
                  "UserID" => $User->GetUserID(),
                  "SessionToken" => $User->GetSessionToken(),
                  "LongToken" => $User->GetLongToken(),
                  // Literally based on W3C, to supply this to JS
                  "SessionTokenExpiry" => $User->GetSessionTokenExpiry()->format("Y-m-d\TH:i:sP"),
                  "LongTokenExpiry" => $User->GetLongTokenExpiry()->format("Y-m-d\TH:i:sP")
                );
                break;
                setcookie("UserID", $User->GetUserID(), array(
                  "expires" => time() + 60 * 60 * 24 * 365,
                  "path" => "/",
                  "secure" => $GLOBALS["PUBLIC_MODE"],
                  "httponly" => false,
                  "samesite" => "Strict"
                ));
                setcookie("Session", $User->GetSessionToken(), array(
                  "expires" => ($User->GetSessionTokenExpiry()->getTimestamp()),
                  "path" => "/",
                  "secure" => $GLOBALS["PUBLIC_MODE"],
                  "httponly" => true,
                  "samesite" => "Strict"
                ));
                setcookie("LongToken", $User->GetLongToken(), array(
                  "expires" => ($User->GetLongTokenExpiry()->getTimestamp()),
                  "path" => "/",
                  "secure" => $GLOBALS["PUBLIC_MODE"],
                  "httponly" => true,
                  "samesite" => "Strict"
                ));
              case false:
                break;
            }
          } catch (ConnectionException $e) {
            $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal exception whilst trying to sign in:  " . $e->getMessage());
            error_log("SIGNIN: An error occurred whilst trying to sign in using passphrase. " . $e->getMessage() . " Stack trace:" . $e->getTraceAsString());
          } catch (InvalidCredentialsException $e) {
            $Resp = Messages::GenerateErrorJSON("INVALID_CREDENTIALS", "The passphrase provided is invalid. " . $e->getMessage());
          } catch (InvalidArgumentException $e) {
            $Resp = Messages::GenerateErrorJSON("INVALID_CREDENTIALS", "The Email address provided is invalid. " . $e->getMessage());
          } catch (Exception $e) {
            $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal exception whilst trying to sign in. " . $e->getMessage());
          }
        } else {
          if ($UserID != null && $LongToken != null) {
            try {
              $User = new UserAuth($UserID);
              switch ($User->SignInFromUserIDAndLongToken($UserID, $LongToken)) {
                case true:
                  $Resp = array(
                    "Result" => true,
                    "UserID" => $User->GetUserID(), // TODO: Is it necessary?
                    "SessionToken" => $User->GetSessionToken(),
                    // Literally based on W3C, to supply this to JS
                    "SessionTokenExpiry" => $User->GetSessionTokenExpiry()->format("Y-m-d\TH:i:sP"),
                    "LongTokenExpiry" => $User->GetLongTokenExpiry()->format("Y-m-d\TH:i:sP")
                  );

                  setcookie("UserID", $User->GetUserID(), array(
                    "expires" => time() + 60 * 60 * 24 * 365,
                    "path" => "/",
                    "secure" => $GLOBALS["PUBLIC_MODE"],
                    "httponly" => false,
                    "samesite" => "Strict"
                  ));
                  setcookie("Session", $User->GetSessionToken(), array(
                    "expires" => ($User->GetSessionTokenExpiry()->getTimestamp()),
                    "path" => "/",
                    "secure" => $GLOBALS["PUBLIC_MODE"],
                    "httponly" => true,
                    "samesite" => "Strict"
                  ));
                  break;

                case false:
                  break;
              }
            } catch (ConnectionException $e) {
              $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal exception whilst trying to sign in:  " . $e->getMessage());
              error_log("SIGNIN: An error occurred whilst trying to sign in using long token. " . $e->getMessage() . " Stack trace:" . $e->getTraceAsString());
            } catch (InvalidCredentialsException $e) {
              $Resp = Messages::GenerateErrorJSON("INVALID_CREDENTIALS", "The long token provided is invalid. " . $e->getMessage());
            } catch (Exception $e) {
              $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal exception whilst trying to sign in. " . $e->getMessage());
            }
          }
        }
        break;
      }
      // Could be a problem: ACTIVITY CHECK may not be necessary as it only checks token validity.
    case "ACTIVITY_CHECK": {
        if (!$User) {
          $Resp = Messages::GenerateErrorJSON("SIGNIN_REQUIRED");
          break;
        }
        $Resp = array(
          "Result" => true
        );
        break;
      }

    case "GET_SCHEDULE": {
        if (!$User) {
          $Resp = Messages::GenerateErrorJSON("SIGNIN_REQUIRED");
          break;
        }
        try {
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

          $Result = json_api_encode($Fetcher->GetTimetable($User->GetGroupID(), $Date));

          if ($Result != false) {
            $Resp = array(
              "Result" => true,
              "ReasonCode" => "",
              "Body" => $Result
            );
          }
        } catch (InsuffcientPermissionException $e) {
          $Resp = Messages::GenerateErrorJSON("INSUFFCIENT_PERMISSION", $e->getMessage());
        } catch (OutOfBoundsException $e) {
          $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "The default timetable that corresponds to the specified day-of-the-week does not contain any data. Please contact administrator.");
        }
        break;
      }


    case "GET_TIMETABLE_RAW": {
        if (!$User) {
          $Resp = Messages::GenerateErrorJSON("SIGNIN_REQUIRED");
          break;
        }
        $Date = null;
        try {
          if (array_key_exists("Date", $Recv)) {
            $Date = new DateTime($Recv["Date"]);
          }
        } catch (Exception $e) {
          $Resp = Messages::GenerateErrorJSON("UNEXPECTED_ARGUMENT", "The specified date is invalid.");
          break;
        }
        if ($Date === null) {
          $Resp = Messages::GenerateErrorJSON("ILLEGAL_CALL", "Specify Date.");
          break;
        }

        $Fetcher = new Fetcher($User);

        if (array_key_exists("GroupID", $Recv)) {
          $TargetGroupID = $Recv["GroupID"];
        } else {
          $TargetGroupID = $User->GetGroupID();
        }

        $Resp = Messages::GenerateErrorJSON("UNEXPECTED_ARGUMENT", "The type specified is invalid.");

        switch ($Recv["Type"]) {
          case "Base": {
              if ($User->IsPermitted("Timetable.View", DEST_GROUP, $TargetGroupID)) {
              } else {
                $Resp = Messages::GenerateErrorJSON("INSUFFCIENT_PERMISSION");
                break;
              }
              // NOT DAY OF THE WEEK, REALLY?
              $IndexOfTheWeek = null;
              if (array_key_exists("Date", $Recv)) {
                $IndexOfTheWeek = (int)$Date->format("w");
              } else if (array_key_exists("DayOfTheWeek", $Recv)) {
                $IndexOfTheWeek = DayEnum::StrToEnum($Recv["DayOfTheDate"]);
              } else {
                $Resp = Messages::GenerateErrorJSON("ILLEGAL_CALL", "Specify at least one of DATE or DayOfTheWeek.");
                break;
              }
              try {
                $Timetable = $Fetcher->GetDefaultTimetable(
                  $TargetGroupID,
                  // Note here: Because PHP Datetime::format() format character "w" follows ISO-8601, DayEnum corresponds to it.
                  $IndexOfTheWeek
                );
                $Resp = array(
                  "Result" => true,
                  "Body" => $Timetable
                );
                break;
              } catch (OutOfBoundsException $e) {
                $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "The default timetable that corresponds to the specified day-of-the-week does not contain any data. Please contact administrator.");
                break;
              }

              break;
            }
          case "Diff": {
              if ($User->IsPermitted("Timetable.View", DEST_GROUP, $TargetGroupID)) {

                $Target_Revision = null;
                if (array_key_exists("Revision", $Recv)) {
                  if (is_int($Recv["Revision"])) {
                    $Target_Revision = intval($Recv["Revision"]);
                  } else {
                    $Resp = Messages::GenerateErrorJSON("INPUT_MALFORMED", "The specified revision is not a valid number.");
                    break;
                  }
                }
                $Diff = $Fetcher->GetTimetableDiff($TargetGroupID, $Date, $Target_Revision);
                $Resp = array(
                  "Result" => true,
                  "Body" => $Diff["Body"],
                  "Revision" => intval($Diff["Revision"])
                );
              } else {
                $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an error whilst trying to fetch timetable diff.");
                break;
              }

              break;
            }
          default: {
              break;
            }
        }

        break;
      }

    case "GET_SCHOOL_CONFIG": {
        if (!$User) {
          $Resp = Messages::GenerateErrorJSON("SIGNIN_REQUIRED");
          break;
        }
        // Need to check permissions here.

        $TargetSchoolID = null;
        if (array_key_exists("SchoolID", $Recv)) {
          $TargetSchoolID = $Recv["SchoolID"];
        } else {
          $TargetSchoolID = $User->GetSchoolID();
        }
        if ($TargetSchoolID === null) {
          if (!$User->GetSchoolID()) {
            $Resp = Messages::GenerateErrorJSON("UNEXPECTED_ARGUMENT", "The user does not belong to any school.");
          } else {
            $Resp = Messages::GenerateErrorJSON("ILLEGAL_CALL", "Specify school ID.");
          }
        }

        $Permitted = false;
        if ($User->IsPermitted("Config.Subjects.View", DEST_SCHOOL, $TargetSchoolID)) {
          $Permitted = true;
        } else {
          $Permitted = false;
        }

        if (!$Permitted) {
          $Resp = Messages::GenerateErrorJSON("INSUFFCIENT_PERMISSION", "You do not have sufficient permission for that config.");
          break;
        }

        // Fetch school profile(raw)
        $Connection = DBConnection::Connect();
        $PDOstt = $Connection->prepare("select DisplayName, Config from school_profile where SchoolID = :SchoolID");
        $PDOstt->bindValue(":SchoolID", $TargetSchoolID);
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

        if (!$User) {
          $Resp = Messages::GenerateErrorJSON("SIGNIN_REQUIRED");
          break;
        }
        // Fetch user profile(raw)
        $Connection = DBConnection::Connect();
        $PDOstt = $Connection->prepare("select BelongGroupID, BelongSchoolID, DisplayName from user_profile where BelongUserID = :UserID");
        $PDOstt->bindValue(":UserID", $User->GetUserID());
        $PDOstt->execute();
        $Data = $PDOstt->fetch();
        if ($Data === false) {
          error_log("Error whilst trying to fetch from table 'user_profile'. TargetID:'$User->GetUserID()', Info:" . implode(",", $PDOstt->errorInfo()));
          $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal error while trying to fetch user profile.");
          break;
        } else if ($Data === null) {
          error_log("Error whilst trying to fetch from table 'user_profile'. No data matched UserID: " . $User->GetUserID() . " , Info: " . implode(",", $PDOstt->errorInfo()));
          $Resp = Messages::GenerateErrorJSON("UNEXPECTED_ARGUMENT", "That user does not exist.");
          break;
        }

        $UserDisplayName = $Data["DisplayName"];
        $GroupID = $Data["BelongGroupID"];
        $SchoolID = $Data["BelongSchoolID"];

        if ($SchoolID !== null) {
          // Fetch school name
          $PDOstt = $Connection->prepare("select DisplayName from school_profile where SchoolID = :SchoolID");
          $PDOstt->bindValue(":SchoolID", $SchoolID);
          $PDOstt->execute();
          $Data = $PDOstt->fetch();
          if ($Data === false) {
            // might not be an error, though
            error_log("Error whilst trying to fetch from table 'school_profile'. No data matched SchoolID:'$SchoolID', Info:" . implode(",", $PDOstt->errorInfo()));
            $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal error while trying to fetch user profile.");
            break;
          }

          if ($Data === null) {
            $SchoolDisplayName = null;
            error_log("Warning in action GET_USER_PROFILE: SchoolID $SchoolID is defined but its DisplayName is null!");
          } else {
            $SchoolDisplayName = $Data["DisplayName"];
          }
        } else {
          $SchoolDisplayName = null;
        }

        if ($GroupID !== null) {
          // Fetch group name
          $PDOstt = $Connection->prepare("select DisplayName from group_profile where GroupID = :GroupID");
          $PDOstt->bindValue(":GroupID", $GroupID);
          $PDOstt->execute();
          $Data = $PDOstt->fetch();
          if ($Data === false) {
            $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal error while trying to fetch user profile.");
            error_log("Error whilst trying to fetch from table 'group_profile'. GroupID $GroupID specified but its DisplayName was not found. Info:" . implode(",", $PDOstt->errorInfo()));
            break;
          }
          if ($Data === null) {
            $GroupDisplayName = null;
            error_log("Warning in action GET_USER_PROFILE: GroupID $GroupID is defined but its DisplayName is null!");
          } else {
            $GroupDisplayName = $Data["DisplayName"];
          }
        } else {
          $GroupDisplayName = null;
        }

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


      /**
       * ACTION GET_EDIT_STASH
       * Arguments: 
       *    *"Date" (Any PHP recognizable string)
       *     "GroupID" Optional, target group ID
       *     "TargetRevision" Optional, a revision number to fetch. If not specified, fetch the newest
       */
    case "GET_EDIT_STASH": {
        if (!$User) {
          $Resp = Messages::GenerateErrorJSON("SIGNIN_REQUIRED");
          break;
        }
        $TargetGroupID = "";
        if (array_key_exists("GroupID", $Recv)) {
          $TargetGroupID = $Recv["GroupID"];
        } else {
          $TargetGroupID = $User->GetGroupID();
        }

        if ($TargetGroupID == null) {
          $Resp = Messages::GenerateErrorJSON("ILLEGAL_CALL", "The user is not in a group or in multiple group. Specify one group.");
          break;
        }

        if ($User->IsPermitted("Timetable.Edit", DEST_GROUP, $TargetGroupID)) {
        } else {
          throw new InsuffcientPermissionException("You cannot view the timetable of that group.");
        }

        $Date = null;
        try {
          if (array_key_exists("Date", $Recv)) {
            $Date = new DateTime($Recv["Date"]);
          }
        } catch (Exception $e) {
          $Resp = Messages::GenerateErrorJSON("UNEXPECTED_ARGUMENT", "The specified date is invalid.");
          break;
        }
        if ($Date === null) {
          $Resp = Messages::GenerateErrorJSON("ILLEGAL_CALL", "Specify Date.");
          break;
        }

        $TargetRevision = null;
        $ReturnData = null;
        if (array_key_exists("Revision", $Recv)) {
          if (is_int($Recv["Revision"])) {
            $TargetRevision = intval($Recv["Revision"]);
          } else {
            $Resp = Messages::GenerateErrorJSON("INPUT_MALFORMED", "The specified revision is not an integer or out of range.");
            break;
          }
        }

        $Connection = DBConnection::Connect();
        $PDOstt = $Connection->prepare("select Revision, StashData, CreatedAt from edit_stash where UserID = :UserID AND DestGroupID = :GroupID AND TargetDate = :TargetDate ORDER BY 'Revision' DESC");
        $PDOstt->bindValue(":UserID", $User->GetUserID());
        $PDOstt->bindValue(":GroupID", $TargetGroupID);
        $PDOstt->bindValue(":TargetDate", $Date->format("Y-m-d"));
        $PDOstt->execute();
        $Data = $PDOstt->fetchAll();

        if ($Data === false) {
          error_log("An error occurred in action GET_EDIT_STASH: Could not fetch data from `edit_stash`. TargetUserID: " . $User->GetUserID() . ", DestGroupID: $TargetGroupID. This GroupID might not exist.");
          $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal error while trying to fetch stash. Group ID might be invalid!");
          break;
        } else if (empty($Data)) {
          $Resp = array(
            "Result" => true,
            "Revision" => -1,
            "Content" => null
          );
        } else {
          // It's damn inefficient
          if ($TargetRevision === null) {
            $TargetRevision = 0;
            foreach ($Data as $Segment) {
              $SegRev = intval($Segment["Revision"]);
              if ($SegRev > $TargetRevision) {
                $TargetRevision = $SegRev;
              }
            }
          }

          foreach ($Data as $Entry) {
            if (intval($Entry["Revision"]) === $TargetRevision) {
              $ReturnData = $Entry;
              break;
            }
          }

          $Resp = array(
            "Result" => true,
            "Revision" => intval($ReturnData["Revision"]),
            "CreatedAt" => $ReturnData["CreatedAt"],
            "Body" => $ReturnData["StashData"]
          );
        }
        break;
      }


      /**
       * ACTION SET_EDIT_STASH
       * Arguments: 
       *    *"Body" Timetable JSON. Must be parsable.
       *    *"Date" (Any PHP recognizable string)
       *     "GroupID" Optional, target group ID
       * 
       * Returns:
       *    *"Result" bool: true on success, false on failure
       *     "Revision" The revision number that was created.
       */
    case "SET_EDIT_STASH": {
        if (!$User) {
          $Resp = Messages::GenerateErrorJSON("SIGNIN_REQUIRED");
          break;
        }
        if ($Recv["Body"] === null) {
          $Resp = Messages::GenerateErrorJSON("ILLEGAL_CALL", "Specify 'Body' to save.");
        }

        $TargetGroupID = "";
        if (array_key_exists("GroupID", $Recv)) {
          $TargetGroupID = $Recv["GroupID"];
        } else {
          $TargetGroupID = $User->GetGroupID();
        }

        if ($TargetGroupID == null) {
          $Resp = Messages::GenerateErrorJSON("UNEXPECTED_ARGUMENT", "The group ID is not found.");
          break;
        }

        $Date = null;
        try {
          if (array_key_exists("Date", $Recv)) {
            $Date = new DateTime($Recv["Date"]);
          }
        } catch (Exception $e) {
          $Resp = Messages::GenerateErrorJSON("UNEXPECTED_ARGUMENT", "The specified date is invalid.");
          break;
        }
        if ($Date === null) {
          $Resp = Messages::GenerateErrorJSON("ILLEGAL_CALL", "Specify Date.");
          break;
        }

        if ($User->IsPermitted("Timetable.Edit", DEST_GROUP, $TargetGroupID)) {
        } else {
          throw new InsuffcientPermissionException("You cannot view the timetable of that group.");
        }

        $NewRevision = null;
        $Connection = DBConnection::Connect();
        $PDOstt = $Connection->prepare("select Revision, StashData, CreatedAt from edit_stash where UserID = :UserID AND DestGroupID = :GroupID AND TargetDate = :SetDate");
        $PDOstt->bindValue(":UserID", $User->GetUserID());
        $PDOstt->bindValue(":GroupID", $TargetGroupID);
        $PDOstt->bindValue(":SetDate", $Date->format("Y-m-d"));
        $PDOstt->execute();
        $Data = $PDOstt->fetchAll();

        // Not really efficient, but due to ORDER BY Revision not really working
        if ($Data === false) {
          $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal error while trying to fetch stashed data. The group ID might be invalid!");
          break;
        } else if (empty($Data)) {
          $NewRevision = 0;
        } else {
          $NewRevision = -1;
          foreach ($Data as $Segment) {
            $SegRev = intval($Segment["Revision"]);
            if ($SegRev > $NewRevision) {
              $NewRevision = $SegRev;
            }
          }
          $NewRevision = $SegRev + 1;
        }

        $PDOstt = $Connection->prepare("insert into edit_stash (`UserID`, `DestGroupID`, `TargetDate`, `Revision`, `StashData`) VALUES (:UserID, :GroupID, :TargetDate, :Revision, :StashData)");
        $PDOstt->bindValue(":UserID", $User->GetUserID());
        $PDOstt->bindValue(":GroupID", $TargetGroupID);
        $PDOstt->bindValue(":TargetDate", $Date->format("Y-m-d"));
        $PDOstt->bindValue(":Revision", $NewRevision);
        $PDOstt->bindValue(":StashData", $Recv["Body"]);
        $Result = $PDOstt->execute();

        if ($Result) {
          $Resp = array(
            "Result" => true,
            "Revision" => $NewRevision
          );
        } else {
          $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal error occurred while connecting to the database.");
          error_log("There was an error while trying to add stash data: " . $PDOstt->errorCode());
        }
        break;
      }

    case "SET_TIMETABLE": {
        if (!$User) {
          $Resp = Messages::GenerateErrorJSON("SIGNIN_REQUIRED");
          break;
        }
        if ($Recv["Body"] === null) {
          $Resp = Messages::GenerateErrorJSON("ILLEGAL_CALL", "Specify 'Body' to save as stash.");
        }

        $TargetGroupID = "";
        if (array_key_exists("GroupID", $Recv)) {
          $TargetGroupID = $Recv["GroupID"];
        } else {
          $TargetGroupID = $User->GetGroupID();
        }

        if ($TargetGroupID == null) {
          $Resp = Messages::GenerateErrorJSON("UNEXPECTED_ARGUMENT", "The group ID is not found.");
          break;
        }

        if ($User->IsPermitted("Timetable.Edit", DEST_GROUP, $TargetGroupID)) {
        } else {
          $Resp = Messages::GenerateErrorJSON("INSUFFCIENT_PERMISSION", "You cannot edit the timetable of that group.");
          break;
        }

        $Date = null;
        try {
          if (array_key_exists("Date", $Recv)) {
            $Date = new DateTime($Recv["Date"]);
          }
        } catch (Exception $e) {
          $Resp = Messages::GenerateErrorJSON("UNEXPECTED_ARGUMENT", "The specified date is invalid.");
          break;
        }
        if ($Date === null) {
          $Resp = Messages::GenerateErrorJSON("ILLEGAL_CALL", "Specify Date.");
          break;
        }

        $NewRevision = null;
        $Connection = DBConnection::Connect();
        $PDOstt = $Connection->prepare("select Revision from timetable where BelongGroupID = :GroupID AND Date = :Date");
        $PDOstt->bindValue(":GroupID", $TargetGroupID);
        $PDOstt->bindValue(":Date", $Date->format("Y-m-d"));
        $PDOstt->execute();
        $Data = $PDOstt->fetchAll();

        // Not really efficient, but due to ORDER BY Revision not really working
        if ($Data === false) {
          $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal error while trying to fetch stashed data. The group ID might be invalid!");
          break;
        } else if (empty($Data)) {
          $NewRevision = 0;
        } else {
          $NewRevision = -1;
          foreach ($Data as $Segment) {
            $SegRev = intval($Segment["Revision"]);
            if ($SegRev > $NewRevision) {
              $NewRevision = $SegRev;
            }
          }
          $NewRevision = $SegRev + 1;
        }

        $PDOstt = $Connection->prepare("insert into timetable (`BelongGroupID`, `Date`, `Revision`, `Body`) VALUES (:GroupID, :Date, :Revision, :Body)");
        $PDOstt->bindValue(":GroupID", $TargetGroupID);
        $PDOstt->bindValue(":Date", $Date->format("Y-m-d"));
        $PDOstt->bindValue(":Revision", $NewRevision);
        $PDOstt->bindValue(":Body", json_api_encode($Recv["Body"]));
        $Result = $PDOstt->execute();

        if ($Result) {
          $Resp = array(
            "Result" => true,
            "Revision" => $NewRevision,
          );
        } else {
          $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal error occurred while connecting to the database.");
          error_log("There was an error while trying to update timetable: " . $PDOstt->errorCode());
        }
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

    default: {
        error_log("An error occurred in API: Undefined action " . $Recv["Action"] . ". Is this API old?");
        $Resp = Messages::GenerateErrorJSON("UNEXPECTED_ARGUMENT", "That action is invalid.");
        break;
      }
  }

  break;
}

echo json_api_encode($Resp);

exit;
