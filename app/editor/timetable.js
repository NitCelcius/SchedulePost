async function InitPage(User) {
  DeployLoadAnim();

  User.UpdateProfile().then(async function (Prof) {
    await PrepareEditor(User);
    DestructLoadAnim();
  });
}

async function LoadSchedule(TargetDate) {
  if (!(TargetDate instanceof Date)) {
    console.error("Non-date was specified as TargetDate!");
    return false;
  }

  EditingDate = TargetDate;

  //Wait, are they necessary?
  //BUG: These returns same things
  var CfgState = null;
  var TTBase = null;
  var TTDiff = null;
  var Cfg = null;
  [CfgState, TTBase, TTDiff, Cfg] = await Promise.all([
    UserSchool.FetchConfig(User, "Subjects"),
    User.GetTimeTableBase(EditingDate),
    User.GetTimeTableDiff(EditingDate),
    UserSchool.GetConfig("Subjects", User)
  ]);

  console.info(TTBase);
  console.info(TTDiff);

  if (Cfg !== false) {
    SubjectsConfig = Cfg;
  }

  if (TTBase !== false) {
    if (TTBase === null) {
      Timetable = TTDiff;
    } else {
      Timetable = TTBase;
      // merge
      Global["TTBase"] = TTBase;
      Global["TTDiff"] = TTDiff;
      if (TTDiff !== null) {
        if (TTDiff["Override"] === true) {
          Timetable = TTDiff;
        } else {
          Timetable = MergeTimetable(TTBase, TTDiff);
        }
      }
    }
  } else {
    Timetable = null;
  }

  if (!Timetable) {
    Timetable = {};
  }

  // Currently auto-override
  Timetable["Override"] = true;

  // It does not copy things. Just for easy code!
  Classes = Timetable["TimeTable"];

  UpdateEditTimetable();
  return true;
}

async function PrepareEditor(User) {
  //TODO: Select groups.
  // And this is really inefficient.
  GroupProf = await User.GetGroupProfile();
  console.info(GroupProf);
  document.getElementById("Timetable_Group").innerText = GroupProf.DisplayName;
  //Timetable_Group

  DownloadStash(EditingDate).then(function (Ds) {
    if (Ds !== false) {
      // TODO: Notify
    } else {
      //if (LAST_ERROR === "INSUFFCIENT_PERMISSION") {
      //  DeployErrorWindow("時間割を編集する権限がありません。");
      //}
    }
  }).catch(function (e) {
    console.error("Eeeeeeee");
    console.error(e)
    if (e.GetErrorCode() === "INSUFFCIENT_PERMISSION") {
      DeployErrorWindow("時間割を編集する権限がありません。");
    }
    console.info(e);
  });

  UserSchool = new School(User.GetSchoolProfile().ID);
  EditingDate = new Date();

  //TODO: apply some options
  await LoadSchedule(EditingDate);

  document.Options.Daily_Note.addEventListener("change", function () {
    Timetable["Note"] = document.Options.Daily_Note.value;
    StartAutoStash();
  })

  document.Options.Date.addEventListener("change", function () {
    var AskF = true;
    if (IsChanged) {
      AskF = confirm("編集している時間割は確定されず、配信もされません。\n編集中の時間割はサーバーに一定期間保存されるため、日付を変更して一時保存した内容を読み込むと編集を再開できます。\n\n編集する日付を変更しますか？");
    }

    if (AskF === true) {
      var Do = async function () {
        DeployLoadAnim("LOADING", "読み込んでいます...");
        await UploadStash();
        await LoadSchedule(new Date(document.Options.Date.value));
        IsChanged = false;
        DestructLoadAnim();
      }
      Do();
    } else {
      UpdateEditTimetable();
    }
  })

  document.getElementById("LoadFromStash").onclick = function () {
    var AskF = true;
    if (IsChanged) {
      AskF = confirm("編集している時間割は確定されず、配信もされません。\n編集中の時間割はサーバーに一定期間保存されるため、日付を変更して一時保存した内容を読み込むと編集を再開できます。\n\n一時保存から読み込みますか？");
    }

    if (AskF === true) {
      var Do = async function () {
        DeployLoadAnim("LOADING", "読み込んでいます...");
        var Flag = await ApplyLocalStash();
        if (Flag !== true) {
          alert("一時保存した内容はありません。");
        }
        await UpdateEditTimetable();
        IsChanged = false;
        CloseIOMenu();
        DestructLoadAnim();
      }
      Do();
    }
  }

  document.getElementById("LoadFromServer").onclick = function () {
    var AskF = true;
    if (IsChanged) {
      AskF = confirm("編集している時間割は確定されず、配信もされません。\n編集中の時間割はサーバーに一定期間保存されるため、日付を変更して一時保存した内容を読み込むと編集を再開できます。\n\nサーバーから読み込みますか？");
    }

    if (AskF === true) {
      var Do = async function () {
        DeployLoadAnim("LOADING", "読み込んでいます...");
        var Flag = await ApplyServerStash();
        if (Flag === false) {
          alert("サーバーに一時保存した時間割はありません。");
        }
        await UpdateEditTimetable();
        IsChanged = false;
        CloseIOMenu();
        DestructLoadAnim();
      }
      Do();
    }
  }

  document.getElementById("ResetToBase").onclick = function () {
    var AskF = false;
    AskF = confirm("編集している時間割を破棄して、いつもの時間割から編集をやり直しますか？\n確定するまで、時間割は適用されません。");
    if (AskF) {
      var Do = async function () {
        DeployLoadAnim();
        var TTBase = await User.GetTimeTableBase(EditingDate);
        if (TTBase !== false) {
          if (TTBase !== null) {
            Timetable = TTBase;
            await UpdateEditTimetable();
            IsChanged = false;
          } else {
            alert("この曜日のいつもの時間割が設定されていません。\n");
          }
        } else {
          alert("いつもの時間割を読み込めませんでした。");
        }
        CloseIOMenu();
        DestructLoadAnim();
      }
      Do();
    }
  }

  document.getElementById("SaveToStash").onclick = function () {
    StartAutoStash();
    CloseIOMenu();
  }

  document.getElementById("SaveToServer").onclick = function () {
    UploadStash();
    CloseIOMenu();

  }

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
    IsChanged = true;
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

function OpenIOMenu() {
  document.getElementById("Edit_LoadFromWrapper").style.display = "flex";
}

function CloseIOMenu() {
  document.getElementById("Edit_LoadFromWrapper").style.display = "none";
}

async function ApplyLocalStash() {
  var Local = LoadLocalStash();
  if (Local !== null) {
    try {
      Timetable = JSON.parse(Local);
      Classes = Timetable["TimeTable"];
      // Well we may need something to update status text.
      document.getElementById("Update_Status").innerText = "一時保存した内容を読み込みました";
      setTimeout(() => {
        document.getElementById("Update_Status").innerText = "変更があります。確定 を押すと時間割に反映します";
      }, 5000);
    } catch (e) {
      console.warn(e);
      return false;
    }
    return true;
  } else {
    return null;
  }
}

async function ApplyServerStash() {
  var UploadedStash = await DownloadStash(EditingDate);
  if (UploadedStash !== null) {
    try {
      Timetable = UploadedStash["Body"];
      EditingRevision = UploadedStash["Revision"];
      Classes = Timetable["TimeTable"];
      document.getElementById("Update_Status").innerText = "アップロードした内容を読み込みました";
      setTimeout(() => {
        document.getElementById("Update_Status").innerText = "変更があります。確定 を押すと時間割に反映します";
      }, 5000);
      return true;
    } catch (e) {
      console.warn(e);
      return false;
    }
  } else {
    document.getElementById("Update_Status").innerText = "一時保存した内容が見つかりません";
    setTimeout(() => {
      document.getElementById("Update_Status").innerText = "";
    }, 5000);
    return null;
  }
}

async function Edit_LoadStash() {
  var Flag = await ApplyLocalStash();
  if (Flag !== true) {
    Flag = await ApplyServerStash();
    if (!Flag) {
      return false;
    }
    return true;
  }
  return true;
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
  if (!(TargetDate instanceof Date)) {
    throw new Error("Specify Date instance.");
  }

  if (!User.Profile.Group.ID) {
    await User.UpdateProfile();
  }

  var Info = await APIReq(User, {
    "Action": "GET_EDIT_STASH",
    "GroupID": User.Profile.Group.ID,
    "Date": SqlizeDate(TargetDate)
  });

  try {
    var Data = JSON.parse(Info["Body"]);
    if (Info["Result"]) {
      if (Data["Revision"] === -1) {
        // It's brand new!
        return null;
      } else {
        // It's well-prepared!
        return {
          "Revision": Info["Revision"],
          "Body": Data
        }
      }
    } else {
      return false;
    }
  } catch (e) {
    console.error("Could not load server stash.");
    console.error(Info);
    console.error(e);
    return false;
  }
}

// Though we need to save THIS to local storage
async function StartAutoStash() {
  IsChanged = true;
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
  if (Classes === undefined) {
    Timetable["TimeTable"] = {};
    Classes = [];
  }
  ApplyDateStrings(EditingDate);

  //Just an imitation of SQLizeDate, but what the he-!?
  document.Options.Date.value = "" + EditingDate.getFullYear() + "-" + ("00" + (EditingDate.getMonth() + 1).toString()).slice(-2) + "-" + ("00" + (EditingDate.getDate()).toString()).slice(-2);
  if (Timetable["Note"]) {
    document.Options.Daily_Note.value = Timetable["Note"];
  }

  UpdateClasses(Classes, SubjectsConfig, document.getElementById("Table_Body"), document.getElementById("Class_Base"));
}


var UserID = GetCookie("UserID");
User = new UserClass(UserID);
UserSchool = null;
UserGroup = null;

SubjectsConfig = null;
EditingKey = null;
EditingDate = new Date();
EditingRevision = null;
IsChanged = false;

LastSaveTime = 0;
SaveTimer = null;
const SavePeriod = 30000;

Global = {};
Classes = {};

InitPage(User);