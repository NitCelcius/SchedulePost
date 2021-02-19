var Base_Header = document.getElementsByTagName("header")[0].childNodes;
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

function FetchPersonalInfo(UserClass, CallbackSucceed, CallbackFailed, CallbackProgress) {
  let Req = new XMLHttpRequest();
  try {
    Req.addEventListener("loadend", CallbackSucceed);
    Req.addEventListener("error", CallbackFailed);
    Req.addEventListener("progress", CallbackProgress);
  } catch (ReferenceError) {
    /* Do nothing */
  }

  Req.open("POST", API_URL, true);
  Req.setRequestHeader("Content-Type", "application/json");

  console.info(UserClass);
  Req.send(JSON.stringify({
    "Auth": {
      "UserID": UserClass.UserID,
      "SessionToken": UserClass.SessionToken
    },
    "Action": "GET_USER_PROFILE"
  }));

  return;
}

class User {
  constructor(InUserID, InSessionToken) {
    this.UserID = InUserID;
    this.SessionToken = InSessionToken;
    this.School = {
      "ID": null,
      "DisplayName": null
    };
    this.GroupID = {
      "ID": null,
      "DIsplayName": null
    };
  }
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



UserID = GetCookie("UserID");
SessionToken = GetCookie("SessionToken");

if (UserID == null || SessionToken == null) {
  TransferLoginPage();
}

Prof = FetchPersonalInfo(new User(UserID, SessionToken),
  function (Req) { // When succeeded
    console.info(Req.target.responseText);
    Resp = JSON.parse(Req.target.responseText);

    if (Resp["Result"]) {
      document.getElementById("Group_Label").innerHTML = Resp.Profile.Group.DisplayName;
      document.getElementById("Group_Label").innerHTML = Resp.Profile.School.DisplayName;
    } else {
      switch (Resp["ReasonCode"]) {
        case "ACCOUNT_SESSION_TOKEN_EXPIRED":
        case "INVALID_CREDENTIALS": {
          TransferLoginPage();
          break;
        }
      }
    }
  },
  function (Err) {
    console.info(Err);
  },
  function (ProgressEvent) {
    console.info(ProgressEvent);
  });