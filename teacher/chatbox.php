<?php 
    require("db-config/security.php");

    // Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

  if(isset($_GET['student_id']) && isset($_SESSION['teacher_id'])){
    $studentId = $_GET['student_id'];
    $teacherId = $_SESSION['teacher_id'];
  } else {
    header('location: chatbox2');  
  }
  $student_name_display = get_student_name($pdo, $studentId);
  if($student_name_display == "No Assigned"){
    header('location: chatbox2'); 
  }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php

      require __DIR__ . '/headers/head.php'; //Included dito outside links and local styles
    ?>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="js/fb-time-ago.js"></script>


</head>
<body>
    <section>
  <div class="container py-5">

    <div class="row d-flex justify-content-center">
      <div class="col-md-8 col-lg-6 col-xl-6">

        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center p-3"
            style="border-top: 4px solid #ffa900;">
            <h5 class="mb-0"><?= htmlspecialchars($student_name_display) ?></h5>
            <div class="d-flex flex-row align-items-center">
              <span class="badge bg-info me-0"><a href="chatbox2">🏡 Back</a></span>
              <!-- <i class="fas fa-minus me-3 text-muted fa-xs"></i>
              <i class="fas fa-comments me-3 text-muted fa-xs"></i>
              <i class="fas fa-times text-muted fa-xs"></i> -->
            </div>
          </div>
          <div class="card-body overflow-auto" style="max-height: 500px" id="chat-box">

          <!-- This is where Chats or Messages mag didisplay -->
            

          </div>
          <div class="card-footer text-muted d-flex justify-content-start align-items-center p-3">
            <div class="input-group mb-0">
                <input type="hidden" value="<?= $studentId ?>" id="student_id"> <!-- Change this to Teacher ID dynamically -->
                <textarea type="text" class="form-control" id="message" placeholder="Type message"
                  aria-label="Recipient's username" aria-describedby="button-addon2"></textarea>
                <button data-mdb-button-init data-mdb-ripple-init class="btn btn-warning" type="button" id="sendMessageBtn" style="padding-top: .55rem;">
                  Send
                </button>
            </div>
          </div>
        </div>

      </div>
    </div>

  </div>
</section>

<script>
$(document).ready(function () {
    let teacher_id = <?php echo $_SESSION['teacher_id']; ?>;
    let student_id = <?php echo $studentId; ?>;
    let chatBox = $('#chat-box');
    let shouldAutoScroll = true;


    // Function to fetch messages
    function fetchMessages() {
    $.ajax({
        url: 'fetch-messages.php',
        method: 'GET',
        data: { sid: student_id },
        dataType: 'json',
        success: function(messages) {
            chatBox.html('');

            messages.forEach(msg => {
                let messageHtml = '';

                if (msg.teacher_id == teacher_id && msg.to_student_message) {
                    messageHtml += `
                        <div class="d-flex justify-content-between">
                            <p class="small mb-1"></p>
                            <p class="small mb-1 text-muted">[You] ${timeAgo(msg.sent_at)} </p>
                        </div>
                        <div class="d-flex flex-row justify-content-end">
                            <div>
                                <p class="small p-2 me-3 mb-3 text-white rounded-3 bg-warning">${msg.to_student_message}</p>
                            </div>
                            <img src="https://mdbcdn.b-cdn.net/img/Photos/new-templates/bootstrap-chat/ava6-bg.webp" style="width:45px;">
                        </div>
                    `;
                } else if (msg.student_id == student_id && msg.to_teacher_message) {
                    messageHtml += `
                        <div class="d-flex justify-content-between">
                            <p class="small mb-1 text-muted">[${msg.student_name}] ${timeAgo(msg.sent_at)} </p>
                        </div>
                        <div class="d-flex flex-row justify-content-start">
                            <img src="assets/img/user.png" style="width:45px; height:45px;">
                            <div>
                                <p class="small p-2 ms-3 mb-3 rounded-3 bg-body-tertiary">${msg.to_teacher_message}</p>
                            </div>
                        </div>
                    `;
                }

                chatBox.append(messageHtml);
            });

            // ✅ Scroll ONLY ONCE
            if (shouldAutoScroll) {
                chatBox.scrollTop(chatBox[0].scrollHeight);
                shouldAutoScroll = false;
            }
        }
    });
}


    // Poll messages every 2 seconds
    setInterval(fetchMessages, 10000);
    fetchMessages();

    // Send message function (reusable)
    function sendMessage() {
        let message = $('#message').val().trim();
        if (message === '') return;

        $.post(
            'send-message-to-student.php',
            { message: message, student_id: student_id },
            function (response) {
                if (response.status === 'success') {
                    $('#message').val('');
                    fetchMessages();
                } else {
                    alert('Failed to send message.');
                }
            },
            'json'
        );
    }

    // Button click
    $('#sendMessageBtn').on('click', function () {
        sendMessage();
    });

    // ENTER key support
    $('#message').on('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault(); // Prevent newline
            sendMessage();
        }
    });
});
</script>

</body>
</html>