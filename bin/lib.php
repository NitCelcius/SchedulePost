<?php
$GLOBALS["DB_URL"] = getenv("SP_DB_URL");
$GLOBALS["DB_Username"] = getenv("SP_DB_USER");
$GLOBALS["DB_PassPhrase"] = getenv("SP_DB_PASSPHRASE");
$GLOBALS["DB_PORT"] = getenv("SP_DB_PORT");
$GLOBALS["DB_NAME"] = getenv("SP_DB_NAME");
//type false exactly!!
$GLOBALS["PUBLIC_MODE"] = (getenv("SP_PUBLIC_MODE") === "false") ? false : true;

if (($GLOBALS["DefaultTimeZone"] = getenv("SP_TIMEZONE")) === null) {
  $GLOBALS["DefaultTimeZone"] = "UTC";
}
date_default_timezone_set($GLOBALS["DefaultTimeZone"]);

$GLOBALS["SessionTokenExpiry"] = getenv("SP_SESSIONTOKENEXPIRY") ?? "30 minutes";
$GLOBALS["LongTokenExpiry"] = getenv("SP_LONGTOKENEXPIRY") ?? "14 days";

$GLOBALS["SIGNIN_DELAY"] = getenv("SP_SIGNINDELAY") ?? (30 * 1000);

$GLOBALS["Connection"] = null;

define("SQL_FORBIDDEN_CHARS", ";");

if (strpbrk($GLOBALS["DB_URL"], SQL_FORBIDDEN_CHARS)) {
  http_response_code(503);
  error_log("Database URL contains at least one character that cannot be used! Please check your envilonment variables. Forbidden characters are: " . SQL_FORBIDDEN_CHARS);
}

if (!is_numeric($GLOBALS["DB_PORT"]) && !intval($GLOBALS["DB_PORT"]) == floatval($GLOBALS["DB_PORT"])) {
  http_response_code(503);
  error_log("Database port is invalid! Please specify it in your envilonment variables.");
  exit("ERROR: The server is not yet set up.");
}

if (strpbrk($GLOBALS["DB_Username"], SQL_FORBIDDEN_CHARS)) {
  http_response_code(503);
  error_log("Database username contains at least one character that cannot be used! Please check your envilonment variables. Forbidden characters are: " . SQL_FORBIDDEN_CHARS);
  exit("ERROR: The server is not yet set up.");
}

if (strpbrk($GLOBALS["DB_PassPhrase"], SQL_FORBIDDEN_CHARS)) {
  http_response_code(503);
  error_log("Database username contains at least one character that cannot be used! Please check your envilonment variables. Forbidden characters are: " . SQL_FORBIDDEN_CHARS);
  exit("ERROR: The server is not yet set up.");
}

if (strpbrk($GLOBALS["DB_NAME"], SQL_FORBIDDEN_CHARS)) {
  http_response_code(503);
  error_log("Database username contains at least one character that cannot be used! Please check your envilonment variables. Forbidden characters are: " . SQL_FORBIDDEN_CHARS);
  exit("ERROR: The server is not yet set up.");
}

if (DateInterval::createFromDateString($GLOBALS["SessionTokenExpiry"]) === false) {
  http_response_code(503);
  error_log("The envilonment variable SP_SESSIONTOKENEXPIRY is invalid! (must be a PHP recognizable date string)");
  exit("ERROR: The server is not yet set up.");
}

if (DateInterval::createFromDateString($GLOBALS["LongTokenExpiry"]) === false) {
  http_response_code(503);
  error_log("The envilonment variable SP_LONGTOKENEXPIRY is invalid! (must be a PHP recognizable date string)");
  exit("ERROR: The server is not yet set up.");
}

if (!is_numeric($GLOBALS["SIGNIN_DELAY"]) && !intval($GLOBALS["SIGNIN_DELAY"]) == floatval($GLOBALS["SIGNIN_DELAY"])) {
  http_response_code(503);
  error_log("SIGNIN_DELAY is invalid (it must contain an integer)! Please specify it in your envilonment variables.");
  exit("ERROR: The server is not yet set up.");
} else {
  $GLOBALS["SIGNIN_DELAY"] = intval($GLOBALS["SIGNIN_DELAY"]);
}


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
define("TOO_MANY_REQUESTS", -50);

define("INTERNAL_EXCEPTION", -100);

// Following ISO-8601.
define("DAY_SUNDAY", 0);
define("DAY_MONDAY", 1);
define("DAY_TUESDAY", 2);
define("DAY_WEDNESDAY", 3);
define("DAY_THURSDAY", 4);
define("DAY_FRIDAY", 5);
define("DAY_SATURDAY", 6);

define("DEST_SCHOOL", 1);
define("DEST_GROUP", 2);

function ReplaceArgs(string $Basement, array $Args) {
  return str_replace(array_keys($Args), array_values($Args), $Basement);
}

class DayEnum {
  private const Dic = array(
    "DAY_MONDAY" => DAY_MONDAY,
    "DAY_TUESDAY" => DAY_TUESDAY,
    "DAY_WEDNESDAY" => DAY_WEDNESDAY,
    "DAY_THURSDAY" => DAY_THURSDAY,
    "DAY_FRIDAY" => DAY_FRIDAY,
    "DAY_SATURDAY" => DAY_SATURDAY,
    "DAY_SUNDAY" => DAY_SUNDAY
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
      "DefaultAllowRoles" => array("Admin", "Teacher", "Student"),
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

class TooManyRequestsException extends Exception {
  private $Message;

  public function __construct($Message, $Location = null, $Prev = null) {
    $this->Message = $Message;
    $this->Location = $Location;
    parent::__construct($Message, 0, null);
  }

  public function __toString() {
    return "Too many requests received. Additional information: " . $this->Message . " Location: " . $this->Location;
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
    return "Insufficient permission. Additional information: " . $this->Message . " Location: " . $this->Location;
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
        try {
          $Connection = new PDO(
            sprintf(
              "mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4",
              $GLOBALS["DB_URL"],
              $GLOBALS["DB_PORT"],
              $GLOBALS["DB_NAME"]
            ),
            $GLOBALS["DB_Username"],
            $GLOBALS["DB_PassPhrase"],
            array(
              PDO::MYSQL_ATTR_FOUND_ROWS => true
            )
          );
          $Connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
          if ($Connection === false) {
            throw new InvalidArgumentException("PDO initialization failed. Check DB-related settings in your envilonment variables!");
          }
          $GLOBALS["Connection"] = $Connection;
        } catch (Exception $e) {
          error_log("Cannot connect to the database! Error info:" . $e->getMessage());
        }
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
   * Sign-in using EMAIL and PASSPHRASE. Basically this converts arguments and sign-in using SignInFromUserIDAndLongToken.
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
    } catch (TooManyRequestsException $e) {
      throw $e;
      return false;
    } catch (Exception $e) {
      error_log("Error whilst trying to fetch from table 'accounts'. UserID:'$this->UserID', Info:" . implode(",", $PDOstt->errorInfo()));
      throw new ConnectionException("Could not process connection properly.", "Internal function");
      return false;
    }

    if (password_verify($PassPhrase, $VerifyHash)) {
      try {
        $Connection = DBConnection::Connect();

        $LongToken = bin2hex(openssl_random_pseudo_bytes(64));
        $PDOstt = $Connection->prepare("update accounts set LongToken = :LongToken,LongTokenGenAt = :LongTokenGenAt where UserID = :UserID");
        if ($PDOstt === false) {
          error_log("Could not generate PDOStatement. This is possibly a bug!");
          throw new ConnectionException("Could not connect to the database.", "Database: SchedulePost");
          return false;
        }
        $UpdateDateTime = new DateTime("now", new DateTimeZone($GLOBALS["DefaultTimeZone"]));
        $PDOstt->bindValue(":UserID", $this->UserID);
        $PDOstt->bindValue(":LongToken", $LongToken);
        $PDOstt->bindValue(":LongTokenGenAt", $UpdateDateTime->format("Y-m-d H:i:s"));
        $PDOstt->execute();
        //error_log($PDOstt->debugDumpParams());
        if ($PDOstt->rowCount() == 0) {
          error_log("Failed to update LongTokenof user $this->UserID: No row had been updated by PDO. PDO message: $PDOstt->errorInfo()");
          throw new ConnectionException("LongToken was not updated in the database.");
        }
        
        // Perhaps this is unnecessary.
        // $this->UpdateSessionToken();
        // It was.

        return $LongToken;
      } catch (ConnectionException $e) {
        throw $e;
        return false;
      } catch (TooManyRequestsException $e) {
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

      $stt = $Connection->prepare("Select LastSigninAt from `accounts` where UserID = :UserID");
      $stt->bindValue(":UserID", $this->UserID, PDO::PARAM_STR);
      $stt->execute();
      $Dt = $stt->fetch();
      if ($Dt === false) {
        error_log("An error occurred in UpdateSessionToken(): The table `accounts` does not have corresponding column LastSigninAt. TargetUserID: $this->UserID" . ", " . implode(",", $stt->errorinfo()));
      }

      $LastTry = new DateTime("1970/01/01 00:00:00");
      if (isset($Dt["LastSigninAt"])) {
        $LastTry = new DateTime($Dt["LastSigninAt"]);
      }
      $Now = new DateTime();

      if (abs($Now->getTimestamp() - $LastTry->getTimestamp()) <= $GLOBALS["SIGNIN_DELAY"]) {
        error_log("Sign-in throttling detected for user ID $this->UserID !");
        throw new TooManyRequestsException("Please wait before you can update sessiontoken. " . abs($Now->getTimestamp() - $LastTry->getTimestamp()));
        return false;
      }

      $Updater = $Connection->prepare(" Update `accounts` set `LastSigninAt`=:LoginDateTime,`SessionToken`=:SessionToken,`LastActivityAt`=:LastActivityAt WHERE UserID=:UserID");
      $Token = bin2hex(openssl_random_pseudo_bytes(32));
      $LoginDateTime = new DateTime("now", new DateTimeZone($GLOBALS["DefaultTimeZone"]));
      $Updater->bindValue(":LastActivityAt", $LoginDateTime->format("Y-m-d H:i:s"), PDO::PARAM_STR);
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
    } catch (TooManyRequestsException $e) {
      throw $e;
      return false;
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
      error_log("An error occurred in LookupSchoolID(): Error whilst trying to fetch from `group_profile`. TargetGroupID:$GroupID, Info:" . implode(",", $PDOstt));

      throw new ConnectionException("Could not connect to the database.");
      return false;
    } else if ($Result === null) {
      return null;
    }

    return $Result["BelongSchoolID"];
  }

  function GetUserHomework(string $UserID, int $MaxCount, HomeworkLookupParams $Params) {
    
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
    "SIGNIN_REQUIRED" => "You need to be signed in to do that.",
    "TOO_MANY_REQUESTS" => "Please wait until you can do that again."
  );

  public const ErrorHTTPCodes = array(
    "ERROR_UNKNOWN" => 500,
    "INPUT_MALFORMED" => 400,
    "UNEXPECTED_ARGUMENT" => 400,
    "INTERNAL_EXCEPTION" => 500,
    "INVALID_CREDENTIALS" => 403,
    "INSUFFCIENT_PERMISSION" => 403,
    "ACCOUNT_SESSION_TOKEN_EXPIRED" => 403,
    "ACCOUNT_SESSION_TOKEN_INVALID" => 403,
    "ACCOUNT_LONG_TOKEN_INVALID" => 403,
    "ACCOUNT_LONG_TOKEN_EXPIRED" => 403,
    "ACCOUNT_CREDENTIALS_INVALID" => 403,
    "ILLEGAL_CALL" => 400,
    "SIGNIN_REQUIRED" => 401,
    "TOO_MANY_REQUESTS" => 429
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

class HomeworkLookupParams {
  private $StateName;
  private $FromDate;
  private $UntilDate;

  function __construct(string $StateName = null, DateTime $FromDate = null, DateTime $UntilDate = null ) {
    $this->StateName = $StateName;
    $this->FromDate = $FromDate;
    $this->UntilDate = $UntilDate;
  }

}

// Well this does look bad
class StartAndEndTime {
  private $StartTime;
  private $EndTime;

  function __construct(DateTime $Start, DateTime $End)
  {
    $this->EndTime = $Start;
    $this->StartTime = $End;
    $this->Validate();
  }

  function Validate() {
    if ($this->StartTime > $this->EndTime) {
      $Temp = $this->EndTime;
      $this->EndTime = $this->StartTime;
      $this->StartTime = $Temp;
    }
  }

  function SetStartTime(DateTime $StartTime) {
    $this->StartTime = $StartTime;
    $this->Validate();
  }

  function SetEndTime(DateTime $EndTime) {
    $this->EndTime = $EndTime;
    $this->Validate();
  }
  
  function Get() {
    return [
      "StartTime" => $this->StartTime,
      "EndTime" => $this->EndTime
    ];
  }

  function GetStartTime() {
    return $this->StartTime;
  }

  function GetEndTime() {
    return $this->EndTime;
  }
}

function json_api_encode($Obj) {
  // default JSON encode
  return json_encode($Obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_FORCE_OBJECT | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}
?>