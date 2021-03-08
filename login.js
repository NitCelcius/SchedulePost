document.getElementById("LoginButton").addEventListener("click", async function () {
  // むりやり GET パラメータを追加
  document.login.action = encodeURI(document.login.action + "?" + "auth_callback=" + new URL(window.location.href).searchParams.get("auth_callback"));


  var Info = await AwaitAjaxy("/bin/sign_in.php", JSON.stringify({
    "Mail": document.login.Mail.value,
    "PassPhrase": document.login.PassPhrase.value
  }), false);

  var Resp = JSON.parse(Info.Content);
  if (Resp["Result"]) {
    location.href = new URL(window.location.href).searchParams.get("auth_callback");
  
  } else {
    // err
  }
});