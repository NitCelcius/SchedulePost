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
          console.warn(UpdateRes);
          throw new Error("Stop!!!!");

          if (UpdateRes) {
            // We can continue
            break;
          } else {
            // RIP, LongToken wasn't right
            TransferLoginPage();
            break;
          }
        } catch (e) {
          throw new Error("Stop!!!!");
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
  //TODO: Select groups.
  // And this is really inefficient.
  GroupProf = await User.GetGroupProfile();
  console.info(GroupProf);
  document.getElementById("Timetable_Group").innerText = GroupProf.DisplayName;
  //Timetable_Group

  UserSchool = new School(User.GetSchoolProfile().ID);
  EditDate = new Date("2021-01-25");

  // So weird but it actually works.
  FetchCfg = async function () {
    var Res = await UserSchool.FetchConfig(User, "Subjects");
    return {
      "Result": Res
    };
  }

  var Local = LoadLocalStash();
  if (Local !== null) {
    Timetable = JSON.parse(Local);
    // Well we may need something to update status text.
    document.getElementById("Update_Status").innerText = "一時保存した内容を読み込みました";
    setTimeout(() => {
      document.getElementById("Update_Status").innerText = "変更があります。確定 を押すと時間割に反映します";
    }, 5000);
  } else {
    var UploadedStash = await DownloadStash();
    if (UploadedStash !== null) {
      Timetable = JSON.parse(UploadedStash["Body"]);
      Global["Revision"] = UploadedStash["Revision"];
      document.getElementById("Update_Status").innerText = "アップロードした内容を読み込みました";
      setTimeout(() => {
        document.getElementById("Update_Status").innerText = "変更があります。確定 を押すと時間割に反映します";
      }, 5000);
    } else {
      // No local, neither cloud saves!
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

      //Wait, are they necessary?
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
    }
  }
    
  SubjectsConfig = await UserSchool.GetConfig("Subjects", User);

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
  var SearchFrom = document.getElementById("Table_Body").children;
  for (var i = 0; i < SearchFrom.length; i++) {
    if (SearchFrom[i] == Obj.parentElement) {
    }
  }

  var Target = document.getElementById("Edit_ClassType");
  document.getElementById("Edit_Page_Wrapper").style.display = "flex";

  EditingKey = Obj.parentElement.style.getPropertyValue("--Key");

  ClassEdit_Setup(Obj.parentElement.style.getPropertyValue("--Key"));
}

async function ClassEdit_Setup(EditingClassKey) {
  // EditingClassKey can be replaced with EditingKey. Completely.
  document.getElementById("Edit_ClassLabelDisp").innerText = EditingClassKey;
  var UpdateFlag = false;
  document.getElementById("Edit_ClassType").childNodes.forEach(function (Option) {
    if (Option.id === "Edit_ClassNone") {
      UpdateFlag = true;
    }
  });

  if (UpdateFlag) {
    var Target = document.getElementById("Edit_ClassType");
    Target.innerHTML = "";

    var Keys = Object.keys(SubjectsConfig);
    for (var i = 0; i < Keys.length; i++) {
      var AddOption = document.createElement("Option");
      AddOption.value = Keys[i];
      AddOption.innerText = SubjectsConfig[Keys[i]].DisplayName;

      Target.appendChild(AddOption);
    }
  }

  document.getElementById("Edit_ClassType").childNodes.forEach(function (Candidate) {
    if (Candidate.value === Classes[EditingClassKey].ID) {
      Candidate.selected = true;
    } else {
      Candidate.selected = false;
    }
  });

  document.getElementById("Edit_Note").value = Classes[EditingClassKey].Note ?? null;
  document.getElementById("Edit_ClassLabel").value = EditingClassKey ?? null;
}

function Edit_DelAndCloseConfirm() {
  // ...
}

function Edit_CompleteConfirm() {
  // Somehow make this non-dialog.
  Flag = confirm("この時間割を確定してもよろしいですか？");

  if (Flag === true) {
    
  } else {
    // Do nothing !!!
  }
}

// BLOCKED.
function Edit_Upload(ClassList) {
  var Info = await AwaitAjaxy(API_URL, JSON.stringify({
    "Auth": {
      "UserID": User.UserID,
      "SessionToken": User.Credentials.SessionToken
    },
    "Action": "GET_EDIT_STASH",
    "GroupID": User.Profile.Group.ID
  }));
}

function Edit_Apply() {
// TODO: if KEY duplicates, that's not approved

  document.getElementById("Edit_Page_Wrapper").style.display = "none";

  var IsTimetableUpdated = false;
  var NewClassData = Classes[EditingKey]; // Copy that

  document.getElementById("Edit_ClassType").childNodes.forEach(function (Candidate) {
    if (Candidate.selected) {
      if (Classes[EditingKey].ID != Candidate.value) {
        IsTimetableUpdated = true;
      }
      NewClassData["ID"] = Candidate.value;
    }
  });

  var NoteText = document.getElementById("Edit_Note").value;
  if (Classes[EditingKey].Note != NoteText) {
    IsTimetableUpdated = true;
  }
  NewClassData.Note = NoteText;

  var NewKey = document.getElementById("Edit_ClassLabel").value;
  if (EditingKey != NewKey) {
    IsTimetableUpdated = true;
    // How do I remove ONLY that property?
    var NewClasses = {};
    var Keys = Object.keys(Classes);
    for (var i = 0; i < Keys.length; i++) {
      if (Keys[i] === EditingKey) {
        continue; // Just skip and delete it
      }
      NewClasses[Keys[i]] = Classes[Keys[i]]; //Copy
    }

    Classes = NewClasses;
  }

  if (IsTimetableUpdated) {
    Classes[NewKey] = NewClassData;
    UpdateTimeTable(Classes, SubjectsConfig, document.getElementById("Table_Body"), document.getElementById("Class_Base"));
    StartAutoStash();
  }
}

function AddClass() {
  var ClassKey = 0;
  for (ClassKey = Object.keys(Classes).length; true; ClassKey++) {
    if (!Classes[ClassKey]) {
      break;
    }
  }

  Classes[ClassKey] = { "ID": "Special" };

  // Clear
  UpdateTimeTable(Classes, SubjectsConfig, document.getElementById("Table_Body"), document.getElementById("Class_Base"));

  StartAutoStash();
}


function LoadLocalStash() {
  var LocalStash = localStorage.getItem("Timetable_Stash");
  if (LocalStash !== null) {
    return LocalStash;
  } else {
    return null;
  }
}

async function DownloadStash() {
   var Info = await AwaitAjaxy(API_URL, JSON.stringify({
     "Auth": {
       "UserID": User.UserID,
       "SessionToken": User.Credentials.SessionToken
     },
     "Action": "GET_EDIT_STASH",
     "GroupID": User.Profile.Group.ID
   }));
  
  var Data = JSON.parse(Info.Content);
  if (Data["Result"]) {
    if (Data[Revision] === -1) {
      // It's brand new!
      return null;
    } else {
      // It's well-prepared!
      return {
        "Revision": Data["Revision"],
        "Body": Data["Body"]
      }
    }
  } else {
    return false;
  }
}

// Though we need to save THIS to local storage
async function StartAutoStash() {
  document.getElementById("Update_Status").innerText = "一時保存しています...";
  var StoreData = JSON.stringify(Classes);
  localStorage.setItem("Timetable_Stash", StoreData);

  var Dt = new Date().getTime();
  if (SaveTimer) {
    clearTimeout(SaveTimer);
    SaveTimer = null;
  }
  if (Dt > LastSaveTime + SavePeriod) {
    await UploadStash();
  } else {
    // This is some kind of weirdness
    SaveTimer = setTimeout(function () {
      UploadStash();
    }, Dt - LastSaveTime + SavePeriod);
  }

  document.getElementById("Update_Status").innerText = "一時保存しました";
  setTimeout(() => {
    document.getElementById("Update_Status").innerText = "変更があります。確定 を押すと時間割に反映します";
  }, 5000);

}

async function UploadStash() {
  document.getElementById("Update_Status").innerText = "アップロードしています...";

  // Weirdness: global
  var Info = await AwaitAjaxy(API_URL, JSON.stringify({
    "Auth": {
      "UserID": User.UserID,
      "SessionToken": User.Credentials.SessionToken
    },
    "Action": "SET_EDIT_STASH",
    "GroupID": User.Profile.Group.ID,
    "Body": JSON.stringify(Classes)
  }));
  if (JSON.parse(Info["Content"])["Result"]) {
    document.getElementById("Update_Status").innerText = "アップロードして一時保存しました";
  } else {
    console.warn(Info);
    document.getElementById("Update_Status").innerText = "アップロードできませんでした";
  }

  setTimeout(() => {
  document.getElementById("Update_Status").innerText = "変更があります。確定 を押すと時間割に反映します";
}, 5000);


}

var UserID = GetCookie("UserID");
var SessionToken = GetCookie("SessionToken");

User = new User(UserID, SessionToken);
UserSchool = null;
UserGroup = null;

SubjectsConfig = null;
EditingKey = null;

LastSaveTime = 0;
SaveTimer = null;
const SavePeriod = 30000;

Global = {};
Classes = {};

if (UserID == null || SessionToken == null) {
  TransferLoginPage();
}

InitPage(User);
PrepareEditor(User);