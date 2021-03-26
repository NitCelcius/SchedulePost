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
  DispDate = new Date();
  DispDate.setHours(DispDate.getHours() + 8);

  const QueryDate = GetURLQuery("date");
  if (QueryDate) {
    if (!isNaN(new Date(QueryDate).getTime())) {
      DispDate = new Date(QueryDate);
    }
  }

  await LoadSchedule(DispDate);

  document.getElementById("Timetable_DateSwitch").addEventListener("change", function (v) {
    var Dt = new Date(v.target.value);
    if (Dt) {
      LoadSchedule(Dt);
    }
  })

  SetSwipeObserver(document.getElementsByTagName("article")[0], () => Timetable_JumpBy(1), () => Timetable_JumpBy(-1));
}

function SetSwipeObserver(TargetElem, left = undefined, right = undefined) {
  const SwipeRatio = 0.3;
  let firstX, firstY, lastX, lastY;
  const TargetStyle = window.getComputedStyle(TargetElem);
  let MinDist = Math.min(parseFloat(TargetStyle.width), parseFloat(TargetStyle.height)) * SwipeRatio;

  TargetElem.addEventListener("touchstart", (e) => {
    firstX = e.touches[0].pageX;
    firstY = e.touches[0].pageY;
    lastX = e.touches[0].pageX;
    lastY = e.touches[0].pageY;
  });
  TargetElem.addEventListener("touchmove", (e) => {
    lastX = e.changedTouches[0].pageX;
    lastY = e.changedTouches[0].pageY;
  });
  TargetElem.addEventListener("touchend", (e) => {
    if (e.currentTarget != TargetElem) { return; }
    console.debug(firstX + "->" + lastX + "/" + MinDist);
    if (firstX > lastX + MinDist) {
      if (typeof left === "function") {
        left();
      }
    }

    if (firstX < lastX - MinDist) {
      if (typeof left === "function") {
        right();
      }
    }

  })
}


async function LoadSchedule(TargetDate) {
  DeployLoadAnim();
  try {
    [SubjectsConfig, DayData] = await Promise.all([
      async function () { // It does not really look nice though
          await User.UpdateProfile();
          var SchoolID = await User.GetSchoolProfile(); // automatically defined
          UserSchool = new School(SchoolID.ID);
          var Conf = await UserSchool.GetConfig("Subjects", User);
          return Conf;
        }(),
        User.GetTimeTable(TargetDate), // TODO: What if user belongs in 2 or more groups?
    ])

    DispDate = TargetDate; //new Date(DayData["Date"] + " 00:00:00");

    ApplyDateStrings(TargetDate);

    if (DayData["Revision"] === -1) {
      document.getElementById("Undefined_Warn").style.display = ""
    } else {
      document.getElementById("Undefined_Warn").style.display = "none"
    }

    /*
    document.getElementById("Date_Month").innerText = TimetableDate.getMonth() + 1;
    document.getElementById("Date_Day").innerText = TimetableDate.getDate();
    document.getElementById("Date_The_Day").innerText = TimetableDate.toLocaleString(window.navigator.language, {
      weekday: "narrow"
    });
    */

    if (DayData["Note"]) {
      document.getElementById("Daily_Note").innerText = DayData["Note"];
    } else {
      document.getElementById("Daily_Note").innerText = "メモはありません"
    }

    if (!DayData["Holiday"]) {
      UpdateClasses(
        DayData["TimeTable"],
        SubjectsConfig,
        document.getElementById("Table_Body"),
        document.getElementById("Class_Base")
      );
    } else {
      document.getElementById("Table_Body").innerHTML = "";
      HolidaySt = document.createElement("p");
      HolidaySt.id = "HolidayTitle";
      HolidaySt.innerText = "この日は休みです";
      document.getElementById("Table_Body").appendChild(HolidaySt);
    }

    Timetable = DayData;
    DispDate = TargetDate;
    SetURLQuery("date", DateToYMDStr(DispDate));

    document.URL
  } catch (e) {
    console.error(e);
  }
  DestructLoadAnim();
}

function Timetable_JumpBy(days) {
  if (typeof days === "number") {
    LoadSchedule(new Date(DispDate.setDate(DispDate.getDate() + days)));
  } else {
    console.error("Timetable_JumpBy: the argument days (" + days + ") is invalid!");
  }
}

function Timetable_SelectDate() {
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

function Timetable_ShowControl() {
  document.getElementById("Timetable_Control").style.display = "";
  document.getElementById("Table_Date").style.display = "none";
  document.getElementById("Date_AltWrapper").style.display = "none";
}

function Timetable_HideControl() {
  document.getElementById("Timetable_Control").style.display = "none";
  document.getElementById("Table_Date").style.display = "";
  document.getElementById("Date_AltWrapper").style.display = "";
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

User = new UserClass(UserID);
UserSchool = null;
UserGroup = null;

DispDate = null;
Timetable = null;
SubjectsConfig = null;


if (UserID == false) {
  TransferLoginPage();
}

InitPage(User);