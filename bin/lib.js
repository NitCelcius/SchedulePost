function AwaitLoady(URL) {
  return new Promise(function (Resolve, Reject) {
    let Req = new XMLHttpRequest();
    Req.open("GET", URL, true);

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

    Req.send();
  });
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
    this.Credentials.LongToken = null;
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
    
    console.info(Info["Content"]);
    
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

function Sidebar_Open() {
  document.getElementsByTagName("nav")[0].style.display = "block";
  document.getElementById("Nav_Overlay").style.display = "block";
}

function Sidebar_Close() {
  document.getElementsByTagName("nav")[0].style.display = "none";
  document.getElementById("Nav_Overlay").style.display = "none";
}