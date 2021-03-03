const API_URL = "/bin/api.php"

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

function AwaitAjaxy(URL, Content) {
  return new Promise(function (Resolve, Reject) {
    let Req = new XMLHttpRequest();
    Req.open("POST", URL, true);
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

function SqlizeDate(TargetDate) {
  // What the heck, convert!
  return "" + (TargetDate.getDate()) + "-" + (TargetDate.getMonth() + 1) + "-" + TargetDate.getFullYear() + " 00:00:00";
}

class User {
  constructor(InUserID, InSessionToken) {
    // Private, I guess.
    this.UserID = null;
    this.Credentials = {
      SessionToken: null,
      LongToken: null
    };
    // For these properties, if it is false, then it is confirmed to be NULL. Because these need to be fetched from API and can be cached, if API gave the value NULL for a property, then the property is set false - and its getter returns null!
    // Thus,
    //   null = not yet fetched
    //   false = CACHED and it is null.
    //   anything else = itself.
    this.Profile = {
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
    this.UserID = InUserID;
    this.Credentials.SessionToken = InSessionToken;
    this.Credentials.LongToken = null;

    this.PersonalRaw = null;
  }

  async FetchPersonalInfo() {
    if (this.PersonalRaw === null) {
      // Might be making 2 requests.
      this.PersonalRaw = false;
      var Info = await AwaitAjaxy(API_URL, JSON.stringify({
        "Auth": {
          "UserID": this.UserID,
          "SessionToken": this.Credentials.SessionToken
        },
        "Action": "GET_USER_PROFILE"
      }));

      this.PersonalRaw = Info;
      return Info;
    } else if (this.PersonalRaw === false) {
      /*      
      while (this.PersonalRaw === false) {
        // Well, there were no way to actually observe var change.
        // TODO: implement callback later
        console.info("Waiting...");
        setTimeout(100);
        break;
      }
      */
      return null;
    }
    return false;
  }

  async UpdateSessionToken(LongToken = null, StoreLongToken = true) {
    if (LongToken === null) {
      LongToken = this.Credentials.LongToken;
    }
    if (!LongToken || !this.UserID) {
      throw new InvalidCredentialsError("The longtoken is not set (Check User class instance or specify longtoken before!)");
    }

    Info = await AwaitAjaxy(API_URL, JSON.stringify({
      "Auth": {
        "UserID": this.UserID,
        "LongToken": LongToken
      },
      "Action": "SIGN_IN"
    }));

    var Resp = JSON.parse(Info["Content"]);
    if (Resp["Result"] === true) {
      if (StoreLongToken) {
        this.Credentials.LongToken = LongToken;
      }
      this.SessionToken = Resp["SessionToken"];
      return true;
    } else {
      return false;
    }
  }


  //Deprecated
  GetUserID() {
    return this.UserID;
  }

  GetUserProfile() {
    if (this.Profile.Self.ID === null) {
      this.UpdateProfile();
    }

    return this.Profile.Self;
  }

  GetGroupProfile() {
    if (this.Profile.Group.ID === null) {
      this.UpdateProfile();
    }

    return this.Profile.Group;
  }

  GetSchoolProfile() {
    if (this.Profile.School.ID === null) {
      this.UpdateProfile();
    }

    return this.Profile.School;
  }

  async UpdateProfile() {
    var Info = await AwaitAjaxy(API_URL, JSON.stringify({
      "Auth": {
        "UserID": this.UserID,
        "SessionToken": this.Credentials.SessionToken
      },
      "Action": "GET_USER_PROFILE"
    }));

    var Resp = JSON.parse(Info.Content);
    if (Resp["Result"]) {
      // LITERALLY PRIVATE.
      // This weird code may well be removed
      this.Profile.Self.DisplayName = Resp.Profile.User.DisplayName || null;
      this.Profile.School.ID = Resp.Profile.School || null;
      this.Profile.School.DisplayName = Resp.Profile.School.DisplayName || null;
      this.Profile.Group.ID = Resp.Profile.Group.ID || null;
      this.Profile.Group.DisplayName = Resp.Profile.Group.DisplayName || null;
    } else {
      switch (Resp["ReasonCode"]) {
        case "ACCOUNT_SESSION_TOKEN_EXPIRED":
        case "INVALID_CREDENTIALS": {
          console.warn("The account session token has expired. Redirecting to the sign-in page.");
          TransferLoginPage();
          break;
        }
      }
    }
    return Resp;
  }

  async GetTimeTable(TargetDate = null) {
    if (!TargetDate) {
      //TODO: Just fix this. Normalize.
      TargetDate = new Date("2021-01-25");
    }
    // mm-dd-YY
    var TargetDateString = SqlizeDate(TargetDate);

    var Info = await AwaitAjaxy(API_URL, JSON.stringify({
      "Auth": {
        "UserID": this.UserID,
        "SessionToken": this.Credentials.SessionToken
      },
      "Action": "GET_SCHEDULE",
      "Date": TargetDateString
    }));

    //TODO: error handling
    if (!(Info["status"] >= 200 && Info["status"] < 300)) {
      console.info(Info);
      throw new Error("There seems to be an error occurred while fetching timetable. " + Info.toString());
    }

    return JSON.parse(Info["Content"]);
  }

  async GetTimeTableBase(TargetDate = null) {
    if (!TargetDate) {
      //TODO: Just fix this. Normalize.
      TargetDate = new Date();
    }
    var TargetDateString = SqlizeDate(TargetDate);

    // TODO: Casually using DATE, not day of the week
    var Info = await AwaitAjaxy(API_URL, JSON.stringify({
      "Auth": {
        "UserID": this.UserID,
        "SessionToken": this.Credentials.SessionToken
      },
      "Action": "GET_TIMETABLE_RAW",
      "Date": TargetDateString,
      "Type": "Base"
    }));

    //TODO: error handling
    if (!(Info["status"] >= 200 && Info["status"] < 300)) {
      throw new Error("There seems to be an error occurred while fetching timetable. " + Info.toString());
    }


    try {
      return JSON.parse(JSON.parse(Info["Content"])["Body"]);
    } catch (e) {
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
      "Auth": {
        "UserID": this.UserID,
        "SessionToken": this.Credentials.SessionToken
      },
      "Action": "GET_TIMETABLE_RAW",
      "Date": TargetDateString,
      "Type": "Diff"
    }

    if (Revision !== null) {
      Dt["Revision"] = parseInt(Revision);
    }

    var Info = await AwaitAjaxy(API_URL, JSON.stringify(Dt));

    //TODO: error handling
    if (!(Info["status"] >= 200 && Info["status"] < 300)) {
      throw new Error("There seems to be an error occurred while fetching timetable. " + stringify(Info));
    }

    try {
      return JSON.parse(JSON.parse(Info["Content"])["Body"]);
    } catch (e) {
      return false;
    }
  }
}

class InvalidCredentialsError extends Error {
  constructor(...params) {
    super(...params);
  }
}

class School {
  // May need to capsulize these. But wait for now, I'll get some idea...
  ID = null;
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

  async FetchConfig(User, Key) {
    var Data = await AwaitAjaxy(API_URL, JSON.stringify({
      "Auth": {
        "UserID": User.UserID,
        "SessionToken": User.Credentials.SessionToken
      },
      "Action": "GET_SCHOOL_CONFIG",
      "Item": Key
    }));
    this.Config[Key] = JSON.parse(Data.Content)["Content"];
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
          return false;
        }
      }

    }
    //    this.Config.Key = Value;
  }
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
    return null;
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

async function DeployLoadAnim() {
  OverlayDiv = document.createElement("div");
  OverlayDiv.id = "LoadIndiWrapper";
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
  LoadTitle.id = "LoadIndiTitle";
  LoadTitle.innerHTML = "LOADING";
  with(LoadTitle.style) {
    justifyContent = "center";
    color = "#666";
    letterSpacing = "0.3em";
    fontSize = "1.5rem";
    margin = 0;
  }
  LoadText = document.createElement("p");
  LoadText.id = "LoadIndiMessage"
  LoadText.innerHTML = "読み込んでいます...";
  with(LoadText.style) {}
  with(OverlayDiv) {
    appendChild(LoadTitle);
    appendChild(LoadText);
  }
}

async function DestructLoadAnim() {
  // Want some animation, but skip.
  document.getElementById("LoadIndiWrapper").remove();
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
  LoadTitle.innerHTML = "REDIRECTING";
  with(LoadTitle.style) {
    justifyContent = "center";
    color = "#666";
    letterSpacing = "0.3em";
    fontSize = "1.5rem";
    margin = 0;
  }
  LoadText = document.createElement("p");
  LoadText.innerHTML = "ログインページに移動しています...";
  with(LoadText.style) {}
  with(OverlayDiv) {
    appendChild(LoadTitle);
    appendChild(LoadText);
  }
  location.href = encodeURI("/login.html?auth_callback=" + location.pathname);
}

function UpdateTimeTable(TimeTable, SubjectsConfig, TargetNode, BaseNode) {
  Object.keys(TimeTable).sort(function (p, q) {
    return p - q;
  }).forEach(function (Key) {
    console.info(TargetNode);
    Elem = ConstructClassElement(TimeTable[Key], SubjectsConfig, BaseNode, Key);
    Elem.style.setProperty("--Key", Key);
    TargetNode.appendChild(Elem);
  })
}

function ConstructClassElement(ClassData, SubjectsConfig, BaseNode, LabelText = null) {
  var BaseCopy = BaseNode.cloneNode(true);

  with (BaseCopy) {
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

  console.info(BaseCopy);
  return BaseCopy;
}