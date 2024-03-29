// Set here too.
const API_URL = "/bin/api.php";
SessionTransaction = [];
const DB_DATA_NAME = "SchedulePost-data";

async function OpenDBByName(Name) {
  return new Promise((Resolve, Reject) => {
    var db = indexedDB.open(Name);
    db.onupgradeneeded = function (ev) {
      const db = ev.target.result;
      ev.target.transaction.onerror = function (e) {
        console.error(e);
      }
      if (db.objectStoreNames.contains(Name)) {
        db.deleteObjectStore(Name);
      }
      switch (Name) {
        case DB_DATA_NAME: {
          var Obs = db.createObjectStore("Cache", {
            keyPath: "Name"
          });
          Obs.createIndex("Value", "Value", { unique: false });
          break;
        }

        default: {
          console.error("The DB with that name is not defined!")
          Reject(false);
        }
      }
    }

    db.onsuccess = function (ev) {
      var ev = ev.target.result;
      Resolve(ev);
    }

    db.onerror = function (ev) {
      console.warn("Could not open IndexedDB:");
      console.warn(ev);
      Reject(false);
    }
  });
}

async function GetFromDB(DBObj, TableName, Key) {
  return new Promise((Resolve, Reject) => {
    var Trans = DBObj.transaction(TableName, "readonly");
    var St = Trans.objectStore(TableName).get(Key);

    St.onsuccess = function (ev) {
      Resolve(ev);
    }

    St.onerror = function (ev) {
      console.warn("An error occurred while reading from DB:")
      console.warn(ev);
      Reject(false);
    }
  });
}

async function PutToDB(DBObj, TableName, KVObj) {
  return new Promise((Resolve, Reject) => {
    var Trans = DBObj.transaction(TableName, "readwrite");
    var Req = Trans.objectStore(TableName).put(KVObj);

    Req.onsuccess = function () {
      Resolve(true);
    }

    Req.onerror = function () {
      Reject(false);
    }
  });
}

// More like local storage but it constantly closes connection
async function PutToCache(Key, Value) {
  db = await OpenDBByName(DB_DATA_NAME);
  if (!db) { return false; }
  res = await PutToDB(db, "Cache", {
    "Name": Key,
    "Value": Value
  });
  if (!db) { return false; }
  db.close();
  return true;
}

async function GetFromCache(Key) {
  db = await OpenDBByName(DB_DATA_NAME);
  if (!db) { return false; }
  res = await GetFromDB(db, "Cache", Key);
  if (!db) { return false; }
  db.close();

  return res.target.result.Value;
}

class UserClass {
  UserID = null;
  // For these properties, if it is false, then it is confirmed to be NULL. Because these need to be fetched from API and can be cached, if API gave the value NULL for a property, then the property is set false - and its getter returns null!
  // Thus,
  //   null = not yet fetched
  //   false = CACHED and it is null.
  //   anything else = itself.
  Profile = {
    Self: {
      ID: false,
      DisplayName: false
    },
    School: new School(),
    Group: {
      ID: false,
      DisplayName: false
    }
  };

  constructor(InUserID) {
    this.UserID = InUserID;
    this.LastSessionUpdateTime = -14254781999;
    this.MIN_SESSION_INTERVAL = 30 * 1000;
  }

  async UpdateSessionToken() {
    var Info;

    if (!SessionTransaction[this.UserID]) {
      SessionTransaction[this.UserID] = APIReq(User, {
        "Action": "SIGN_IN"
      });
      console.debug("Updating session... ");
      setTimeout(() => {
        SessionTransaction[this.UserID] = null;
      }, 30000);
    }

    return new Promise((resolve, reject) => {
      SessionTransaction[this.UserID].then((Data) => {
        if (Data["Result"] === true) {
          console.debug("Update complete!!");
          resolve(true);
        } else {
          console.error("Session update fail!!");
          console.error(Data);
          resolve(false);
        }
      })
    })

    /*
    if (this.SessionTransaction !== null) {
      console.warn("Multiple session token request detected!");
      Info = await this.SessionTransaction;
    } else {
      var tdif = Math.abs(new Date().getTime - this.LastSessionUpdateTime);
      tdif = isNaN(tdif) ? this.MIN_SESSION_INTERVAL + 1 : tdif;
      if (tdif > this.MIN_SESSION_INTERVAL) {
        this.LastSessionUpdateTime = new Date().getTime;
        this.SessionTransaction = APIReq(User, {
          "Action": "SIGN_IN"
        }).then((Info) => {
          Info = this.SessionTransaction;
          this.SessionTransaction = null;
          try {
            if (Info["Result"] === true) {
              return true;
            } else {
              console.error(Info);
              return false;
            }
          } catch (e) {
            console.error(Info);
            console.error(e);
            return false;
          }
        });
      } else {
        console.warn(Math.abs(new Date().getTime - this.LastSessionUpdateTime));
        console.warn("Repeated session token request detected!");
        return true;
      }
    }
    */

    /*
    var Info = await AwaitAjaxy(API_URL, JSON.stringify({
      "Action": "SIGN_IN"
    }), false);
    */


  }

  async GetUserProfile() {
    if (this.Profile.Self.ID === false) {
      await this.UpdateProfile();
    }

    return this.Profile.Self;
  }

  async GetGroupProfile() {
    if (this.Profile.Group.ID === false) {
      await this.UpdateProfile()
    }

    return this.Profile.Group;
  }

  async GetSchoolProfile() {
    if (this.Profile.School.ID === false) {
      await this.UpdateProfile();
    }
    return this.Profile.School;
  }

  async UpdateProfile(Force_Update = false) {
    //var Cached = (localStorage.getItem("User_Profile") != null ? true : false);
    var Cached = false;
    Force_Update = true;
    var Resp = null;
    if (!Force_Update) {
      //var CachedProfile = localStorage.getItem("User_Profile");
      if (CachedProfile != null) {
        //TODO: expire check
        Resp = JSON.parse(CachedProfile);
        if (Resp != false) {
          Cached = true;
        }
      }
    }
    if (!Cached) {
      var Resp = await APIReq(this, {
        "Action": "GET_USER_PROFILE"
      });
    }

    if (Resp["Result"]) {
      // LITERALLY PRIVATE.
      // This weird code may well be removed
      this.Profile.Self.DisplayName = Resp.Profile.User.DisplayName || null;
      this.Profile.School.ID = Resp.Profile.School.ID || null;
      this.Profile.School.DisplayName = Resp.Profile.School.DisplayName || null;
      this.Profile.Group.ID = Resp.Profile.Group.ID || null;
      this.Profile.Group.DisplayName = Resp.Profile.Group.DisplayName || null;
      // TODO: Cache it if NOT fetched from cache
      //localStorage.setItem()

      return true;
    } else {
      return false;
    }
  }

  async GetTimeTable(TargetDate = null, TargetGroupID = null) {
    if (!TargetDate) {
      //TODO: Just fix this. Normalize.
      TargetDate = new Date();
    }
    // mm-dd-YY
    var TargetDateString = SqlizeDate(TargetDate);

    var Dt = {
      "Action": "GET_SCHEDULE",
      "Date": TargetDateString
    };

    if (TargetGroupID !== null) {
      Dt["TargetGroupID"] = TargetGroupID.toString();
    }

    var Info = await APIReq(this, Dt);

    //TODO: error handling
    if (Info === false) {
      throw new Error("There seems to be an error occured while fetching timetable. " + Info.toString());
    }

    return JSON.parse(Info["Body"]);
  }

  async GetTimeTableBase(TargetDate = null) {
    if (!TargetDate) {
      //TODO: Just fix this. Normalize.
      TargetDate = new Date();
    }
    var TargetDateString = SqlizeDate(TargetDate);

    // TODO: Casually using DATE, not day of the week
    var Info = await APIReq(this, {
      "Action": "GET_TIMETABLE_RAW",
      "Date": TargetDateString,
      "Type": "Base"
    });

    try {
      return Info["Body"];
    } catch (e) {
      console.error(Info);
      console.error(e);
      return false;
    }
  }

  async GetTimeTableDiff(TargetDate = null, Revision = null) {
    if (!TargetDate) {
      //TODO: Just fix this. Normalize.
      TargetDate = new Date();
    }
    var TargetDateString = SqlizeDate(TargetDate);

    var Dt = {
      "Action": "GET_TIMETABLE_RAW",
      "Date": TargetDateString,
      "Type": "Diff"
    }

    if (Revision !== null) {
      Dt["Revision"] = parseInt(Revision);
    }

    var Info = await APIReq(this, Dt);
    try {
      return Info["Body"];
    } catch (e) {
      console.error(Info);
      console.error(e);
      return false;
    }
  }
}

class InvalidCredentialsError extends Error {
  constructor(...params) {
    super(...params);
  }
}

class APIError extends Error {
  constructor(ErrCode, ErrMsg, message) {
    super(message);
    this.name = "APIError";
    this.ErrorCode = ErrCode;
    this.ErrorMessage = ErrMsg;
  }

  GetErrorCode() {
    return this.ErrorCode;
  }

  GetErrorMessage() {
    return this.ErrorMessage;
  }
}

class School {
  // May need to capsulize these. But wait for now, I'll get some idea...
  ID = false;
  DisplayName = false;

  constructor(id = null) {
    this.ID = id;
    this.Config = {};
  }

  IsConfigAvailable(Key) {
    return this.Config[Key] === undefined ? false : true;
  }

  async GetConfig(Key, User = null) {
    if (this.Config[Key] === undefined) {
      // Super Lazy.
      if (User === null) {
        return false;
      } else {
        await this.FetchConfig(User, Key);
        return this.Config[Key];
      }
    } else {
      if (this.Config[Key] === null) {
        return null;
      } else {
        return this.Config[Key]
      }
    }
  }

  async FetchConfig(User, Key, TargetSchoolID = null) {
    var Dt = {
      "Action": "GET_SCHOOL_CONFIG",
      "Item": Key
    };
    if (TargetSchoolID !== null) {
      Dt["TargetSchoolID"] = TargetSchoolID.toString();
    }

    var Data = await APIReq(User, Dt);
    this.Config[Key] = Data.Content;
    return true;
  }

  UpdateConfig(Key, Value) {
    // somehow validate
    switch (Key) {
      case "Subjects": {
        try {
          Data = JSON.parse(Key);
          this.Config.Subjects = Data;
          return true;
        } catch (e) {
          console.error(Data);
          console.error(e);
          return false;
        }
      }

    }
    //    this.Config.Key = Value;
  }
}

// Deprecated, and will be using fetch(). Thanks man
/*
function AwaitLoady(URL) {
  return new Promise(function (Resolve, Reject) {
    let Req = new XMLHttpRequest();
    Req.open("GET", URL, true);

    Req.onload = function (LoadData) {
      //console.debug(LoadData.target.responseText);
      if (LoadData.target.status >= 200 && LoadData.target.status < 300) {
        Resolve({
          "status": LoadData.target.status,
          "statusText": LoadData.target.statusText,
          "Content": LoadData.target.response
        });
      } else {
        Reject({
          "status": LoadData.target.status,
          "statusText": LoadData.target.statusText
        })
      }
    };
    Req.onerror = function (LoadData) {
      Reject({
        "status": LoadData.target.status,
        "statusText": LoadData.target.statusText
      });
    }

    Req.send();
  });
}

// Also deprecated, RIP
function AwaitAjaxy(DestURL, Content, Prot = true) {
  return new Promise(function (Resolve, Reject) {
    let Req = new XMLHttpRequest();
    Req.open("POST", DestURL, true);
    Req.setRequestHeader("Content-Type", "application/json");

    Req.onload = function (LoadData) {
      //console.debug(LoadData.target.responseText);
      if (LoadData.target.status >= 200 && LoadData.target.status < 300) {
        Resolve({
          "status": LoadData.target.status,
          "statusText": LoadData.target.statusText,
          "Content": LoadData.target.response
        });
      } else {
        Reject({
          "status": LoadData.target.status,
          "statusText": LoadData.target.statusText
        })
      }
    };
    Req.onerror = function (LoadData) {
      Reject({
        "status": LoadData.target.status,
        "statusText": LoadData.target.statusText
      });
    }

    Req.send(Content);
  });
}
*/

async function APIReq(User, Content) {
  var RespCont;
  if (!Content) {
    throw new Error("Do not specify null !!!");
  }
  for (var i = 0; i < 3; i++) {
    console.debug(JSON.stringify(Content))
    Dt = await fetch(API_URL, {
      method: "post",
      body: JSON.stringify(Content),
      credentials: "same-origin",
      headers: {
        "Accept": "application/json",
        "Content-Type": "application/json; charset=UTF-8"
      }
    }).then(function (resp) {
      try {
        return resp.text();
      } catch (e) {
        console.error(Content);
        console.error(resp);
        console.error(e);
        console.error("Fatal error occured in API: Server responded with an error.");
        throw new APIError("ERROR_UNKNOWN", "Server responded with an error.", "Fatal error occured in API: Server responded with an error.");
        return false;
      }
    }).then(async function (RespRaw) {
      try {
        RespCont = RespRaw;
        var Data = JSON.parse(RespRaw);
      } catch (e) {
        console.error(Content);
        console.error(RespRaw);
        console.error(e);
        console.error("Fatal error occured in API: Server responded with an error.");
        throw new APIError("ERROR_UNKNOWN", "Server responded with an error.", "Fatal error occured in API: Server responded with an error.");
      }
      if (!Data.Result) {
        var ErrLog = function () {
          LAST_ERROR = Data.ReasonCode;
          console.error("Fatal error occured in API: " + Data.ReasonCode + ": " + Data.ReasonText + " Script will suspend.")
          throw new APIError(Data.ReasonCode, Data.ReasonText, "Fatal error occured in API: " + Data.ReasonCode + ": " + Data.ReasonText + " Script will suspend.");
          return false;
        }
        switch (Data.ReasonCode) {
          case "ACCOUNT_SESSION_TOKEN_EXPIRED":
          case "ACCOUNT_SESSION_TOKEN_INVALID":
          case "SIGNIN_REQUIRED": {
            var Flag = await User.UpdateSessionToken();
            if (!Flag) {
              console.warn("The account session token has expired. Redirecting to the sign-in page.");
              TransferLoginPage();
              break;
            } else {}
            break;
          }
          case "ACCOUNT_LONG_TOKEN_INVALID":
          case "ACCOUNT_LONG_TOKEN_EXPIRED":
          case "ACCOUNT_CREDENTIALS_INVALID":
          case "INVALID_CREDENTIALS": {
            console.warn("The account session token is invalid. Redirecting to the sign-in page.");

            TransferLoginPage();
            break;
          }
          case "UNEXPECTED_ARGUMENT": {
            TransferLoginPage();
          }
          case "INPUT_MALFORMED": {
            ErrLog();
            DeployErrorWindow("内部処理でエラーが発生しました (プログラムの不具合の可能性があります)");
            break;
          }
          default: {
            ErrLog();
          }
        }
        return false;
      } else {
        return Data;
      }
    });
    if (Dt !== false) {
      Resp = Dt;
      break;
    }
    await Delay(500);
  }

  if (Resp) {
    return Resp;
  } else {
    console.error(RespCont);
    console.error(User);
    console.error(Content);
    throw new Error("APIReq failed.");
  }
}

function SqlizeDate(TargetDate) {
  // What the heck, convert!
  return "" + (TargetDate.getDate()) + "-" + (TargetDate.getMonth() + 1) + "-" + TargetDate.getFullYear() + " 00:00:00";
}

function DateToYMDStr(TargetDate) {
  return TargetDate.getFullYear() + "-" + (TargetDate.getMonth() + 1) + "-" + TargetDate.getDate();
}

function GetCookie(name) {
  try {
    // Thanks to mozilla ref, this is quite nice
    // But this function lets every single cookie easily readable by user.
    return document.cookie
      .split("; ")
      .find(Piece => Piece.startsWith(name))
      .split("=")[1];
  } catch (err) {
    console.warn(err);
    return false;
  }
}

function SetCookie(Name, Value, ExpiryHour, Options = {
  secure: true,
  samesite: "strict"
}) {
  try {
    var Exp = "";
    if (ExpiryHour) {
      var DateObj = new Date();
      DateObj.setTime(DateObj.getTime() + (ExpiryHour * 3600000));
      Exp = "; expires=" + DateObj.toUTCString();
    }

    var CookieString = Name + "=" + (Value || "") + Exp + "; ";
    for (var Key in Options) {
      CookieString += "; " + Key;
      if (Options[Key] !== true) {
        CookieString += "=" + Options[Key];
      }
    };

    document.cookie = CookieString;
    return true;
  } catch (err) {
    console.warn(err);
    return false;
  }
}

function GetURLQuery(name = null) {
  const Params = new URLSearchParams(location.search.slice(1));

  return Params.get(name);
}

function SetURLQuery(name, value) {
  const Params = new URLSearchParams(location.search.slice(1));

  if (value) {
    Params.set(name, value);
    window.history.replaceState({}, "", location.pathname + "?" + Params);
  } else {
    Params.delete(name);
    if (Params.length > 0) {
      window.history.replaceState({}, "", location.pathname + "?" + Params);
    } else {
      window.history.replaceState({}, "", location.pathname);
    }
  }
}

function Sidebar_Open() {
  const Nav = document.getElementsByTagName("nav")[0]
  const NavLay = document.getElementById("Nav_Overlay");

  Nav.style.display = "block";
  Nav.removeEventListener("animationend", HideIt);
  Nav.style.animation = ".2s InFromLeft";

  NavLay.style.display = "block";
  NavLay.style.animation = ".2s FadeIn";
  NavLay.removeEventListener("animationend", HideIt)
}

function Sidebar_Close() {
  const Nav = document.getElementsByTagName("nav")[0]
  const NavLay = document.getElementById("Nav_Overlay");

  Nav.style.animation = ".2s OutToLeft";
  Nav.addEventListener("animationend", HideIt)
  
  NavLay.style.animation = ".2s FadeOut";
  NavLay.addEventListener("animationend", HideIt);

}

function HideIt(e) {
  e.target.style.display = "none";
}

async function DeployLoadAnim(TitleText = "LOADING", DescText = "読み込んでいます...") {
  OverlayDiv = document.createElement("div");
  OverlayDiv.id = "LoadIndiWrapper";
  with(OverlayDiv.style) {
    display = "flex"
    position = "fixed";
    top = 0;
    width = "100%";
    height = "100%";
    zIndex = 5;
    backgroundColor = "#fffe";
    alignItems = "center";
    flexDirection = "column";
    placeContent = "center";
    animation = "60s ease-out 0s 1 normal none running LoadIn";
  }
  document.getElementsByTagName("Body")[0].appendChild(OverlayDiv);
  LoadTitle = document.createElement("h1");
  LoadTitle.id = "LoadIndiTitle";
  LoadTitle.innerText = TitleText;
  with(LoadTitle.style) {
    justifyContent = "center";
    color = "#666";
    letterSpacing = "0.2em";
    fontSize = "1.5rem";
  }
  LoadText = document.createElement("p");
  LoadText.id = "LoadIndiMessage"
  LoadText.innerText = DescText;
  with(LoadText.style) {}
  LoadAnimator = document.createElement("div");
  LoadAnimator.id = "LoadAnimator";
  with(LoadAnimator.style) {
    position = "fixed";
    width = "1rem";
    height = "1rem";
    zIndex = "20";
    backgroundColor = "black";
    animation = "3.8s ease-in-out 0s infinite normal none running LoadBox";
  }
  with(OverlayDiv) {
    appendChild(LoadTitle);
    appendChild(LoadText);
    appendChild(LoadAnimator);
  }
}

async function DestructLoadAnim() {
  document.getElementById("LoadIndiWrapper").style.animation = "0.15s linear 0s 1 normal none running LoadOut";
  document.getElementById("LoadIndiWrapper").addEventListener("animationend", function () {
    document.getElementById("LoadIndiWrapper").remove();
  })
  // Want some animation, but skip.
}

async function DeployErrorWindow(ErrorText) {
  OverlayDiv = document.createElement("div");
  OverlayDiv.id = "LoadIndiWrapper";
  with(OverlayDiv.style) {
    display = "flex"
    position = "fixed";
    top = 0;
    width = "100%";
    height = "100%";
    zIndex = 128;
    backgroundColor = "#ddde";
    alignItems = "center";
    flexDirection = "column";
    placeContent = "center";
  }
  document.getElementsByTagName("Body")[0].appendChild(OverlayDiv);
  LoadTitle = document.createElement("h1");
  LoadTitle.id = "LoadIndiTitle";
  LoadTitle.innerText = "エラーが発生しました";
  with(LoadTitle.style) {
    justifyContent = "center";
    color = "#000";
    letterSpacing = "0.2em";
    fontSize = "1.5rem";
  }
  LoadText = document.createElement("p");
  LoadText.id = "LoadIndiMessage"
  LoadText.innerText = ErrorText;
  with(LoadText.style) {}
  LoadGeneText = document.createElement("p");
  LoadGeneText.id = "LoadGeneMessage"
  LoadGeneText.innerText = "システム管理者に連絡してください。ユーザーID: " + GetCookie("UserID");
  with(LoadGeneText.style) {
    color = "#666";
    textAlign = "center";
    overflowWrap = "break-word"
  }

  with(OverlayDiv) {
    appendChild(LoadTitle);
    appendChild(LoadText);
    appendChild(LoadGeneText);
  }
}

function TransferLoginPage() {
  // NOTE: Depending on the last-update time, automatically redirect or recommend to redirect.
  // Referer problem occurs here, but ignoring

  OverlayDiv = document.createElement("div");
  with(OverlayDiv.style) {
    display = "flex"
    position = "absolute";
    top = 0;
    left = 0;
    width = "100%";
    height = "100%";
    zIndex = 32;
    backgroundColor = "#fffe";
    alignItems = "center";
    flexDirection = "column";
    placeContent = "center";
  }
  document.getElementsByTagName("Body")[0].appendChild(OverlayDiv);
  LoadTitle = document.createElement("h1");
  LoadTitle.innerText = "REDIRECTING";
  with(LoadTitle.style) {
    justifyContent = "center";
    color = "#666";
    letterSpacing = "0.3em";
    fontSize = "1.5rem";
    margin = 0;
  }
  LoadText = document.createElement("p");
  LoadText.innerText = "ログインページに移動しています...";
  with(LoadText.style) {}
  with(OverlayDiv) {
    appendChild(LoadTitle);
    appendChild(LoadText);
  }
  location.href = "/login.html?auth_callback=" + encodeURIComponent(location.pathname);
}

function GetDateStrings(TargetDate) {
  return {
    Year: TargetDate.getFullYear(),
    Month: (TargetDate.getMonth() + 1),
    DayOfTheMonth: TargetDate.getDate(),
    DayOfTheWeek: TargetDate.toLocaleString(window.navigator.language, {
      weekday: "short"
    })
  };
}

// ULTRA SUPER DELUXE LAZINESS
function ApplyDateStrings(TargetDate, YearElement = document.getElementById("Date_Year"), MonthElement = document.getElementById("Date_Month"), DayOfTheMonthElement = document.getElementById("Date_Day"), DayOfTheWeekElement = document.getElementById("Date_The_Day")) {
  DateStrings = GetDateStrings(TargetDate);
  YearElement.innerText = DateStrings.Year;
  MonthElement.innerText = DateStrings.Month;
  DayOfTheMonthElement.innerText = DateStrings.DayOfTheMonth;
  DayOfTheWeekElement.innerText = DateStrings.DayOfTheWeek;

  var tg = document.getElementById("Head_Title");
  if (tg) {
    var Prefix = "";
    var TodayRaw = new Date();
    var TargetRaw = new Date(TargetDate.toString());
    TodayRaw.setHours(0, 0, 0, 0);
    TargetRaw.setHours(0, 0, 0, 0);
    var PastTodayD = Math.floor(TodayRaw.getTime() / (1000 * 60 * 60 * 24));
    var PastTargetD = Math.floor(TargetRaw.getTime() / (1000 * 60 * 60 * 24));
    var DaysDiff = PastTargetD - PastTodayD;

    switch (DaysDiff) {
      case -1: {
        Prefix = "きのう";
        break;
      }
      case 0: {
        Prefix = "今日";
        break;
      }
      case 1: {
        Prefix = "明日";
        break;
      }
      case 2: {
        Prefix = "あさって";
        break;
      }
      case 3: {
        Prefix = "しあさって";
        break;
      }
      /* It's not popular 
      case 4: {
        Prefix = "やのあさって";
        break;
      }
      */
    }
    if (Prefix !== "") {
      tg.innerText = Prefix + "の" + "時間割";
    } else {
      tg.innerText = "時間割";
    }
  }
  // I'm done for.
}

function UpdateClasses(ClassList, SubjectsConfig, TargetNode, BaseNode) {
  TargetNode.innerHTML = "";
  if (ClassList == null || Object.keys(ClassList).length === 0) {
    EmptyDesc = document.createElement("p");
    EmptyDesc.class = "Timetable_Desc";
    EmptyDesc.innerText = "時間割データがありません";
    EmptyDesc.color = "#444";
    EmptyDesc.textAlign = "center";
    TargetNode.appendChild(EmptyDesc);
  } else {
    Object.keys(ClassList).sort(function (p, q) {
      return p - q;
    }).forEach(function (Key) {
      if (ClassList[Key] === null) {} else {
        Elem = ConstructClassElement(ClassList[Key], SubjectsConfig, BaseNode, Key);
        Elem.style.setProperty("--Key", Key);
        TargetNode.appendChild(Elem);
      }
    });
  }
}

function ConstructClassElement(ClassData, SubjectsConfig, BaseNode, LabelText = null) {
  var BaseCopy = BaseNode.cloneNode(true);

  with(BaseCopy) {
    SubjectColorCode = "eeeeee";
    if (SubjectsConfig[ClassData.ID]) {
      SubjectColorCode = SubjectsConfig[ClassData.ID]["Color"];
    }
    SubjectColorHex = "";
    // Weird thing: converts HEX color code (w/o # in the beginning) to int then multiplies by 0.7 and converts it back to HEX. Calculates emphasizing color.
    for (var i = 0; i <= 5; i += 2) {
      EmpColor = Math.floor(parseInt(SubjectColorCode.substring(i, i + 2), 16) * 0.7);
      SubjectColorHex += ("00" + EmpColor.toString(16)).slice(-2);
    }
    style.setProperty("--subject-color", "#" + SubjectColorCode);
    style.display = "flex";
    style.setProperty("--subject-emphasize-color", "#" + SubjectColorHex);
    // Also delete its id.
    id = "";
  }

  // If there's any options specified, do these
  if (ClassData["Options"]) {
    var Options = ClassData["Options"];
    if (Options["Important"]) {
      BaseCopy.classList.add("important");
    }
    if (Options["DisplayCount"]) {
      LabelText = Options["DisplayCount"]
    }
  }
  BaseCopy.getElementsByTagName("label")[0].textContent = LabelText;
  BaseCopy.getElementsByClassName("Class_Name")[0].textContent = SubjectsConfig[ClassData["ID"]]["DisplayName"];
  BaseCopy.getElementsByClassName("Class_Note")[0].textContent = ClassData["Note"];

  return BaseCopy;
}

async function Delay(duration) {
  return new Promise(
    resolve => setTimeout(resolve, duration)
  );
}

async function AttemptFunc(Tryer) {
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