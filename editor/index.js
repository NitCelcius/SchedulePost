async function InitPage(User) {
  var Prof = await User.FetchPersonalInfo();
  var Resp = JSON.parse(Prof["Content"]);

  switch (Resp["ReasonCode"]) {
    case "ACCOUNT_SESSION_TOKEN_INVALID":
    case "ACCOUNT_SESSION_TOKEN_EXPIRED": {
      LongToken = GetCookie("LongToken");
      if (LongToken != null && User.GetUserID() != null) {
        try {
          UpdateRes = await UpdateSessionToken(LongToken);
          if (UpdateRes) {
            // We can continue
            break;
          } else {
            // RIP, LongToken wasn't right
            TransferLoginPage();
            break;
          }
        } catch (e) {
          // In fact this catch might not be necessary.
          TransferLoginPage();
          break;
        }
      }
    }
    case "INVALID_CREDENTIALS": {
      TransferLoginPage();
      break;
    }
  }

  UserSchool = new School();
  EditDate = new Date();

  // So weird but it actually works.
  FetchCfg = async function () {
    var Res = await UserSchool.FetchConfig(User, "Subjects");
    return { "Result": Res };
  }

  AttemptFunc = async (Tryer) => {
    for (var i = 0; i < 3; i++) {
      Data = await Tryer;
      if (Tryer["Result"]) {
        return Tryer;
      } else {
        await Delay(2000);
      }
      if (i == 2) {
        return false;
      }
    }
  }

  [CfgState, TTBase, TTDiff] = await Promise.all([
    AttemptFunc(await FetchCfg()),
    AttemptFunc(await User.GetTimeTableBase(EditDate)),
    AttemptFunc(await User.GetTimeTableDiff(EditDate)),
  ]);

  // Todo: keep working.
  /*
  First of all implement EDITOR.
   - Create outline of website, then just modify those w/ jS.
  */

  /*
  TTBase = await User.GetTimeTableBase(EditDate);
  if (TTBase.Result) {
  } else {
    // handle some error
    throw new Error("Could not load timetable base.");
  }

  TTDiff = await User.GetTimeTableDiff(EditDate);
  if (TTBase.Result) {} else {
    // handle some error
    throw new Error("Could not load timetable base.");
  }
  */
}

var UserID = GetCookie("UserID");
var SessionToken = GetCookie("SessionToken");

User = new User(UserID, SessionToken);
UserSchool = null;
UserGroup = null;

SubjectsConfig = null;
TTBase = null;
TTDiff = null;

if (UserID == null || SessionToken == null) {
  TransferLoginPage();
}

InitPage(User);