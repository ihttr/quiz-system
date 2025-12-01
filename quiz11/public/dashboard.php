<?php
require_once '../src/auth.php';
redirectIfNotLoggedIn();
require_once '../src/config.php';

// Get user statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT quiz_id) as quizzes_taken,
        SUM(score) as total_score,
        AVG(score) as avg_score,
        COUNT(*) as total_attempts
    FROM quiz_attempts 
    WHERE user_id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$stats = $stmt->fetch();

// Get recent attempts
$stmt = $pdo->prepare("
    SELECT qa.*, q.title, q.passing_score
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.user_id = ?
    ORDER BY qa.completed_at DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$recentAttempts = $stmt->fetchAll();

// Get user progress
$stmt = $pdo->prepare("
    SELECT up.*, q.title, q.passing_score
    FROM user_progress up
    JOIN quizzes q ON up.quiz_id = q.id
    WHERE up.user_id = ?
    ORDER BY up.updated_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$userProgress = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo getUserTheme(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Quiz App</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="nav-brand">Quiz App</div>
                <ul class="nav-links">
                    <li><a href="quiz.php">Quizzes</a></li>
                    <li><a href="dashboard.php" class="active">Dashboard</a></li>
                    <!-- <li><a href="leaderboard.php">Leaderboard</a></li> -->
                    <?php if (isAdmin()): ?>
                        <li><a href="admin/dashboard.php">Admin</a></li>
                    <?php endif; ?>
                    <li>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="toggle_theme" class="theme-toggle">
                                <?php echo (getUserTheme() === 'dark') ? '☀️' : '🌙'; ?>
                            </button>
                        </form>
                    </li>
                    <li><a href="logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="dashboard-header">
            <h1>Welcome back, <?php echo $_SESSION['username']; ?>!</h1>
            <p>Track your learning progress and achievements</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-info">
                    <h3><?php echo $stats['quizzes_taken'] ?? 0; ?></h3>
                    <p>Quizzes Taken</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-info">
                    <h3><?php echo round($stats['avg_score'] ?? 0, 1); ?></h3>
                    <p>Average Score</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎯</div>
                <div class="stat-info">
                    <h3><?php echo $stats['total_attempts'] ?? 0; ?></h3>
                    <p>Total Attempts</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🏆</div>
                <div class="stat-info">
                    <h3>
                        <?php 
                        $completed = array_filter($userProgress, function($progress) {
                            return $progress['completed'];
                        });
                        echo count($completed);
                        ?>
                    </h3>
                    <p>Quizzes Completed</p>
                </div>
            </div>
        </div>

        <div class="dashboard-content">
            <!-- Recent Attempts -->
            <div class="dashboard-section">
                <h2>Recent Attempts</h2>
                <?php if ($recentAttempts): ?>
                    <div class="attempts-list">
                        <?php foreach ($recentAttempts as $attempt): 
                            $percentage = ($attempt['score'] / $attempt['total_questions']) * 100;
                            $passed = $percentage >= $attempt['passing_score'];
                        ?>
                            <div class="attempt-item <?php echo $passed ? 'passed' : 'failed'; ?>">
                                <div class="attempt-info">
                                    <h4><?php echo htmlspecialchars($attempt['title']); ?></h4>
                                    <p>Score: <?php echo $attempt['score']; ?>/<?php echo $attempt['total_questions']; ?> 
                                    (<?php echo round($percentage); ?>%)</p>
                                    <small><?php echo date('M j, Y g:i A', strtotime($attempt['completed_at'])); ?></small>
                                </div>
                                <div class="attempt-actions">
                                    <a href="results.php?attempt_id=<?php echo $attempt['id']; ?>" class="btn btn-sm">
                                        View Results
                                    </a>
                                    <?php if (canRetakeQuiz($_SESSION['user_id'], $attempt['quiz_id'])): ?>
                                        <a href="take_quiz.php?quiz_id=<?php echo $attempt['quiz_id']; ?>" class="btn btn-sm btn-primary">
                                            Retake
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No quiz attempts yet. <a href="quiz.php">Take your first quiz!</a></p>
                <?php endif; ?>
            </div>

            <!-- Progress Tracking -->
            <!-- <div class="dashboard-section">
                <h2>Your Progress</h2>
                 <?php if ($userProgress): ?>
                    <div class="progress-list">
                         <?php foreach ($userProgress as $progress): 
                            $percentage = ($progress['best_score'] / $progress['total_questions']) * 100;
                        ?> 
                            <div class="progress-item">
                                <div class="progress-info">
                                    <h4><?php echo htmlspecialchars($progress['title']); ?></h4>
                                    <p>Best Score: <?php echo $progress['best_score']; ?> 
                                    (<?php echo round($percentage); ?>%)</p>
                                    <p>Attempts: <?php echo $progress['attempts_count']; ?></p>
                                </div>
                                <div class="progress-status">
                                    <?php if ($progress['completed']): ?>
                                        <span class="status-completed">✅ Completed</span>
                                    <?php else: ?>
                                        <span class="status-in-progress">📝 In Progress</span>
                                    <?php endif; ?>
                                    <a href="take_quiz.php?quiz_id=<?php echo $progress['quiz_id']; ?>" class="btn btn-sm btn-primary">
                                        Continue
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No progress tracked yet. <a href="quiz.php">Start a quiz!</a></p> 
                <?php endif; ?>
            </div> -->
        </div>
    </div>

    <script src="assets/js/dashboard.js"></script>
</body>
</html>