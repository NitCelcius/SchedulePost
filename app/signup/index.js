function Signup_Check() {
  if (document.Signup.Passphrase.value === document.Signup.Passphrase_Re.value) {
    return true;
  } else {
    alert("パスワードが異なっています")
    return false;
  }
}

function ChkBasePass(Tg) {
  if (document.Signup.Passphrase.value.length < 6) {    
    Tg.setCustomValidity("パスワードは 6文字 以上にしてください");
  } else {
    Tg.setCustomValidity("");
  }
}

function ChkPass(Tg) {
  if (document.Signup.Passphrase.value === document.Signup.Passphrase_Re.value) {
    Tg.setCustomValidity("");
  } else {
    Tg.setCustomValidity("入力値が一致しません");
  }
}

function ClearErr() {
  var Tg = document.getElementById("Err")
  Tg.innerText = "";
  Tg.style.display = "none";
}


document.addEventListener("DOMContentLoaded", function () {
  var Sp = new URL(window.location.href).searchParams;
  var Emsg = Sp.get("emsg");
  var EDisp = "不明なエラーが発生しました。";
  switch (Emsg) {
    case "INV_ARGS": {
      EDisp = "システムエラーが発生しました。再読み込みしてやり直してください。";
      break;
    }
      
    case "PASS_CONF": {
      EDisp = "入力した2つのパスワードが一致しません。";
      break;
    }
      
    
    case "INVI_INVA": {
      EDisp = "招待コードが間違っています。期限切れや人数上限に達した可能性があります。";
      break;
    }
      
    case "MAIL_USED": {
      EDisp = "入力したメールアドレス/IDはすでに使われています。";
      break;
    }
      
    case "GROUP_GONE": {
      EDisp = "招待先のグループが見つかりませんでした。招待コードの再発行が必要です。";
      break;
    }
  }

  var ErrTg = document.getElementById("Err");
  if (Emsg) {
  ErrTg.innerText = EDisp;
  ErrTg.style.display = "";
  }
})