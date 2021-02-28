function UpdateTimeTable(TimeTable, SubjectsConfig) {
  /* RIP global variables
  if (SubjectsConfig === null) {
    // Read from global settings - wait, is it available?
    SubjectsConfig = UserSchool.GetConfig("Subjects", User)
  }
  */
  var TargetTimeTable = document.getElementById("Table_Body");
  var BaseRef = document.getElementById("Class_Base");
  //Should be fetched before, though prepare for sudden re-loading
  console.info(SubjectsConfig);
  console.info(TimeTable);

  Object.keys(TimeTable).sort(function (p, q) {
    return p - q;
  }).forEach(function (Key) {
    ClassData = TimeTable[Key];
    var BaseCopy = BaseRef.cloneNode(true);
    with(BaseCopy) {
      SubjectColorCode = SubjectsConfig[ClassData["ID"]]["Color"];
      SubjectColorHex = "";
      // Weird thing: converts HEX color code (w/o # in the beginning) to int then multiplies by 0.7 and converts it back to HEX. Calculates emphasizing color.
      for (var i = 0; i <= 5; i += 2) {
        EmpColor = Math.floor(parseInt(SubjectsConfig[ClassData["ID"]]["Color"].substring(i, i + 2), 16) * 0.7);
        SubjectColorHex += ("00" + EmpColor.toString(16)).slice(-2);
      }
      style.setProperty("--subject-color", "#" + SubjectColorCode);
      style.display = "block";
      style.setProperty("--subject-emphasize-color", "#" + SubjectColorHex);
      // Also delete its id.
      id = "";
    }

    var CountDisp = Key;
    // If there's any options specified, do these
    if (ClassData["Options"]) {
      var Options = ClassData["Options"];
      if (Options["Important"]) {
        BaseCopy.classList.add("important");
      }
      if (Options["DisplayCount"]) {
        CountDisp = Options["DisplayCount"]
      }
    }
    BaseCopy.getElementsByTagName("label")[0].textContent = CountDisp;
    BaseCopy.getElementsByClassName("Class_Name")[0].textContent = SubjectsConfig[ClassData["ID"]]["DisplayName"];
    BaseCopy.getElementsByClassName("Class_Note")[0].textContent = ClassData["Note"];

    TargetTimeTable.appendChild(BaseCopy);
  })

}

function AwaitAjaxy(URL, Content) {
  return new Promise(function (Resolve, Reject) {
    let Req = new XMLHttpRequest();
    Req.open("POST", URL, true);
    Req.setRequestHeader("Content-Type", "application/json");

    Req.onload = function (LoadData) {
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

async function FetchPersonalInfo(UserClass) {
  Info = await AwaitAjaxy(API_URL, JSON.stringify({
    "Auth": {
      "UserID": UserClass.UserID,
      "SessionToken": UserClass.Credentials.SessionToken
    },
    "Action": "GET_USER_PROFILE"
  }));
  return Info;
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
        DisplayName: null
      },
      School: new School(),
      Group: {
        ID: null,
        DisplayName: null
      }
    };
    this.UserID = InUserID;
    this.Credentials.SessionToken = InSessionToken;
  }

  GetUserID() {
    return this.UserID;
  }

  GetSchoolProfile() {
    if (this.School.ID === null) {
      this.UpdateProfile();
    }
  }

  UpdateProfile() {
    Info = AwaitAjaxy(API_URL, JSON.stringify({
      "Auth": {
        "UserID": this.UserID,
        "SessionToken": this.Credentials.SessionToken
      },
      "Action": "GET_USER_PROFILE"
    }));

    Resp = JSON.parse(Info.Content);
    if (Resp["Result"]) {
      // LITERALLY PRIVATE.
      // This weird code may well be removed
      this.Profile.Self.DisplayName = Resp.Profile.User.DisplayName || false;
      this.Profile.School.ID = Resp.Profile.School || false;
      this.Profile.School.DisplayName = Resp.Profile.School.DisplayName || false;
      this.Profile.Group.ID = Resp.Profile.Group.ID || false;
      this.Profile.Group.DisplayName = Resp.Profile.Group.DisplayName || false;
    } else {
      switch (Resp["ReasonCode"]) {
        case "ACCOUNT_SESSION_TOKEN_EXPIRED":
        case "INVALID_CREDENTIALS": {
          TransferLoginPage();
          break;
        }
      }
    }
    return Info;
  }

  async GetTimeTable(TargetDate = null) {
    if (!TargetDate) {
      TargetDate = new Date("2021-01-25");
    }
    // mm-dd-YY
    var TargetDateString = "" + (TargetDate.getMonth() + 1) + "-" + (TargetDate.getDate()) + "-" + TargetDate.getFullYear() + " 00:00:00";
    TargetDateString = "" + (TargetDate.getDate()) + "-" + (TargetDate.getMonth() + 1) + "-" + TargetDate.getFullYear() + " 00:00:00";

    var Info = AwaitAjaxy(API_URL, JSON.stringify({
      "Auth": {
        "UserID": this.UserID,
        "SessionToken": this.Credentials.SessionToken
      },
      "Action": "GET_SCHEDULE",
      "Date": TargetDateString
    }));

    return Info;
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
  DisplayName = null;

  constructor() {
    this.Config = {};
  }

  IsConfigAvailable(Key) {
    return this.Config[Key] === undefined ? false : true;
  }

  GetConfig(Key, User = null) {
    if (this.Config[Key] === undefined) {
      // Super Lazy.
      if (User === null) {
        return false;
      } else {
        this.FetchConfig(User, Key);
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