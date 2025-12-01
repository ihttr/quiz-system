<?php
require_once '../../src/config.php';
require_once '../../src/auth.php';
redirectIfNotAdmin();

// Pagination
$page = $_GET['page'] ?? 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filters
$quizFilter = $_GET['quiz_id'] ?? '';
$userFilter = $_GET['user_id'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';

// Build WHERE clause for filters
$whereConditions = [];
$params = [];

if (!empty($quizFilter)) {
    $whereConditions[] = "qa.quiz_id = ?";
    $params[] = $quizFilter;
}

if (!empty($userFilter)) {
    $whereConditions[] = "qa.user_id = ?";
    $params[] = $userFilter;
}

if (!empty($dateFrom)) {
    $whereConditions[] = "DATE(qa.completed_at) >= ?";
    $params[] = $dateFrom;
}

if (!empty($dateTo)) {
    $whereConditions[] = "DATE(qa.completed_at) <= ?";
    $params[] = $dateTo;
}

$whereClause = $whereConditions ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get total count for pagination
$countSql = "SELECT COUNT(*) FROM quiz_attempts qa $whereClause";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$totalAttempts = $countStmt->fetchColumn();
$totalPages = ceil($totalAttempts / $perPage);

// SIMPLE FIX: Build SQL with LIMIT and OFFSET as integers directly in the query
$sql = "
    SELECT 
        qa.*,
        u.username,
        u.email,
        q.title as quiz_title,
        ROUND((qa.score / qa.total_questions) * 100, 2) as percentage,
        CASE 
            WHEN (qa.score / qa.total_questions) * 100 >= q.passing_score THEN 'Passed'
            ELSE 'Failed'
        END as result_status
    FROM quiz_attempts qa
    JOIN users u ON qa.user_id = u.id
    JOIN quizzes q ON qa.quiz_id = q.id
    $whereClause
    ORDER BY qa.completed_at DESC
    LIMIT $perPage OFFSET $offset
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attempts = $stmt->fetchAll();

// Get quizzes for filter dropdown
$quizzes = $pdo->query("SELECT id, title FROM quizzes WHERE is_active = TRUE ORDER BY title")->fetchAll();

// Get users for filter dropdown
$users = $pdo->query("SELECT id, username FROM users ORDER BY username")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo getUserTheme(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Grades - Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .grades-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .filters-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .filter-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }
        
        .grades-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card-bg);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .grades-table th,
        .grades-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .grades-table th {
            background: var(--bg-color);
            font-weight: 600;
        }
        
        .status-passed {
            color: var(--success-color);
            font-weight: 600;
        }
        
        .status-failed {
            color: var(--danger-color);
            font-weight: 600;
        }
        
        .percentage-cell {
            font-weight: 600;
        }
        
        .percentage-high {
            color: var(--success-color);
        }
        
        .percentage-medium {
            color: var(--warning-color);
        }
        
        .percentage-low {
            color: var(--danger-color);
        }
        
        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem 0;
        }
        
        .pagination a {
            padding: 0.5rem 1rem;
            background: var(--card-bg);
            border-radius: 4px;
            text-decoration: none;
            color: var(--text-color);
        }
        
        .pagination a.active {
            background: var(--primary-color);
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary-color);
            display: block;
        }
        
        .stat-label {
            color: var(--text-color);
            opacity: 0.8;
        }
        
        .export-btn {
            background: var(--success-color);
            color: white;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="nav-brand">Quiz App - Admin</div>
                <ul class="nav-links">
                    <li><a href="../quiz.php">← Back to Quiz</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="users.php">Users</a></li>
                    <li><a href="quizzes.php">Quizzes</a></li>
                    <li><a href="grades.php" class="active">Grades</a></li>
                    <li>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="toggle_theme" class="theme-toggle">
                                <?php echo (getUserTheme() === 'dark') ? '☀️' : '🌙'; ?>
                            </button>
                        </form>
                    </li>
                    <li><a href="../logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="grades-header">
            <h1>Quiz Grades & Attempts</h1>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <span class="stat-number"><?php echo $totalAttempts; ?></span>
                <span class="stat-label">Total Attempts</span>
            </div>
            <?php
            // Calculate passed attempts
            $passedStmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM quiz_attempts qa
                JOIN quizzes q ON qa.quiz_id = q.id
                WHERE (qa.score / qa.total_questions) * 100 >= q.passing_score
            ");
            $passedStmt->execute();
            $passedAttempts = $passedStmt->fetchColumn();
            ?>
            <div class="stat-card">
                <span class="stat-number"><?php echo $passedAttempts; ?></span>
                <span class="stat-label">Passed Attempts</span>
            </div>
            <div class="stat-card">
                <span class="stat-number"><?php echo $totalAttempts - $passedAttempts; ?></span>
                <span class="stat-label">Failed Attempts</span>
            </div>
            <div class="stat-card">
                <span class="stat-number">
                    <?php echo $totalAttempts > 0 ? round(($passedAttempts / $totalAttempts) * 100, 1) : 0; ?>%
                </span>
                <span class="stat-label">Pass Rate</span>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <h3>Filters</h3>
            <form method="GET" id="filtersForm">
                <div class="filters-grid">
                    <div class="form-group">
                        <label for="quiz_id">Quiz</label>
                        <select id="quiz_id" name="quiz_id">
                            <option value="">All Quizzes</option>
                            <?php foreach ($quizzes as $quiz): ?>
                                <option value="<?php echo $quiz['id']; ?>" <?php echo $quizFilter == $quiz['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($quiz['title']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="user_id">User</label>
                        <select id="user_id" name="user_id">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>" <?php echo $userFilter == $user['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_from">Date From</label>
                        <input type="date" id="date_from" name="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">Date To</label>
                        <input type="date" id="date_to" name="date_to" value="<?php echo htmlspecialchars($dateTo); ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="grades.php" class="btn btn-secondary">Clear Filters</a>
                </div>
            </form>
        </div>

        <!-- Grades Table -->
        <div class="quiz-card">
            <h3>Quiz Attempts (<?php echo $totalAttempts; ?>)</h3>
            
            <?php if ($attempts): ?>
                <div class="table-responsive">
                    <table class="grades-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Quiz</th>
                                <th>Score</th>
                                <th>Percentage</th>
                                <th>Result</th>
                                <th>Time Taken</th>
                                <th>Date Completed</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attempts as $attempt): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($attempt['username']); ?></strong>
                                        <br><small><?php echo htmlspecialchars($attempt['email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($attempt['quiz_title']); ?></td>
                                    <td>
                                        <strong><?php echo $attempt['score']; ?></strong> / <?php echo $attempt['total_questions']; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $percentage = $attempt['percentage'];
                                        $percentageClass = 'percentage-cell ';
                                        if ($percentage >= 80) {
                                            $percentageClass .= 'percentage-high';
                                        } elseif ($percentage >= 60) {
                                            $percentageClass .= 'percentage-medium';
                                        } else {
                                            $percentageClass .= 'percentage-low';
                                        }
                                        ?>
                                        <span class="<?php echo $percentageClass; ?>">
                                            <?php echo $percentage; ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-<?php echo strtolower($attempt['result_status']); ?>">
                                            <?php echo $attempt['result_status']; ?>
                                        </span>
                                    </td>
                                    <td>
     <?php
    // Safely handle missing time_taken values
    $timeTaken = $attempt['time_taken'] ?? 0; // Default to 0 if not set
    $minutes = floor($timeTaken / 60);
    $seconds = $timeTaken % 60;
    echo sprintf('%d:%02d', $minutes, $seconds);
    
    // Show indicator if time wasn't recorded
    if ($timeTaken === 0) {
        echo ' <small style="color: var(--secondary-color);">(not recorded)</small>';
    }
    ?>
</td>
                                    <td>
                                        <?php echo date('M j, Y g:i A', strtotime($attempt['completed_at'])); ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="attempt_details.php?id=<?php echo $attempt['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                                            <a href="../results.php?attempt_id=<?php echo $attempt['id']; ?>" class="btn btn-sm btn-secondary" target="_blank">Results</a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                               class="<?php echo $i == $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="alert info">
                    <p>No quiz attempts found matching your criteria.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-submit form when filters change
        document.getElementById('quiz_id').addEventListener('change', function() {
            document.getElementById('filtersForm').submit();
        });
        
        document.getElementById('user_id').addEventListener('change', function() {
            document.getElementById('filtersForm').submit();
        });
    </script>
</body>
</html>