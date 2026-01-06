<?php
    require("db-config/security.php");

    // Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

$student_id = $_SESSION['user_id'];


$sql = "
    SELECT 
    m.student_id,
    m.teacher_id,
    m.to_teacher_message,
    m.to_student_message,
    m.sent_at,
    s.firstname,
    s.lastname
FROM messages m
INNER JOIN students s ON s.id = m.student_id
INNER JOIN (
    SELECT student_id, MAX(sent_at) AS latest_time
    FROM messages
    WHERE student_id = :student_id_inner
    GROUP BY student_id
) latest 
    ON latest.student_id = m.student_id 
   AND latest.latest_time = m.sent_at
WHERE m.student_id = :student_id_outer
ORDER BY m.sent_at DESC

";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':student_id_inner' => $student_id,
    ':student_id_outer' => $student_id
]);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql2 = "
    SELECT * FROM teachers
";

$stmt = $pdo->prepare($sql2);
$stmt->execute();
$students_without_messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php

      require __DIR__ . '/headers/head.php'; //Included dito outside links and local styles
    ?>
    <style>
        
    </style>
</head>
<body>
    <section>
    <div class="container py-5">

      <div class="row">

        <div class="col-md-12 col-lg-12 col-xl-12 mb-4 mb-md-0">

          <h5 class="font-weight-bold mb-3 text-center text-lg-start">My Immersion Teacher</h5>
          <span class="badge bg-info me-0"><a href="dashboard">🏡 Back</a></span>
          <hr />
          <div class="card">
            <div class="card-body">

              <ul class="list-unstyled mb-0">

                <?php if ($students): ?>
                    <?php foreach ($students as $row): ?>
                        <li class="p-2 border-bottom bg-body-tertiary">
                            <a href="chatbox.php?teacher_id=<?= (int)$row['teacher_id'] ?>" class="d-flex justify-content-between text-decoration-none text-dark">

                                <div class="d-flex flex-row">
                                    <img src="assets/img/user.png"
                                        class="rounded-circle align-self-center me-3 shadow-1-strong"
                                        width="60">

                                    <div class="pt-1">
                                        <p class="fw-bold mb-0">
                                            <?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?>
                                        </p>
                                        <p class="small text-muted mb-0">
                                            <?= htmlspecialchars($row['to_teacher_message'] ?: $row['to_student_message']) ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="pt-1 text-end">
                                    <p class="small text-muted mb-1">
                                        <?= date('M d, h:i A', strtotime($row['sent_at'])) ?>
                                    </p>
                                </div>

                            </a>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="text-center text-muted py-3">
                        This Feature uses End-To-End Encryption keys <br> that exist only on user devices and nowhere else. So no one—not even the Developer—can read your messages.
                    </li>
<?php endif; ?>
                    <hr />
                    <?php foreach ($students_without_messages as $row): ?>
                        <li class="p-2 border-bottom bg-body-tertiary">
                            <a href="chatbox.php?teacher_id=<?= (int)$row['id'] ?>" class="d-flex justify-content-between text-decoration-none text-dark">

                                <div class="d-flex flex-row">
                                    <img src="assets/img/user.png"
                                        class="rounded-circle align-self-center me-3 shadow-1-strong"
                                        width="60">

                                    <div class="pt-1">
                                        <p class="fw-bold mb-0">
                                            <?= htmlspecialchars($row['firstname'] . ' ' . $row['lastname']) ?>
                                        </p>
                                        <p class="small text-muted mb-0">
                                            <i>Click to Start a Conversation.</i>
                                        </p>
                                    </div>
                                </div>

                                <div class="pt-1 text-end">
                                    <p class="small text-muted mb-1">
                                        ...
                                    </p>
                                </div>

                            </a>
                        </li>
                    <?php endforeach; ?>

                </ul>


            </div>
          </div>

        </div>

        

      </div>

    </div>
  </section>
</body>
</html>