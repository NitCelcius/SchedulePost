document.getElementById("LoginButton").addEventListener("click", async function () {
  DeployLoadAnim("LOGGING IN", "ログインしています...");

  var Info = await AwaitAjaxy("/bin/sign_in.php", JSON.stringify({
    "Mail": document.login.Mail.value,
    "PassPhrase": document.login.PassPhrase.value
  }), false);

  var Resp = JSON.parse(Info.Content);
  if (Resp["Result"]) {
    console.info("Moving on...");
    var Sp = new URL(window.location.href).searchParams;
    var Dest = Sp.get("auth_callback");
    Sp.delete("auth_callback")
    location.pathname = Dest;
  } else {
    // err
  }
});