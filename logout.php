<?php
  setcookie("UserID", "", time() - 1000);
  setcookie("Session", "", time() - 1000);
  setcookie("LongToken", "", time() - 1000);

  header("Location: https://".$_SERVER['HTTP_HOST']."/", true, 302);
  
  exit();
?>