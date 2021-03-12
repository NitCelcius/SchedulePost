function Signup_Check() {
  if (document.Signup.Passphrase.value === document.Signup.Passphrase_Re.value) {
    return true;
  } else {
    alert("パスワードが異なっています")
    return false;
  }
}

function ChkPass(Tg) {
  if (document.Signup.Passphrase.value === document.Signup.Passphrase_Re.value) {
    Tg.setCustomValidity("");
  } else {
    Tg.setCustomValidity("入力値が一致しません");
  }
}