document.addEventListener("load", async function () {
  // Dependency: global variables
  var GroupProf, SchoolProf;
  [GroupProf, SchoolProf] = Promise.all(
    await User.GetGroupProfile(),
    await User.GetSchoolProfile()
  );
  document.getElementById("Group_Label").innerHTML = GroupProf.DisplayName;
  document.getElementById("School_Label").innerHTML = SchoolProf.DisplayName;
});