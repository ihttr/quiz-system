<?php
require_once '../src/config.php';
require_once '../src/auth.php';

// Correct function name - fixed the typo
redirectIfNotLoggedIn();


// Safe version of canRetakeQuiz if it doesn't exist
if (!function_exists('canRetakeQuiz')) {
    function canRetakeQuiz($userId, $quizId) {
        // Default behavior - allow retakes
        return true;
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo getUserTheme(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quizzes - Quiz App</title>
    <link rel="stylesheet" href="assets/css/styles.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="nav-brand">Quiz App</div>
                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="quiz.php" class="active">Quizzes</a></li>
                     <li><a href="dashboard.php">Dashboard</a></li>
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
        <div class="quiz-container">
            <div class="quiz-card">
                <h1>Available Quizzes</h1>
                
                <?php
                // Get all active quizzes
                try {
                    $stmt = $pdo->prepare("
                        SELECT q.*, u.username as created_by_name 
                        FROM quizzes q 
                        LEFT JOIN users u ON q.created_by = u.id 
                        WHERE q.is_active = TRUE
                    ");
                    $stmt->execute();
                    $quizzes = $stmt->fetchAll();
                } catch (Exception $e) {
                    $quizzes = [];
                    error_log("Database error: " . $e->getMessage());
                }
                
                if ($quizzes): ?>
                    <div class="dashboard-grid">
                        <?php foreach ($quizzes as $quiz): 
                            // Safe check for retake capability
                            $canTakeQuiz = true;
                            try {
                                $canTakeQuiz = canRetakeQuiz($_SESSION['user_id'], $quiz['id']);
                            } catch (Exception $e) {
                                // If function fails, allow taking quiz
                                $canTakeQuiz = true;
                            }
                        ?>
                            <div class="dashboard-card">
                                <h3><?php echo htmlspecialchars($quiz['title']); ?></h3>
                                <p><?php echo htmlspecialchars($quiz['description']); ?></p>
                                <div class="quiz-meta">
                                    <span>By: <?php echo htmlspecialchars($quiz['created_by_name']); ?></span>
                                    <?php if (isset($quiz['time_limit']) && $quiz['time_limit'] > 0): ?>
                                        <span>⏱️ <?php echo $quiz['time_limit']; ?> min</span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($canTakeQuiz): ?>
                                    <a href="take_quiz.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-primary">
                                        Take Quiz
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>
                                        Attempts Exhausted
                                    </button>
                                    <a href="dashboard.php" class="btn btn-sm">View Results</a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert info">
                        <p>No quizzes available at the moment. Please check back later.</p>
                        <?php if (isAdmin()): ?>
                            <a href="admin/create_quiz.php" class="btn btn-primary">Create First Quiz</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- User Stats Card -->
            <div class="quiz-card">
                <h2>Your Quiz Statistics</h2>
                <?php
                try {
                    $stmt = $pdo->prepare("
                        SELECT 
                            COUNT(*) as total_attempts,
                            AVG(score) as avg_score,
                            MAX(score) as best_score
                        FROM quiz_attempts 
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$_SESSION['user_id']]);
                    $stats = $stmt->fetch();
                } catch (Exception $e) {
                    $stats = ['total_attempts' => 0, 'avg_score' => 0, 'best_score' => 0];
                }
                ?>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['total_attempts'] ?? 0; ?></span>
                        <span class="stat-label">Total Attempts</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo round($stats['avg_score'] ?? 0, 1); ?></span>
                        <span class="stat-label">Average Score</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-number"><?php echo $stats['best_score'] ?? 0; ?></span>
                        <span class="stat-label">Best Score</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>