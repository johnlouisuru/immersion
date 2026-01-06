
<?php 
    require("../db-config/security.php");
     //require('headers/head.php');
     $_SESSION['error'] = '';
     $is_registered = 0;
  ?>
  
<?php 


  function generatePassword($length = 6) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return substr(str_shuffle(str_repeat($characters, ceil($length / strlen($characters)))), 0, $length);
  }



  if($_SERVER['REQUEST_METHOD'] === 'POST'){

    if($_SESSION['csrf_token'] != $_POST['csrf_token']){
      $_SESSION['error'] .= 'Invalid Process Request. Internal Error 501';
      echo "csrf";
      header('location: ../register');
      die();
    }
    $email = clean_email($_POST['email']);
    if($email == NULL || $email == '' || strlen($email) <= 7){
      $_SESSION['error'] .= 'Invalid Email';
      header('location: ../register');
      die();
    }
    if($_POST['cid'] == NULL || $_POST['cid'] == '0' || !isset($_POST['cid'])){
      $_SESSION['error'] .= 'Invalid District';
      header('location: ../register');
      die();
    }
    if($_POST['did'] == NULL || $_POST['did'] == '0' || !isset($_POST['did'])){
      $_SESSION['error'] .= 'Invalid Station';
      header('location: ../register');
      die();
    }
    $sql = "SELECT * FROM users WHERE email=:email";
    $stmt = authenticate_email($pdo, $sql, $email);
    //echo '<br> Cred: '.$email.' / Pass: '.$password. '<br>';
    if($stmt->rowCount() == 10){
      $_SESSION['error'] = 'Email already exist.';
      header('Location: ../register');
      die();
    }
    else {
      $password_holder = generatePassword();
      $passwordHash = password_hash($password_holder, PASSWORD_DEFAULT);
      $unhashedPassword = $password_holder;
      $is_active = 1; 
      $station_name_fullname = get_last_port_of_call($pdo, $_POST['did']);
      $sql = "INSERT INTO users (cid,did,email,password,is_active,fullname) 
                          VALUES (:cid, :did, :email, :password, :is_active, :fullname)";
      $params = [
          ':cid' => $_POST['cid'],
          ':did'    => $_POST['did'],
          ':email'    => $email,
          ':password' => $passwordHash,
          ':is_active' => $is_active,
          ':fullname' => $station_name_fullname
      ];
      $is_registered = secure_insert($pdo, $sql, $params);
      if($is_registered != 0){
        $data['message'] = '<p class="alert alert-success">New User : <b>'.$email.'</b> Successfully Added!</p>';
        include('emailing.php');
        $message_holder = 'Hi! '.$email.', Your Password is : <br><b><i>'.$unhashedPassword.'</i></b><br> You may now Login your account using this Email Address. <a href="https://mssc-official.com/e-dvms/api/log_in" target="_blank">Click this Link </a>';
        //emailing($message_holder, $email); 
        $_SESSION['error'] = emailing($message_holder, $email);
        header('Location: ../register');
       die();
      }
      else {
        $_SESSION['error'] = 'Error while inserting Data.';
        header('Location: ../register');
       die();
      }
      
    }
  }
  else {
    $_SESSION['error'] = 'Invalid HTTP Request!';
      header('location: log-in');
  }
echo $_SESSION['error'];
    
?>
