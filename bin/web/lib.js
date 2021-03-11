// Set here too.
const API_URL = "/bin/api.php";

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
  }

  async UpdateSessionToken() {
    var Info = await APIReq(User, {
      "Action": "SIGN_IN"
    })
    /*
    var Info = await AwaitAjaxy(API_URL, JSON.stringify({
      "Action": "SIGN_IN"
    }), false);
    */

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

    return Info;
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

async function APIReq(User, Content) {
  var Resp;
  if (!Content) {
    throw new Error("Do not specify null !!!");
  }
  for (var i = 0; i < 3; i++) {
    console.warn(JSON.stringify(Content))
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
        return resp.json();
      } catch (e) {
        console.error(Content);
        console.error(Resp);
        console.error(e);
        console.error("Fatal error occured in API: Server responded with an error.");
        throw new APIError("ERROR_UNKNOWN","Server responded with an error.","Fatal error occured in API: Server responded with an error.");
        return false;
      }
    }).then(async function (Data) {
      if (!Data.Result) {
        var ErrLog = function () {
          LAST_ERROR = Data.ReasonCode;
          console.error("Fatal error occured in API: " + Data.ReasonCode + ": " + Data.ReasonText + " Script will suspend.")
          throw new APIError(Data.ReasonCode, Data.ReasonText, "Fatal error occured in API: " + Data.ReasonCode + ": " + Data.ReasonText + " Script will suspend.");
          return false;
        }
        switch (Data.ReasonCode) {
          case "ACCOUNT_SESSION_TOKEN_EXPIRED":
          case "ACCOUNT_SESSION_TOKEN_INVALID": {
            var Flag = await User.UpdateSessionToken();
            if (!Flag) {
              console.warn("The account session token has expired. Redirecting to the sign-in page.");
              TransferLoginPage();
              break;
            }
            break;
          }
          case "ACCOUNT_LONG_TOKEN_INVALID":
          case "ACCOUNT_LONG_TOKEN_EXPIRED":
          case "ACCOUNT_CREDENTIALS_INVALID": {
            console.warn("The account session token is invalid. Redirecting to the sign-in page.");

            TransferLoginPage();
            break;
          }
          case "SIGNIN_REQUIRED": {
            console.warn("Please sign in.");
            TransferLoginPage();
            break;
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
      } else {
        return Data;
      }
    });
    if (Dt !== false) {
      Resp = Dt;
      break;
    }
  }
  if (Resp) {
    console.info(Resp);
    return Resp;
  } else {
    console.error(Resp);
    console.error(User);
    console.error(Content);
    throw new Error("APIReq failed.");
  }
}

function SqlizeDate(TargetDate) {
  // What the heck, convert!
  return "" + (TargetDate.getDate()) + "-" + (TargetDate.getMonth() + 1) + "-" + TargetDate.getFullYear() + " 00:00:00";
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

function Sidebar_Open() {
  document.getElementsByTagName("nav")[0].style.display = "block";
  document.getElementById("Nav_Overlay").style.display = "block";
}

function Sidebar_Close() {
  document.getElementsByTagName("nav")[0].style.display = "none";
  document.getElementById("Nav_Overlay").style.display = "none";
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
    zIndex = 32;
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
  with (LoadText.style) { }
  LoadGeneText = document.createElement("p");
  LoadGeneText.id = "LoadGeneMessage"
  LoadGeneText.innerText = "システム管理者に連絡してください。ユーザーID: "+GetCookie("UserID");
  with (LoadGeneText.style) {
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
      weekday: "narrow"
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
  // I'm done for.
}

function UpdateClasses(ClassList, SubjectsConfig, TargetNode, BaseNode) {
  TargetNode.innerHTML = "";
  if (ClassList == null || Object.keys(ClassList).length === 0) {
    EmptyDesc = document.createElement("p");
    EmptyDesc.class = "Timetable_Desc";
    EmptyDesc.innerText = "時間割はまだ入力されていません";
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