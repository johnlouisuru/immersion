
<?php 
    require("../db-config/security.php");
     //require('headers/head.php');
     $_SESSION['error'] = '';
  ?>
<?php 
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
  //echo $_SERVER['REQUEST_METHOD'];
  if($_SERVER['REQUEST_METHOD'] === 'POST'){
    if($_SESSION['csrf_token'] != $_POST['csrf_token']){
      $_SESSION['error'] .= 'Invalid Process Request. Internal Error 501';
      header('Location: index.php');
      die();
    }
    $email = clean_email($_POST['email']);
    $password = clean_text($_POST['password']);
    if($email == NULL || $email == '' || strlen($email) <= 7){
      $_SESSION['error'] .= 'Invalid Email';
      header('Location: index.php');
      die();
    }
   
    if($password == NULL || $password == '' || strlen($password) <= 5){
      $_SESSION['error'] = 'Password must be 6 Characters';
      $_SESSION['temp-email-holder'] = $email;
      header('Location: index.php');
      die();
    }
    $sql = "SELECT * FROM admins WHERE email=:email";
    $stmt = authenticate_email($pdo, $sql, $email);
    //echo '<br> Cred: '.$email.' / Pass: '.$password. '<br>';
    if($stmt){
      //var_dump($stmt);
      while($valid_user = $stmt->fetch()){
            if (password_verify($password, $valid_user['password'])){
            // Password correct
            $_SESSION['is_admin'] = 1;
            $_SESSION['user_id'] = $valid_user['id'];
            $_SESSION['fullname'] = $valid_user['fullname'];
            $_SESSION['position'] = $valid_user['position'];
            $_SESSION['email'] = $valid_user['email'];
            echo "Login successful! Welcome, " . htmlspecialchars($valid_user['fullname']) . ".";
            header('Location: ../dashboard');
            $_SESSION['error'] = 'Successful.';
            echo $_SESSION['error'];
            } else {
                $_SESSION['error'] = 'Invalid Email or Passwordx';
                echo $_SESSION['error'];
                header('Location: index.php');
                die();
            }   
        }
    }
    else {
      $_SESSION['error'] = 'Invalid Email or Password';
      header('Location: index.php');
      die();
    }
  }
  else {
    $_SESSION['error'] = 'Invalid HTTP Request!';
      //header('location: log-in');
  }
echo $_SESSION['error'];
    
?>
