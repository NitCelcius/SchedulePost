// Please note that this js requires "lib.js" ( I mean, SchedulePost common library )
var InterSectNodes = document.getElementsByClassName("InterSectTarget");
var CurrentInterSect = 0;
var InterSectIDs = new Array;
const InterSectObs = new IntersectionObserver(Scroll_Update, {
  root: null,
  rootMargin: "10px",
  threshold: [0.0, 1.0]
})

for (var i = 0; i < InterSectNodes.length; i++) {
  InterSectIDs.push(InterSectNodes[i].id);
  InterSectObs.observe(InterSectNodes[i]);
}

async function InitPage(User) {
  DeployLoadAnim();
  try {
    var Prof = await User.UpdateProfile();

    var Resp = Prof;
    if (Resp["Result"]) {
      
    } else {
      // May need to copy these
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

    var SchoolID = await User.GetSchoolProfile();
    UserSchool = new School(SchoolID.ID);
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

    var DayData = JSON.parse(Tb.Body);
    var SubjectsConfig = await UserSchool.GetConfig("Subjects", User);

    var TimetableDate = new Date(DayData["Date"]+" 00:00:00");
    document.getElementById("Date_Month").innerText = TimetableDate.getMonth() + 1;
    document.getElementById("Date_Day").innerText = TimetableDate.getDate();
    document.getElementById("Date_The_Day").innerText = TimetableDate.toLocaleString(window.navigator.language, {
      weekday: "narrow"
    });

    UpdateTimeTable(
      DayData["TimeTable"],
      SubjectsConfig,
      document.getElementById("Table_Body"),
      document.getElementById("Class_Base")
    );
  } catch (e) {
    console.error(e);
  }
  DestructLoadAnim();
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
  var Base_Header = document.getElementsByTagName("header")[0].childNodes;
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