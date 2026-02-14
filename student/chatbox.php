<?php 
  require("db-config/security.php");

  // Redirect if not logged in
        if (!isLoggedIn()) {
            header('Location: index');
            exit;
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

    <script>
      // $(document).ready(function () {
      //     $('#sendMessageBtn').on('click', function () {

      //         let message = $('#message').val().trim();
      //         let teacher_id = $('#teacher_id').val();

      //         if (message === '') {
      //             alert('Message cannot be empty.');
      //             return;
      //         }

      //         $.ajax({
      //             url: 'send-message-to-teacher.php',
      //             type: 'POST',
      //             dataType: 'json',
      //             data: {
      //                 message: message,
      //                 teacher_id: teacher_id
      //             },
      //             success: function (response) {
      //                 if (response.status === 'success') {
      //                     alert('Message sent successfully!');
      //                     $('#message').val('');
      //                 } else {
      //                     alert(response.message);
      //                 }
      //             },
      //             error: function () {
      //                 alert('Something went wrong. Please try again.');
      //             }
      //         });
      //     });
      // });
    </script>

</head>
<body>
    <section>
  <div class="container py-5">

    <div class="row d-flex justify-content-center">
      <div class="col-md-8 col-lg-6 col-xl-6">

        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center p-3"
            style="border-top: 4px solid #ffa900;">
            <h5 class="mb-0"><?= get_teacher_name($pdo, $_GET['teacher_id']) ?></h5>
            <div class="d-flex flex-row align-items-center">
              <span class="badge bg-info me-0"><a href="chatbox2">🏡 Back</a></span>
              <!-- <i class="fas fa-minus me-3 text-muted fa-xs"></i>
              <i class="fas fa-comments me-3 text-muted fa-xs"></i>
              <i class="fas fa-times text-muted fa-xs"></i> -->
            </div>
          </div>
          <div class="card-body overflow-auto" style="max-height: 500px" id="chat-box">

            <!-- TEACHER MESSAGE -->
            <!-- <div class="d-flex justify-content-between">
              <p class="small mb-1 text-muted">23 Jan 6:10 pm</p>
              <p class="small mb-1">Johny Bullock</p>
            </div>
            <div class="d-flex flex-row justify-content-end">
              <div>
                <p class="small p-2 me-3 mb-3 text-white rounded-3 bg-warning">Dolorum quasi voluptates quas
                  amet in
                  repellendus perspiciatis fugiat
                Dolorum quasi voluptates quas
                  amet in
                  repellendus perspiciatis fugiat
                Dolorum quasi voluptates quas
                  amet in
                  repellendus perspiciatis fugiat
                Dolorum quasi voluptates quas
                  amet in
                  repellendus perspiciatis fugiat
                Dolorum quasi voluptates quas
                  amet in
                  repellendus perspiciatis fugiat
                Dolorum quasi voluptates quas
                  amet in
                  repellendus perspiciatis fugiat</p>
              </div>
              <img src="https://mdbcdn.b-cdn.net/img/Photos/new-templates/bootstrap-chat/ava6-bg.webp"
                alt="avatar 1" style="width: 45px; height: 100%;">
            </div> -->
            <!--END OF TEACHER MESSAGE -->

            <!-- STUDENT MESSAGE -->
            <!-- <div class="d-flex justify-content-between">
              <p class="small mb-1">Timona Siera</p>
              <p class="small mb-1 text-muted">23 Jan 5:37 pm</p>
            </div>
            <div class="d-flex flex-row justify-content-start">
              <img src="https://mdbcdn.b-cdn.net/img/Photos/new-templates/bootstrap-chat/ava5-bg.webp"
                alt="avatar 1" style="width: 45px; height: 100%;">
              <div>
                <p class="small p-2 ms-3 mb-3 rounded-3 bg-body-tertiary">Lorem ipsum dolor
                  sit amet
                  consectetur adipisicing elit similique quae consequatur</p>
              </div>
            </div> -->

            <!--END OF STUDENT MESSAGE -->
            

          </div>
          <div class="card-footer text-muted d-flex justify-content-start align-items-center p-3">
            <div class="input-group mb-0">
                <input type="hidden" value="<?= $_GET['teacher_id'] ?>" id="teacher_id"> <!-- Change this to Teacher ID dynamically -->
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
    let teacher_id = $('#teacher_id').val();
    let student_id = <?php echo $_SESSION['user_id']; ?>;
    let chatBox = $('#chat-box');
    let shouldAutoScroll = true;


    // Function to fetch messages
    function fetchMessages() {
    $.ajax({
        url: 'fetch-messages.php',
        method: 'GET',
        data: { teacher_id: teacher_id },
        dataType: 'json',
        success: function(messages) {
            chatBox.html('');

            messages.forEach(msg => {
                let messageHtml = '';

                if (msg.student_id == student_id && msg.to_teacher_message) {
                    messageHtml += `
                        <div class="d-flex justify-content-between">
                            <p class="small mb-1"></p>
                            <p class="small mb-1 text-muted">${timeAgo(msg.sent_at)} [You]</p>
                        </div>
                        <div class="d-flex flex-row justify-content-end">
                            <div>
                                <p class="small p-2 me-3 mb-3 text-white rounded-3 bg-warning">${msg.to_teacher_message}</p>
                            </div>
                            <img src="https://mdbcdn.b-cdn.net/img/Photos/new-templates/bootstrap-chat/ava6-bg.webp" style="width:45px;">
                        </div>
                    `;
                } else if (msg.teacher_id == teacher_id && msg.to_student_message) {
                    messageHtml += `
                        <div class="d-flex justify-content-between">
                            <p class="small mb-1 text-muted">[${msg.teacher_name}] ${timeAgo(msg.sent_at)}</p>
                        </div>
                        <div class="d-flex flex-row justify-content-start">
                            <img src="assets/img/user.png" style="width:45px; height:45px;">
                            <div>
                                <p class="small p-2 ms-3 mb-3 rounded-3 bg-body-tertiary">${msg.to_student_message}</p>
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
            'send-message-to-teacher.php',
            { message: message, teacher_id: teacher_id },
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