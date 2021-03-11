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
      CommonRaw = await fetch("/bin/web/navs.html").then(response => response.text());

      if (CommonRaw) {
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
      Field.innerHTML = CommonRaw;

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

    if (Prof["Result"]) {
      document.getElementById("Group_Label").innerText = Prof.Profile.Group.DisplayName;
      document.getElementById("Group_Label").innerText = Prof.Profile.School.DisplayName;
    }
}

LoadCommonNodes().then(async function () {
  var UserID = GetCookie("UserID");
  User = new UserClass(UserID);
  User.GetGroupProfile().then(function (GroupProf) {
    if (GroupProf.DisplayName) {
      document.getElementById("Group_Label").innerText = GroupProf.DisplayName;
    }
  });
  SchoolProf = await User.GetSchoolProfile();
    console.info(SchoolProf);
    if (SchoolProf.DisplayName) {
      document.getElementById("School_Label").innerText = SchoolProf.DisplayName;
    };

  User.GetSchoolProfile().then(function (SchoolProf) {
  })
});

