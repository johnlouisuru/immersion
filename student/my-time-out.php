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
    <?php
    require __DIR__ . '/headers/head.php'; //Included dito outside links and local styles
    ?>


</head>

<body class="sb-nav-fixed">
    <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">
        <?php
        require __DIR__ . '/bars/topbar.php'; //Topbar yung kasama Profile Icon
        ?>
    </nav>
    <div id="layoutSidenav">
        <div id="layoutSidenav_nav">
            <?php
            require __DIR__ . '/bars/sidebar.php'; //Sidebar yung kasama Logged in Session
            ?>
        </div>
        <div id="layoutSidenav_content">
            <main>
                <div class="container-fluid px-4">
                    <h1 class="mt-4"><?= $_ENV['PAGE_SIDEBAR'] ?></h1>
                    <ol class="breadcrumb mb-4">
                        <li class="breadcrumb-item active"><?= ucwords($filename) ?></li>
                    </ol>
                    <div class="row">

                        <!-- Content Here -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <i class="fas fa-table me-1"></i>
                                All My Time Outs
                            </div>
                            <div class="card-body">
            <?php
                $student_id = $_SESSION['user_id'];

                $sql = "
                    SELECT 
                        CONCAT(barangay, ', ', municipality, ', ', province) AS exact_location,
                        date_created,
                        video_path
                    FROM attendance_logs
                    WHERE student_id = :student_id
                    AND is_login = 2   -- 1 = Time In
                    ORDER BY date_created DESC
                ";

                $stmt = $pdo->prepare($sql);
                $stmt->execute([':student_id' => $student_id]);
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            ?>

                                <table id="datatablesSimple">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Date / Time</th>
                                            <th>Exact Location</th>
                                            <th>Image (Selfie)</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ($logs): ?>
                                            <?php $i = 1;
                                            foreach ($logs as $log): ?>
                                                <tr>
                                                    <td><?= $i++ ?></td>
                                                    <td><?= date('M d, Y h:i A', strtotime($log['date_created'])) ?></td>
                                                    <td><?= htmlspecialchars($log['exact_location']) ?></td>
                                                    <td>
                                                        <?php if (!empty($log['video_path'])): ?>
                                                            <img
                                                                src="<?= htmlspecialchars($log['video_path']) ?>"
                                                                class="img-thumbnail attendance-img"
                                                                style="width: 80px; cursor: pointer;"
                                                                data-img="<?= htmlspecialchars($log['video_path']) ?>"
                                                                alt="Selfie">
                                                        <?php else: ?>
                                                            <span class="text-muted">No Image</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="4" class="text-center text-muted">No attendance records found.</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>

                                </table>
                            </div>
                        </div>

                        <!-- End of Content Here -->
                    </div>
                </div>
            </main>
            <footer class="py-4 bg-light mt-auto">
                <?php
                require __DIR__ . '/footer/footer.php'; //Literal Footer with (c) and Year
                ?>
            </footer>
        </div>
    </div>
    <!-- Footer SCripts -->
    <?php
    require __DIR__ . '/footer/footer-scripts.php'; //Footer JavaSCripts 
    ?>

    <div class="modal fade" id="imageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Attendance Image</h5>
                    <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="modalImage" src="" class="img-fluid rounded">
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.attendance-img').forEach(img => {
                img.addEventListener('click', function() {
                    const src = this.getAttribute('data-img');
                    document.getElementById('modalImage').src = src;
                    new bootstrap.Modal(document.getElementById('imageModal')).show();
                });
            });
        });
    </script>


</body>

</html>