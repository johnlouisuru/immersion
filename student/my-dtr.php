<?php
require("db-config/security.php");

// Redirect if not logged in
if (!isLoggedIn()) {
    header('Location: index');
    exit;
}

$student_id = $_SESSION['user_id'];

// Get student information
$student_query = "
    SELECT s.*, sec.section_name 
    FROM students s
    LEFT JOIN sections sec ON s.section_id = sec.id
    WHERE s.id = ?
";
$stmt = $pdo->prepare($student_query);
$stmt->execute([$student_id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Get date range filter
$filter_type = isset($_GET['filter_type']) ? $_GET['filter_type'] : 'monthly';
$month = isset($_GET['month']) ? $_GET['month'] : date('m');
$year = isset($_GET['year']) ? $_GET['year'] : date('Y');

// Get student's first and last login dates for reference
$date_range_query = "
    SELECT 
        MIN(DATE(date_created)) as first_date,
        MAX(DATE(date_created)) as last_date,
        COUNT(DISTINCT DATE(date_created)) as total_days_all
    FROM attendance_logs 
    WHERE student_id = ?
";
$stmt = $pdo->prepare($date_range_query);
$stmt->execute([$student_id]);
$date_range = $stmt->fetch(PDO::FETCH_ASSOC);

// Build query based on filter type
if ($filter_type == 'all') {
    // Get ALL attendance logs from first login to present
    $attendance_query = "
        SELECT *
        FROM attendance_logs 
        WHERE student_id = ?
        ORDER BY date_created ASC
    ";
    $stmt = $pdo->prepare($attendance_query);
    $stmt->execute([$student_id]);
    $period_label = "All Records (" . ($date_range['first_date'] ? date('M d, Y', strtotime($date_range['first_date'])) : 'N/A') . " - Present)";
} else {
    // Monthly filter
    $attendance_query = "
        SELECT *
        FROM attendance_logs 
        WHERE student_id = ?
        AND MONTH(date_created) = ?
        AND YEAR(date_created) = ?
        ORDER BY date_created ASC
    ";
    $stmt = $pdo->prepare($attendance_query);
    $stmt->execute([$student_id, $month, $year]);
    $period_label = date('F Y', strtotime("$year-$month-01"));
}

$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Process logs to group by date and calculate hours
$daily_records = [];
$total_hours_all = 0;
$first_login_ever = null;
$last_activity_ever = null;

foreach ($logs as $log) {
    $date = date('Y-m-d', strtotime($log['date_created']));
    
    // Track first and last login ever
    if (!$first_login_ever || strtotime($log['date_created']) < $first_login_ever) {
        $first_login_ever = strtotime($log['date_created']);
    }
    if (!$last_activity_ever || strtotime($log['date_created']) > $last_activity_ever) {
        $last_activity_ever = strtotime($log['date_created']);
    }
    
    if (!isset($daily_records[$date])) {
        $daily_records[$date] = [
            'date' => $date,
            'time_in' => null,
            'time_out' => null,
            'time_in_record' => null,
            'time_out_record' => null,
            'hours' => 0,
            'logs' => [],
            'all_logs' => [] // Store all logs for this day
        ];
    }
    
    // Store the log
    $daily_records[$date]['logs'][] = $log;
    $daily_records[$date]['all_logs'][] = [
        'time' => date('h:i A', strtotime($log['date_created'])),
        'type' => $log['is_login'] == 1 ? 'IN' : 'OUT',
        'id' => $log['id']
    ];
    
    // Set time in (first login of the day)
    if ($log['is_login'] == 1 && (!$daily_records[$date]['time_in'] || strtotime($log['date_created']) < $daily_records[$date]['time_in'])) {
        $daily_records[$date]['time_in'] = strtotime($log['date_created']);
        $daily_records[$date]['time_in_record'] = $log;
    }
    
    // Set time out (last logout of the day)
    if ($log['is_login'] == 2) {
        if (!$daily_records[$date]['time_out'] || strtotime($log['date_created']) > $daily_records[$date]['time_out']) {
            $daily_records[$date]['time_out'] = strtotime($log['date_created']);
            $daily_records[$date]['time_out_record'] = $log;
        }
    }
}

// Calculate hours for each day
foreach ($daily_records as $date => &$record) {
    if ($record['time_in'] && $record['time_out']) {
        // Calculate hours between time in and time out
        $seconds = $record['time_out'] - $record['time_in'];
        $record['hours'] = round($seconds / 3600, 2); // Convert to hours with 2 decimals
        $total_hours_all += $record['hours'];
    } elseif ($record['time_in'] && !$record['time_out']) {
        // If no time out, calculate until end of day or current time
        $end_of_day = strtotime($date . ' 23:59:59');
        $now = time();
        $end_time = min($end_of_day, $now);
        $seconds = $end_time - $record['time_in'];
        $record['hours'] = round($seconds / 3600, 2);
        $record['remarks'] = 'No time out recorded';
        $total_hours_all += $record['hours'];
    }
    
    // Sort all_logs by time
    usort($record['all_logs'], function($a, $b) {
        return strtotime($a['time']) - strtotime($b['time']);
    });
}

// Get statistics based on filter
if ($filter_type == 'all') {
    $stats_query = "
        SELECT 
            COUNT(DISTINCT DATE(date_created)) as total_days,
            COUNT(CASE WHEN is_login = 1 THEN 1 END) as total_logins,
            COUNT(CASE WHEN is_login = 2 THEN 1 END) as total_logouts,
            MIN(date_created) as first_login,
            MAX(date_created) as last_activity
        FROM attendance_logs 
        WHERE student_id = ?
    ";
    $stmt = $pdo->prepare($stats_query);
    $stmt->execute([$student_id]);
} else {
    $stats_query = "
        SELECT 
            COUNT(DISTINCT DATE(date_created)) as total_days,
            COUNT(CASE WHEN is_login = 1 THEN 1 END) as total_logins,
            COUNT(CASE WHEN is_login = 2 THEN 1 END) as total_logouts,
            MIN(date_created) as first_login,
            MAX(date_created) as last_activity
        FROM attendance_logs 
        WHERE student_id = ?
        AND MONTH(date_created) = ?
        AND YEAR(date_created) = ?
    ";
    $stmt = $pdo->prepare($stats_query);
    $stmt->execute([$student_id, $month, $year]);
}
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate average hours per day
$avg_hours_per_day = count($daily_records) > 0 ? round($total_hours_all / count($daily_records), 2) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daily Time Record - <?= htmlspecialchars($student['firstname']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background: #f8fafc;
            min-height: 100vh;
            padding: 30px 20px;
        }
        
        .dtr-container {
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .main-card {
            background: white;
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border: none;
        }
        
        .student-info h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .student-info p {
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.9;
        }
        
        .filter-section {
            padding: 20px 30px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .filter-type-btn {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 30px;
            padding: 8px 20px;
            font-weight: 500;
            color: #64748b;
            transition: all 0.2s;
            cursor: pointer;
        }
        
        .filter-type-btn.active {
            background: #667eea;
            border-color: #667eea;
            color: white;
        }
        
        .filter-type-btn:hover {
            border-color: #667eea;
        }
        
        .table-container {
            padding: 30px;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: #f1f5f9;
            color: #475569;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px;
            border: none;
        }
        
        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
        }
        
        .table tbody tr:hover {
            background-color: #f8fafc;
        }
        
        .date-badge {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .time-badge {
            background: #f1f5f9;
            color: #334155;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
            display: inline-block;
            margin: 2px;
        }
        
        .time-badge-in {
            background: #d1fae5;
            color: #065f46;
        }
        
        .time-badge-out {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .hours-badge {
            background: #dbeafe;
            color: #1e40af;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .total-hours {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 16px;
        }
        
        .remarks {
            font-size: 12px;
            color: #ef4444;
            font-style: italic;
        }
        
        .month-picker {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 8px 16px;
            font-weight: 500;
            color: #1e293b;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .month-picker:hover {
            border-color: #667eea;
        }
        
        .btn-print {
            background: white;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 12px;
            padding: 8px 20px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-print:hover {
            background: #667eea;
            color: white;
        }
        
        .summary-footer {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 20px 30px;
            border-top: 2px solid #e2e8f0;
        }
        
        .timeline-indicator {
            max-width: 250px;
        }
        
        .all-logins {
            background: #f8fafc;
            border-radius: 8px;
            padding: 5px;
            margin-top: 5px;
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                background: white;
                padding: 0;
            }
            .main-card {
                box-shadow: none;
            }
        }
    </style>
</head>
<body>
    <div class="dtr-container">
        <div class="main-card">
            <!-- Header -->
            <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-3">
                <div class="student-info">
                    <h2><i class="bi bi-clock-history me-2"></i>Daily Time Record</h2>
                    <p class="mb-1"><i class="bi bi-person-circle me-2"></i><?= htmlspecialchars($student['firstname'] . ' ' . $student['lastname']) ?></p>
                    <p><i class="bi bi-building me-2"></i><?= htmlspecialchars($student['section_name'] ?? 'No Section') ?></p>
                </div>
                <div class="text-end">
                    <div class="mb-2">
                        <span class="date-badge">
                            <i class="bi bi-calendar3 me-1"></i>
                            <?= $period_label ?>
                        </span>
                    </div>
                    <button onclick="window.print()" class="btn-print no-print">
                        <i class="bi bi-printer me-2"></i>Print DTR
                    </button>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="stats-grid px-4 pt-4">
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_days'] ?? 0 ?></div>
                    <div class="stat-label">Days Present</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_logins'] ?? 0 ?></div>
                    <div class="stat-label">Total Logins</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $stats['total_logouts'] ?? 0 ?></div>
                    <div class="stat-label">Total Logouts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= round($total_hours_all, 1) ?></div>
                    <div class="stat-label">Total Hours</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= $avg_hours_per_day ?></div>
                    <div class="stat-label">Avg Hours/Day</div>
                </div>
            </div>
            
            <!-- Filter Section -->
            <div class="filter-section no-print">
                <form method="GET" class="d-flex gap-3 align-items-center flex-wrap">
                    <div class="d-flex gap-2">
                        <label class="fw-semibold me-2">View:</label>
                        <button type="submit" name="filter_type" value="monthly" 
                                class="filter-type-btn <?= $filter_type == 'monthly' ? 'active' : '' ?>"
                                onclick="this.form.submit()">
                            <i class="bi bi-calendar-month me-1"></i>Monthly
                        </button>
                        <button type="submit" name="filter_type" value="all" 
                                class="filter-type-btn <?= $filter_type == 'all' ? 'active' : '' ?>"
                                onclick="this.form.submit()">
                            <i class="bi bi-clock-history me-1"></i>Display All
                        </button>
                    </div>
                    
                    <?php if ($filter_type == 'monthly'): ?>
                        <div class="d-flex gap-2 align-items-center ms-4">
                            <select name="month" class="month-picker">
                                <?php for ($i = 1; $i <= 12; $i++): ?>
                                    <option value="<?= sprintf('%02d', $i) ?>" <?= $month == sprintf('%02d', $i) ? 'selected' : '' ?>>
                                        <?= date('F', mktime(0, 0, 0, $i, 1)) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                            
                            <select name="year" class="month-picker">
                                <?php for ($i = date('Y'); $i >= 2024; $i--): ?>
                                    <option value="<?= $i ?>" <?= $year == $i ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                            
                            <button type="submit" class="btn btn-primary" style="background: #667eea; border: none;">
                                <i class="bi bi-filter"></i> Apply
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
                
                <?php if ($filter_type == 'all' && $date_range['first_date']): ?>
                    <div class="mt-2 text-muted small">
                        <i class="bi bi-info-circle me-1"></i>
                        Showing all records from <?= date('F d, Y', strtotime($date_range['first_date'])) ?> 
                        to <?= date('F d, Y', strtotime($date_range['last_date'])) ?> 
                        (<?= $date_range['total_days_all'] ?> total days)
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- DTR Table -->
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Location</th>
                            <th>Hours</th>
                            <th class="no-print">All Logs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($daily_records)): ?>
                            <tr>
                                <td colspan="6" class="text-center py-5">
                                    <i class="bi bi-calendar-x" style="font-size: 48px; color: #cbd5e1;"></i>
                                    <p class="mt-3 text-muted">No attendance records found for this period.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($daily_records as $record): ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold">
                                            <?= date('M d, Y', strtotime($record['date'])) ?>
                                        </span>
                                        <br>
                                        <small class="text-muted"><?= date('l', strtotime($record['date'])) ?></small>
                                    </td>
                                    <td>
                                        <?php if ($record['time_in']): ?>
                                            <span class="time-badge time-badge-in">
                                                <i class="bi bi-box-arrow-in-right me-1"></i>
                                                <?= date('h:i A', $record['time_in']) ?>
                                            </span>
                                            <?php if ($record['time_in_record']): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <!-- ID: <?= $record['time_in_record']['id'] ?> -->
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($record['time_out']): ?>
                                            <span class="time-badge time-badge-out">
                                                <i class="bi bi-box-arrow-right me-1"></i>
                                                <?= date('h:i A', $record['time_out']) ?>
                                            </span>
                                            <?php if ($record['time_out_record']): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <!-- ID: <?= $record['time_out_record']['id'] ?> -->
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($record['time_in_record']): ?>
                                            <small>
                                                <?= htmlspecialchars($record['time_in_record']['barangay'] ?? 'N/A') ?><br>
                                                <?= htmlspecialchars($record['time_in_record']['municipality'] ?? '') ?>
                                            </small>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($record['hours'] > 0): ?>
                                            <span class="hours-badge">
                                                <i class="bi bi-clock me-1"></i>
                                                <?= number_format($record['hours'], 2) ?> hrs
                                            </span>
                                            <?php if (isset($record['remarks'])): ?>
                                                <br>
                                                <small class="remarks"><?= $record['remarks'] ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">0.00 hrs</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="no-print">
                                        <?php if (!empty($record['all_logs'])): ?>
                                            <div class="all-logins">
                                                <?php foreach ($record['all_logs'] as $log): ?>
                                                    <span class="time-badge <?= $log['type'] == 'IN' ? 'time-badge-in' : 'time-badge-out' ?>" 
                                                          title="ID: <?= $log['id'] ?>">
                                                        <?= $log['time'] ?> (<?= $log['type'] ?>)
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Summary Footer -->
            <?php if (!empty($daily_records)): ?>
                <div class="summary-footer d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        <strong>First Login:</strong> 
                        <?= $stats['first_login'] ? date('M d, Y h:i A', strtotime($stats['first_login'])) : 'N/A' ?>
                        <br>
                        <strong>Last Activity:</strong> 
                        <?= $stats['last_activity'] ? date('M d, Y h:i A', strtotime($stats['last_activity'])) : 'N/A' ?>
                    </div>
                    <div class="text-end">
                        <strong>Total Days:</strong> <?= count($daily_records) ?><br>
                        <strong>Total Hours:</strong> <?= number_format($total_hours_all, 2) ?> hours<br>
                        <strong>Average/Day:</strong> <?= $avg_hours_per_day ?> hours
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-submit filter type buttons
        document.querySelectorAll('.filter-type-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const form = this.closest('form');
                const filterType = this.value;
                
                // Create hidden input for filter type
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'filter_type';
                input.value = filterType;
                form.appendChild(input);
                
                form.submit();
            });
        });
    </script>
</body>
</html>