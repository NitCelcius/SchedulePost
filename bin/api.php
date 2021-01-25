<?php
// Requirement: PHP 8.0.0 or later
// TODO: PHP envilonment requirement
// WIP.

$GLOBALS["DB_URL"] = getenv("DB_URL");
$GLOBALS["DB_Username"] = getenv("DB_UserName");
$GLOBALS["DB_PassPhrase"] = getenv("DB_PassPhrase");

function ReplaceArgs(string $Basement, array $Args) {
  return str_replace(array_keys($Args), array_values($Args), $Basement);
}

$ProfilePathFormat = "/Data/Profiles/{School_UUID}/{Group_UUID}.json";
$TimeTablePathFormat = "/Data/Schedules/{School_UUID}/{Group_UUID}/{Year}/{Month}/{Day}/{Version}.json";

$Result = array(
  "Head" => array(
    "Result" => false,
    "ReasonCode" => "ERROR_UNKNOWN",
    "ReasonText" => "The API did not respond properly to your request."
    )
  );
  // BASICではよくある、 while(true) -> break. try~catch(exception e)~finally ができるやり方。
  while (true) {
    $Recv = json_decode(file_get_contents("php://input"), true);
    
    if ($Recv === null) {
      $Result["ReasonCode"] = "INPUT_MALFORMED";
      $Result["ReasonText"] = "The provided JSON was malformed so the API could not recognize.";
      break;
    }
    
    //Authenticate here
    //Probs insert this part on request header
    $Auth = new UserAuth($Recv["Auth"]["UserID"], $Recv["Auth"]["Token"]);
    
    switch ($Recv["Action"]) {
      case "SIGN_IN": {
        $User = new UserAuth($Recv["Auth"]["UserID"]);
        if ( $Recv["Auth"]["Token"] !== NULL) {
          $User->SignInFromLongToken($Recv["Auth"]["Token"]);
        } else {
          // Trying NOT to use passphrase in POST.
          $User->SignInFromPassPhrase($Recv["Auth"]["PassPhrase"]);
        }
      }
      
      case "GET_SCHEDULE": {
        $User = new UserAuth($Recv["Auth"]["UserID"]);
        $User->UpdateSessionToken(); 
        if ($User->SignIn()) {
          
        }
        
        break;
      }
    }
    $School_UUID = $Recv["Auth"]["School_UUID"];
    $Group_UUID = $Recv["Auth"]["Group_UUID"];
    
    // This could be request time or use requested data?
    // Should be converted to LOCAL TIMEZONE (of school)
    $TimeObj = new DateTime($Recv["Options"]["Date"]);
    
    // Revision. Must be fetched before, not constant
    $RecentRev = 1;
    /*
    $TimeTablePath = ReplaceArgs($TimeTablePathFormat, array(
      "{School_UUID}" => $School_UUID,
      "{Group_UUID}" => $Group_UUID,
      "{Year}" => $TimeObj->format('Y'),
      "{Month}" => $TimeObj->format('n'),
      "{Day}" => $TimeObj->format('j'),
      "{Version}" => $RecentRev
    ));
    */
    
    break;
  }
  
  class ConnectionException extends Exception {
    private $Message;
    
    public function __construct($Message, $Location = null, $Prev = null) {
      $this->Message = $Message;
      $this->Location = $Location;
      parent::__construct($Message, 0, null);
    }
    
    public function __toString() {
      return "Connection Error. Additional information: ".$this->Message." Location: ".$this->Location;
    }
  }
  
  class DBConnection {
    static function Connect(string $Username = null, string $PassPhrase = null) {
      if ($Username === null && $PassPhrase === null) {
        $Username = $GLOBALS["DB_Username"];
        $PassPhrase = $GLOBALS["DB_PassPhrase"];
      } else if ($Username === null || $PassPhrase === null) {
        throw new BadMethodCallException("The username or passphrase provided is empty.");
      }
      try {
        $Connection = new PDO(sprintf("mysql:host=%s;dbname=%s;charset=utf8", $GLOBALS["DB_URL"], "schedulepost"), $GLOBALS["DB_Username"], $GLOBALS["DB_PassPhrase"], array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET CHARACTER SET 'utf8mb4'"));
        $Connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $Connection;
      } catch (Exception $e) {
        throw new ConnectionException("Could not connect to the database: ".$e->getMessage()." Could not connect to the database using provided credentials.");
        return false;
      }
      return false;
    }
  }
  
  class UserAuth {
    private $UUID;
    private $SessionToken;
    
    function __construct($UserID, $SessionToken = null)  {
      $this->UUID = $UserID;
      $this->SessionToken = $SessionToken;
      if (!($this->Validate())) {
        throw new UnexpectedValueException("User UUID or Token is invalid.");
      }
    }
    
    function SignIn() {
      $Connection = DBConnection::Connect();
      try {
        $PDOstt = $Connection->prepare("select UUID from schedulepost.accounts where UUID = :UUID AND SessionToken = :Token");
        $PDOstt->bindValue(":UUID", $this->UUID);
        $PDOstt->bindValue(":Token", $this->SessionToken);
        $Result = $PDOstt->execute();
        if ($Result === null) {
          return false;
        } else {
          return true;
        }
      } catch (Exception $e) {
        
      }
    }
    
    function Mail2UUID(string $Email) {
    }
    
    function SignInFromPassPhrase(string $PassPhrase) {
      $Connection = DBConnection::Connect();
      try {        
        $PDOstt = $Connection->prepare("select PassHash from schedulepost.accounts where UUID = :UUID");
        if ($PDOstt === false) {
          throw new ConnectionException("Could not connect to the database.","Database: SchedulePost");
        }
        $PDOstt->bindValue(":UUID", $this->UUID);
        $PDOstt->execute();
        $Data = $PDOstt->fetch(PDO::FETCH_ASSOC);
        if (password_verify($PassPhrase, $Data["PassHash"])) {
          $this->UpdateSessionToken();
          return true;
        } else {
          return false;
        }
      } catch (Exception $e) {
        throw new ConnectionException("Could not process connection properly: ".$e->getMessage(),"Internal function");
        return false;
      }
    }
    
    function SignInFromLongToken(string $LongToken) {
      $Connection = DBConnection::Connect();
      try {        
        $PDOstt = $Connection->prepare("select UUID from schedulepost.accounts where UUID = :UUID AND LongToken = :LongToken");
        if ($PDOstt === false) {
          throw new ConnectionException("Could not connect to the database.","Database: SchedulePost");
        }
        $PDOstt->bindValue(":UUID", $this->UUID);
        $PDOstt->bindValue(":LongToken", $LongToken);
        $PDOstt->execute();
        $Data = $PDOstt->fetch(PDO::FETCH_ASSOC);
        if ($Data["UUID"] === $this->UUID) {
          $this->UpdateSessionToken();
          return true;
        } else {
          return false;
        }
      } catch (Exception $e) {
        throw new ConnectionException("Could not process connection properly: ".$e->getMessage(),"Internal function");
        return false;
      }
    }
    
    function UpdateSessionToken() {
      try {
        $Connection = DBConnection::Connect();
        $Updater = $Connection->prepare("Update `accounts` set `LastSigninAt` = :LoginDateTime,`SessionToken` = :SessionToken WHERE UUID = :UUID");
        $Token = bin2hex(openssl_random_pseudo_bytes(32));
        $LoginDateTime = new DateTime("now", new DateTimeZone("UTC"));
        $Updater->bindValue(":LoginDateTime", $LoginDateTime->format("Y-m-d H:i:s"), PDO::PARAM_STR);
        $Updater->bindValue(":SessionToken", $Token);
        $Updater->bindValue(":UUID", $this->UUID, PDO::PARAM_STR);
        $Data = $Updater->execute();
        if ($Data === false) {
          throw new ConnectionException("Database refused to update.", "Database: SchedulePost");
        }
        return true;
      } catch (Exception $e) {
        throw new ConnectionException("Could not update database: ".$e->getMessage(), "Internal function");
        return false;
      }
    }
    
    // 形式上合っているかどうか。$Token どうしようかな...。
    function Validate() {
      if (preg_match("/[0-9a-f]{32}/", $this->UUID)) {
        return true;
      } else {
        return false;
      }
    }
  }
  
  class Fetcher {
    private $Connection;
    private $User;
    
    function __construct(UserAuth $Auth) {
      if ( $Auth->Validate() ) {
        $this->User = $Auth;
      } else {
        throw new UnexpectedValueException("Authentication data is invalid so Fetcher cannot use the provided credentials.");
      }
    }
    
    function Connect() {
      try {
        $this->Connection = DBConnection::Connect();
        //TODO: TOKEN 種類とって where 以下を変更する。
      } catch (Exception $e) {
        var_dump($e);
        exit;
      }
    }
    
    function IsPermitted(UserAuth $User, string $Command) {
      
    }
    
    function GetTokenValidity() {
      $this->Connection->prepare();
    }
    
    function GetSchedule(string $User, string $GroupID, DateTime $Date) {
      
    }
  }
  
  echo "<br>";
  echo json_encode($Result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  
  ?>