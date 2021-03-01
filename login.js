document.getElementById("LoginButton").addEventListener("click", function () {
  // むりやり GET パラメータを追加
  
  document.login.action = encodeURI(document.login.action + "?" + "auth_callback=" + new URL(window.location.href).searchParams.get("auth_callback"));
  document.login.submit();
})