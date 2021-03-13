<?php
// Requirement: PHP 8.0.0 or later
// TODO: PHP envilonment requirement
// WIP.

require "/bin/lib.php";

// These might be removed
error_reporting(E_ALL);
ini_set("log_errors", "On");
ini_set("display_errors", 0);

function ErrShutdown() {
  error_log("API shut down with an fatal error.");
  exit(json_api_encode(array(
    "Result" => false,
    "ReasonCode" => "INTERNAL_EXCEPTION",
    "ReasonText" => "There was an error in API."
  )));
}

// BASICではよくある、 while(true) -> break. try~catch(exception e)~finally ができるやり方。

while (true) {
  $Recv = json_decode(file_get_contents("php://input"), true);
  $User = null;

  if ($Recv === null || $Recv === false) {
    $Resp = array(
      "Result" => false,
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
                $Resp = Messages::GenerateErrorJSON($User->GetError()["Code"]);

                break;
            }
          } catch (ConnectionException $e) {
            $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal exception whilst trying to sign in:  " . $e->getMessage());
            error_log("SIGNIN: An error occurred whilst trying to sign in using passphrase. " . $e->getMessage() . " Stack trace:" . $e->getTraceAsString());
          } catch (InvalidCredentialsException $e) {
            $Resp = Messages::GenerateErrorJSON("INVALID_CREDENTIALS", "The passphrase provided is invalid. " . $e->getMessage());
          } catch (InvalidArgumentException $e) {
            $Resp = Messages::GenerateErrorJSON("INVALID_CREDENTIALS", "The Email address provided is invalid. " . $e->getMessage());
          } catch (TooManyRequestsException $e) {
            $Resp = Messages::GenerateErrorJSON("TOO_MANY_REQUESTS", "Please wait before you can log-in / update session again. " .$e->getMessage());
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
                  $Resp = Messages::GenerateErrorJSON($User->GetError()["Code"]);
                  //var_dump("U/L detect");

                  break;
              }
            } catch (ConnectionException $e) {
              $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal exception whilst trying to sign in:  " . $e->getMessage());
              error_log("SIGNIN: An error occurred whilst trying to sign in using long token. " . $e->getMessage() . " Stack trace:" . $e->getTraceAsString());
            } catch (InvalidCredentialsException $e) {
              $Resp = Messages::GenerateErrorJSON("INVALID_CREDENTIALS", "The long token provided is invalid. " . $e->getMessage());
            } catch (TooManyRequestsException $e) {
              $Resp = Messages::GenerateErrorJSON("TOO_MANY_REQUESTS", "Please wait before you can log-in or update session again.");
            } catch (Exception $e) {
              $Resp = Messages::GenerateErrorJSON("INTERNAL_EXCEPTION", "There was an internal exception whilst trying to sign in. " . $e->getMessage());
              error_log("SIGNIN: An error occurred whilst trying to sign in using long token. " . $e->getMessage() . " Stack trace:" . $e->getTraceAsString());
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
            error_log("User ".$User->GetUserID()." is not permitted in $TargetGroupID??");
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

        $PermKey = false;
        $Permitted = false;
        switch ($Recv["Item"]) {
          case "Subjects": {
              $PermKey = "Config.Subjects.View";
              break;
            }
        }

        if ($PermKey === false) {
          $Resp = array(
            "Result" => false,
            "ReasonCode" => "UNEXPECTED_ARGUMENT",
            "ReasonText" => "The item type requested is not defined in the system. There might be a typo in your code!"
          );
          break;
        }

        if ($User->IsPermitted($PermKey, DEST_SCHOOL, $TargetSchoolID)) {
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

        try {
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
            throw new InsuffcientPermissionException("You cannot edit the timetable stash of that group.");
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
        } catch (InsuffcientPermissionException $e) {
          $Resp = Messages::GenerateErrorJSON("INSUFFCIENT_PERMISSION", "You do not have permission to edit that group's timetable.");
          break;
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

        try {
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
            throw new InsuffcientPermissionException("You cannot set the timetable stash of that group.");
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
        } catch (InsuffcientPermissionException $e) {
          $Resp = Messages::GenerateErrorJSON("INSUFFCIENT_PERMISSION", "You do not have permission to edit that group's timetable.");
          break;
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

if ($Resp["Result"] === false) {
  http_response_code(Messages::ErrorHTTPCodes[$Resp["ReasonCode"]] ?? 500);
}

echo json_api_encode($Resp);

exit;
?>