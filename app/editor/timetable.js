async function InitPage(User) {
  DeployLoadAnim();

  User.UpdateProfile().then(async function (Prof) {
    await PrepareEditor(User);
    DestructLoadAnim();
  });
}

async function PrepareEditor(User) {
  //TODO: Select groups.
  // And this is really inefficient.
  GroupProf = await User.GetGroupProfile();
  console.info(GroupProf);
  document.getElementById("Timetable_Group").innerText = GroupProf.DisplayName;
  //Timetable_Group

  UserSchool = new School(User.GetSchoolProfile().ID);
  EditingDate = new Date();

  // So weird but it actually works.
  FetchCfg = async function () {
    var Res = await UserSchool.FetchConfig(User, "Subjects");
    return {
      "Result": Res
    };
  }

  // No local, neither cloud saves!
  AttemptFunc = async (Tryer) => {
    for (var i = 0; i < 3; i++) {
      var Data = await Tryer;
      if (Data !== false) {
        return Tryer;
      } else {
        await Delay(2000);
      }
      if (i == 2) {
        console.warn("AttemptFunc failed!");
        return false;
      }
    }
  }

  //Wait, are they necessary?

  //BUG: These returns same things
  var CfgState = null;
  var TTBase = null;
  var TTDiff = null;
  [CfgState, TTBase, TTDiff] = await Promise.all([
    AttemptFunc(FetchCfg()),
    AttemptFunc(User.GetTimeTableBase(EditingDate)),
    AttemptFunc(User.GetTimeTableDiff(EditingDate))
  ]);

  console.info(TTBase);
  console.info(TTDiff);

  if (TTBase !== false) {
    if (TTBase === null || TTBase["Body"]===null) {
      Timetable = TTDiff;
    } else {
      Timetable = TTBase["TimeTable"];
      // merge
      Global["TTBase"] = TTBase;
      Global["TTDiff"] = TTDiff;
      if (TTDiff["Override"] === true) {
        Timetable = TTDiff;
      } else {
        Timetable = MergeTimetable(TTBase, TTDiff);
      }
    }
  } else {
    Timetable = null;
  }

  if (Timetable === null) {
    Timetable = {};
  }

  // Currently auto-override
  Timetable["Override"] = true;

   // It does not copy things. Just for easy code!
   Classes = Timetable["TimeTable"];

  //TODO: apply some options

  SubjectsConfig = await UserSchool.GetConfig("Subjects", User);

  UpdateEditTimetable();

  
  /* メモを編集 のところ、考える
  document.getElementsByClassName("Class_Block").forEach(function (Element) {
    Element.getElementsByClassName("Class_Note_Input")[0].addEventListener(onchange, function (UpElem) {
      UpElem.
    })
  });
  */
 
}

function MergeTimetable(Base, Diff) {
  if (Diff === null) {
    return Object.create(JSON.parse(JSON.stringify(Base)));
  }
  // RIP Object.create - could not deep-copy things
  var Tp = JSON.parse(JSON.stringify(Base));
  //var Tp = Object.create(Base);

  // Preserve these keys in BASE. Only for root element
  const UnmergeableKeys = [
    "TimeTable"
  ];

  // Merge options.
  Object.keys(Diff).forEach(Key => {
    // Merge only what can be merged.
    if ((Key in UnmergeableKeys)) {
      Tp[Key] = Diff[Key];
    }
  });

  // Merge TIMETABLE thing
  Object.keys(Diff["TimeTable"]).forEach(Key => {
    Tp["TimeTable"][Key] = Diff["TimeTable"][Key];
  });

  return Tp;
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
    if (SearchFrom[i] == Obj.parentElement) {}
  }

  var Target = document.getElementById("Edit_ClassType");
  document.getElementById("Edit_Page_Wrapper").style.display = "flex";

  EditingKey = Obj.parentElement.style.getPropertyValue("--Key");

  ClassEdit_Setup(Obj.parentElement.style.getPropertyValue("--Key"));
}

async function ClassEdit_Setup(EditingClassKey) {
  // EditingClassKey can be replaced with EditingKey. Completely.
  document.getElementById("Edit_ClassLabelDisp").innerText = EditingClassKey;
  // Update "class type"
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

  Edit_Class.Edit_Note.value = Classes[EditingClassKey].Note ?? null;
  Edit_Class.Edit_ClassLabel.value = EditingClassKey ?? null;

  if (Classes[EditingClassKey].Options) {
    var Options = Classes[EditingClassKey].Options;  
    Edit_Class.Edit_Important.checked = Options["Important"];
  } else {
    // initial
  }
}

// BLOCKED.
async function Edit_Upload(TimetableObj) {
  DeployLoadAnim("UPLOADING", "適用しています...");

  var Info = await APIReq(User, {
    "Action": "SET_TIMETABLE",
    "GroupID": User.Profile.Group.ID,
    "Date": SqlizeDate(EditingDate),
    "Body": TimetableObj
  });

  DestructLoadAnim();
  if (Info["Result"]) {
    alert("更新しました。");
  } else {
    alert("更新に失敗しました。\n\n" + Info["ReasonCode"] + "," + Info["ReasonText"]);
  }
}

function Edit_Apply() {
  // TODO: if KEY duplicates, that's not approved
  var IsTimetableUpdated = false;
  var NewClassData = Classes[EditingKey]; // Copy that

  /*
  document.getElementById("Edit_ClassType").childNodes.forEach(function (Candidate) {
    if (Candidate.selected) {
      if (Classes[EditingKey].ID != Candidate.value) {
        IsTimetableUpdated = true;
      }
      NewClassData["ID"] = Candidate.value;
    }
  });
  */
  
  if (Classes[EditingKey]["ID"] != Edit_Class.Class_Type.value) {
    IsTimetableUpdated = true;
  }
  NewClassData["ID"] = Edit_Class.Class_Type.value;

  var NoteText = Edit_Class.Edit_Note.value;
  if (Classes[EditingKey].Note != NoteText) {
    IsTimetableUpdated = true;
  }
  NewClassData.Note = NoteText;

  // O P T I O N S
  if (!(Classes[EditingKey].Options)) {
    Classes[EditingKey].Options = {}
  };

  var IsImportant = Edit_Class.Edit_Important.checked
  if (Classes[EditingKey].Options.Important != IsImportant) {
    IsTimetableUpdated = true;
  }
  NewClassData.Options.Important = IsImportant;


  // Finally do
  var NewKey = Edit_Class.Edit_ClassLabel.value;
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
    UpdateEditTimetable();
    StartAutoStash();
  }

  Edit_Close();
}

function Edit_DiscardConfirm() {
  // Somehow make this non-dialog.
  Flag = confirm("この編集を取り消しますか？");

  if (Flag === true) {
    Edit_Close();
  } else {
    // Do nothing !!!
  }
}

function Edit_DeleteClassConfirm() {
  Flag = confirm(EditingKey + "時間目 の授業を削除しますか？");

  if (Flag === true) {
    Edit_DeleteClass(EditingKey);
    Edit_Close();
  } else {
    // Do nothing !!!
  }
}

function Edit_CompleteConfirm() {
  // Somehow make this non-dialog.
  Flag = confirm("この時間割を確定してもよろしいですか？");

  if (Flag === true) {
    Edit_Upload(Timetable);
  } else {
    // Do nothing !!!
  }
}

function Edit_Close() {
  document.getElementById("Edit_Page_Wrapper").style.display = "none";
}

function Edit_AddClass() {
  var ClassKey = 0;
  if (Object.keys(Classes).length === 0) {
    ClassKey = 1;
  } else {
    for (ClassKey = 1; true; ClassKey++) {
      if (!Classes[ClassKey]) {
        break;
      } else {
        if (Classes[ClassKey] === null) {
          break;
        }
      }
    }
  }

  Classes[ClassKey] = {
    "ID": "Special"
  };

  // Clear
  
  UpdateEditTimetable();

  StartAutoStash();
}

function Edit_DeleteClass(ClassKey) {
  Classes[ClassKey] = null;
  UpdateEditTimetable();

  StartAutoStash();
}

// Not necessarily async
async function Edit_ApplyStashConfirm() {
  // Somehow make this non-dialog.
  Flag = confirm("一時保存した内容を読み込みますか？");

  if (Flag === true) {
    DeployLoadAnim("LOADING", "一時保存した内容を読み込んでいます...");
    await Edit_LoadStash();
    UpdateEditTimetable();
    DestructLoadAnim();
  } else {
    // Do nothing !!!
  }
}

async function Edit_LoadStash() {
  var Local = LoadLocalStash();
  if (Local !== null) {
    Timetable = JSON.parse(Local);
    Classes = Timetable["TimeTable"];
    // Well we may need something to update status text.
    document.getElementById("Update_Status").innerText = "一時保存した内容を読み込みました";
    setTimeout(() => {
      document.getElementById("Update_Status").innerText = "変更があります。確定 を押すと時間割に反映します";
    }, 5000);
    return true;
  } else {
    var UploadedStash = await DownloadStash(EditingDate);
    if (UploadedStash !== null) {
      Timetable = JSON.parse(UploadedStash["Body"]);
      EditingRevision = UploadedStash["Revision"];
      Classes = Timetable["TimeTable"];
      document.getElementById("Update_Status").innerText = "アップロードした内容を読み込みました";
      setTimeout(() => {
        document.getElementById("Update_Status").innerText = "変更があります。確定 を押すと時間割に反映します";
      }, 5000);
      return true;
    } else {
      document.getElementById("Update_Status").innerText = "一時保存した内容が見つかりません";
      setTimeout(() => {
        document.getElementById("Update_Status").innerText = "";
      }, 5000);
      return null;
    }
  }
}

function LoadLocalStash() {
  var LocalStash = localStorage.getItem("Timetable_Stash");
  if (LocalStash !== null) {
    return LocalStash;
  } else {
    return null;
  }
}

async function DownloadStash(TargetDate) {
  var Info = await APIReq(User, {
    "Action": "GET_EDIT_STASH",
    "GroupID": User.Profile.Group.ID,
    "Date": SqlizeDate(TargetDate)
  });

  var Data = JSON.parse(Info.Content);
  if (Data["Result"]) {
    if (Data["Revision"] === -1) {
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
  var StoreData = JSON.stringify(Timetable);
  localStorage.setItem("Timetable_Stash", StoreData);

  var Dt = new Date().getTime();
  if (SaveTimer) {
    clearTimeout(SaveTimer);
    SaveTimer = null;
  }
  if (Dt > LastSaveTime + SavePeriod) {
    LastSaveTime = Dt;
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
  var Info = await APIReq(User, {
    "Action": "SET_EDIT_STASH",
    "GroupID": User.Profile.Group.ID,
    "Date": SqlizeDate(EditingDate),
    "Body": JSON.stringify(Timetable)
  });
  if (Info["Result"]) {
    document.getElementById("Update_Status").innerText = "アップロードして一時保存しました";
    return true;
  } else {
    console.warn(Info);
    document.getElementById("Update_Status").innerText = "アップロードできませんでした";
    return false;
  }

  setTimeout(() => {
    document.getElementById("Update_Status").innerText = "変更があります。確定 を押すと時間割に反映します";
  }, 5000);
}

function UpdateEditTimetable() {
  // Redefine "Classes"
  Classes = Timetable["TimeTable"];
  ApplyDateStrings(EditingDate);

  //Just an imitation of SQLizeDate, but what the he-!?
  document.Timetable_Options.Date.value = "" +EditingDate.getFullYear() + "-" + ("00"+(EditingDate.getMonth() + 1).toString()).slice(-2) + "-"+ ("00"+(EditingDate.getDate()).toString()).slice(-2);

  UpdateClasses(Classes, SubjectsConfig, document.getElementById("Table_Body"), document.getElementById("Class_Base"));
}


var UserID = GetCookie("UserID");

User = new User(UserID);
UserSchool = null;
UserGroup = null;

SubjectsConfig = null;
EditingKey = null;
EditingDate = new Date();
EditingRevision = null;

LastSaveTime = 0;
SaveTimer = null;
const SavePeriod = 30000;

Global = {};
Classes = {};

InitPage(User);