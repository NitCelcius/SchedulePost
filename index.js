// Please note that this js requires "lib.js" ( I mean, SchedulePost common library )
var Base_Header = document.getElementsByTagName("header")[0].childNodes;
var InterSectNodes = document.getElementsByClassName("InterSectTarget");
var CurrentInterSect = 0;
var InterSectIDs = new Array;
const InterSectObs = new IntersectionObserver(Scroll_Update, {
  root: null,
  rootMargin: "10px",
  threshold: [0.0, 1.0]
})
const API_URL = "/bin/api.php"

for (var i = 0; i < InterSectNodes.length; i++) {
  InterSectIDs.push(InterSectNodes[i].id);
  InterSectObs.observe(InterSectNodes[i]);
}

async function InitPage(User) {
  try {
    var Prof = await FetchPersonalInfo(User);

    var Resp = JSON.parse(Prof.Content);
    if (Resp["Result"]) {
      document.getElementById("Group_Label").innerHTML = Resp.Profile.Group.DisplayName;
      document.getElementById("Group_Label").innerHTML = Resp.Profile.School.DisplayName;
    } else {
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
    }

    UserSchool = new School();
    var Res = false;
    for (var i = 0; i < 3; i++) {
      Res = await UserSchool.FetchConfig(User, "Subjects");
      if (Res) {
        break;
      } else {
        setTimeout(1000);
      }
      if (i == 2) {
        throw new Error("Could not fetch school information.");
      }
    }

    var Tb = await User.GetTimeTable();

    var DayData = JSON.parse(JSON.parse(Tb.Content).Body);

    UpdateTimeTable(
      DayData["TimeTable"],
      UserSchool.GetConfig("Subjects", User)
    );

  } catch (e) {
    console.error(e);
  }
}

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

function Scroll_Update(Elements, Obj) {
  Elements.forEach(Node => {
    if (Node.isIntersecting === false) return;

    // console.info(Node.intersectionRect);

    var index = InterSectIDs.indexOf(Node.target.id);

    with(Node.intersectionRect) {

      console.info("index = " + index.toString() + " Ratio " + Node.intersectionRatio + " Pos " + top.toString() + "~" + bottom.toString());
      if ((Node.intersectionRatio < 0.1) && (top < 10)) {
        index = InterSectIDs.indexOf(Node.target.id) - 1;
      }

      index = index >= 0 ? index : null;
    }
    /*
    if (Node.intersectionRatio < 0.1) {

      if (index >= 1) {
        Header_Update(Node.target.id);
      } else {
        Header_Update(null);
      }
    }


    if (Node.intersectionRatio > 0.9) {
      Header_Update(Node.target.id);
    }
*/
  });
}

function Header_Update(ElementID) {
  var Header = document.querySelector("header>p");
  switch (ElementID) {
    case "Table_Date":
      Header.innerHTML = "";
      Header.appendChild(document.createTextNode(document.getElementById("Table_Date").textContent));
      break;

    default:
      Header.innerHTML = "";
      Base_Header.forEach(Node => {
        Header.appendChild(Node);
      })
      break;
  }
}

/*
var Scroller_Timer;
Scroll_Update();
document.addEventListener("scroll", Scroller, {passive: true});
*/

/*
function Scroll_Update() {
  with (document.getElementById("Table_Date")) {
    this.Date_Top = offsetTop;
    this.Date_Bottom = offsetTop + offsetHeight;
  }
  
  this.rem = parseFloat(getComputedStyle(document.documentElement).fontSize);
}

function Scroller() {
  clearTimeout(Scroller_Timer);
  Scroller_Timer = setTimeout(function() {
    var ScreenY = window.pageYOffset + 3 * rem;
    var ScreenBottom = window.pageYOffset + window.innerHeight - 3 * rem;
    console.info(ScreenY);
    
    if (ScreenY > Date_Bottom) {
      ChangeHeader(document.createTextNode("hidden"));
      console.info("hidden");
    } else {
      ChangeHeader(null);
      console.info("shown");
    }
    
    
  }, 100);
}
*/

function Sidebar_Open() {
  document.getElementsByTagName("nav")[0].style.display = "block";
  document.getElementById("Nav_Overlay").style.display = "block";
}

function Sidebar_Close() {
  document.getElementsByTagName("nav")[0].style.display = "none";
  document.getElementById("Nav_Overlay").style.display = "none";
}

function TransferLoginPage() {
  // NOTE: Depending on the last-update time, automatically redirect or recommend to redirect.
  // Referer problem occurs here, but ignoring
  console.warn("The account session token has expired. Redirecting to the sign-in page.");

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
  location.href = "/login.html";
}

/*
var Potato = 0;

setInterval(function () {
  document.getElementsByTagName("body")[0].style.backgroundPositionX = Potato.toString() + "%";
  document.getElementsByTagName("body")[0].style.backgroundPositionY = Potato.toString() + "%";
  Potato = Potato + 0.1;
},30);
*/

// Temporary credentials

var UserID = GetCookie("UserID");
var SessionToken = GetCookie("SessionToken");

User = new User(UserID, SessionToken);
UserSchool = null;
UserGroup = null;

if (UserID == null || SessionToken == null) {
  TransferLoginPage();
}

InitPage(User);