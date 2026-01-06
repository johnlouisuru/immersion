<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require("security.php");
$details = '';
if(isset($_POST['id'])){
    $id = intval($_POST['id']);
    
      $query = "SELECT * FROM cg_station WHERE district_id=:id";
      $params = [
        ':id' => $_POST['id']
      ];
      $stmt = secure_query($pdo, $query, $params);
        if($stmt){
            while($row = $stmt->fetch()){
                $details .= "<option value='$row[id]'>$row[station]</option>";
            }
        }
        else {
        $details = 'No Division Found';

        }
    //$data['message'] = '<p class="alert alert-success">'.$_POST['rank_'].' '.$_POST['fullname_'].' Successfully Added</p>'; 
  
}
else {
	echo 'Error Retrieving Division';
}
echo $details;
?>