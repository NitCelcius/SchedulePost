async function Delay(duration) {
  return new Promise(
    resolve => setTimeout(resolve, duration)
  );
}

async function LoadCommonNodes() {
  // Load until successful attempt - is this correct, or no?
  var CommonRaw;
  while (true) {
    try {
      CommonRaw = await AwaitLoady("/bin/web/navs.html");

      if (CommonRaw["Content"] != null) {
        break;
      }
    } catch (e) {
      console.warn("Could not load common nodes.");
      await Delay(2000);
      // Do nothing, retry!
    }
  }

  var Target = document.getElementsByTagName("body")[0];

  /*
  Field = document.createElement("div");
  Field.innerHTML = CommonRaw["Content"];
  console.info(Field.childNodes);

  for (var i = 0; i < Field.children.length; i++) {
    console.info(Field.children[i]);
    Target.appendChild(Field.children[i]);
  }
  */
      Field = document.createElement("div");
      Field.innerHTML = CommonRaw["Content"];

      for (var i = 0; i < Field.childNodes.length; i++) {
        switch (Field.childNodes[i].tagName) {
          case "NAV": {
            // NAV needs some space.
            Nav = document.getElementsByTagName("nav")[0];
            Field.childNodes[i].childNodes.forEach(function (Node) {
              cp = Node.cloneNode(true);
              Nav.appendChild(cp);
            })
            break;
          }
          default: {
            cp = Field.childNodes[i].cloneNode(true);
            Target.appendChild(cp);
            break;
          }
        }
      }
}

async function LoadSidebarProfile() {
    // May be waste. Already loaded
    var Prof = await User.FetchPersonalInfo();
    console.info(Prof.Content);

    var Resp = JSON.parse(Prof.Content);
    if (Resp["Result"]) {
      document.getElementById("Group_Label").innerHTML = Resp.Profile.Group.DisplayName;
      document.getElementById("Group_Label").innerHTML = Resp.Profile.School.DisplayName;
    }
}

LoadCommonNodes().then(async function () {
  var UserID = GetCookie("UserID");
  User = new UserClass(UserID);
  User.GetGroupProfile().then(function (GroupProf) {
    if (GroupProf.DisplayName) {
      document.getElementById("Group_Label").innerHTML = GroupProf.DisplayName;
    }
  });
  SchoolProf = await User.GetSchoolProfile();
    console.warn(SchoolProf);
    if (SchoolProf.DisplayName) {
      document.getElementById("School_Label").innerHTML = SchoolProf.DisplayName;
    };

  User.GetSchoolProfile().then(function (SchoolProf) {
  })
});

