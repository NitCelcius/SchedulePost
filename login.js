document.getElementById("LoginButton").addEventListener("click", async function () {
  DeployLoadAnim("LOGGING IN", "ログインしています...");

  await fetch("/bin/sign_in.php", {
    method: "post",
    body: JSON.stringify({
      "Mail": document.login.Mail.value,
      "PassPhrase": document.login.PassPhrase.value
    }),
    credentials: "same-origin",
    headers: {
      "Accept": "application/json",
      "Content-Type": "application/json; charset=UTF-8"
    }
  }).then((Raw) => {
    return Raw.json();
  }).catch((e) => {
    alert("ログインできません (ID/パスワードを確認してください)\n\n" + e.message);
    console.error(i);
  }).then((Resp) =>{
    try {
      if (Resp["Result"]) {
        console.info("Moving on...");
        var Url = new URL(window.location.href);
        var Dest = Url.searchParams.get("auth_callback");
        Url.pathname = Dest;
        Url.searchParams.delete("auth_callback");

        if (Dest) {
          window.location.href = Url.toString();
        } else {
          location.pathname = "/app/";
        }
      } else {
        // err
      }
    } catch (e) {
      alert("ログインできません (ID/パスワードを確認してください)\n\n" + e.message);
      console.error(i);
    }
  })
});