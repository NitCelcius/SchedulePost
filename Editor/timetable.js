async function InitPage(User) {
  Prof = await User.FetchPersonalInfo();
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

async function PrepareEditor(User) {
  DeployLoadAnim();
  UserSchool = new School(User.GetSchoolProfile().ID);
  EditDate = new Date("2021-01-25");

  // So weird but it actually works.
  FetchCfg = async function () {
    var Res = await UserSchool.FetchConfig(User, "Subjects");
    return {
      "Result": Res
    };
  }

  AttemptFunc = async (Tryer) => {
    for (var i = 0; i < 3; i++) {
      Data = await Tryer;
      if (Tryer) {
        return Tryer;
      } else {
        await Delay(2000);
      }
      if (i == 2) {
        return false;
      }
    }
  }

  var CfgState, TTBase, TTDiff;
  [CfgState, TTBase, TTDiff] = await Promise.all([
    AttemptFunc(await FetchCfg()),
    AttemptFunc(await User.GetTimeTableBase(EditDate)),
    AttemptFunc(await User.GetTimeTableDiff(EditDate))
  ]);

  Timetable = TTBase["Body"]["TimeTable"];

  Global["Revision"] = TTDiff.Revision;
  // merge
  Timetable = MergeTimetable(TTBase["Body"]["TimeTable"], TTDiff["Body"]);
    
  console.info(Timetable);
  SubjectsConfig = await UserSchool.GetConfig("Subjects", User);
  console.info(SubjectsConfig);

  UpdateTimeTable(Timetable, SubjectsConfig, document.getElementById("Table_Body"), document.getElementById("Class_Base"));

  Classes = Timetable;

  DestructLoadAnim();
}

function MergeTimetable(Base, Diff) {
  Timetable = Base;
  Object.keys(Diff).forEach(Key => {
    Timetable[ClassData.Key] = Diff.Key;
  });

  return Timetable;
}

class TimetableStore {
  SubjectsConfig = null;
  Base = null;
  Diff = null;
  Revision = null;
}

function Class_Edit(Obj) {
  console.info(Obj.parentElement);
  SearchFrom = document.getElementById("Table_Body").children;
  for (var i = 0; i < SearchFrom.length; i++) {
    if (SearchFrom[i] == Obj.parentElement) {
      console.info(i);
    }
  }

  document.getElementById("Edit_Page_Wrapper").style.display = "block";
  ClassEdit_Setup(Classes[Obj.parentElement.style.getPropertyValue("--Key")], Obj.parentElement.style.getPropertyValue("--Key"));
}

async function ClassEdit_Setup(SubjectData, Key) {
  console.info(SubjectData);
  document.getElementById("Edit_ClassLabel").innerText = Key;
  console.info(document.getElementById("Edit_ClassSel"));
}

function Edit_Apply() {
  document.getElementById("Edit_Page_Wrapper").style.display = "none";
}

function AddClass() {
  while (true) {
    ClassKey = Object.keys(Classes).length;
    if (Classes[ClassKey]) {
      continue;
    }
    break;
  }

  Classes[ClassKey] = { "ID": "Special" };

  // Clear
  document.getElementById("Table_Body").innerHTML = "";
  UpdateTimeTable(Classes, SubjectsConfig, document.getElementById("Table_Body"), document.getElementById("Class_Base"));
}

var UserID = GetCookie("UserID");
var SessionToken = GetCookie("SessionToken");

User = new User(UserID, SessionToken);
UserSchool = null;
UserGroup = null;

SubjectsConfig = null;

Global = {};

Classes = {};

if (UserID == null || SessionToken == null) {
  TransferLoginPage();
}

InitPage(User);
PrepareEditor(User);