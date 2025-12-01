<?php
require_once '../src/config.php';
require_once '../src/auth.php';

redirectIfNotLoggedIn();

// Provide a safe default for canRetakeQuiz if it's not defined elsewhere
if (!function_exists('canRetakeQuiz')) {
    /**
     * Default implementation: allow retakes.
     * Replace with your own logic (check attempts, cooldowns, etc.)
     */
    function canRetakeQuiz($userId, $quizId) {
        return true;
    }
}

// Fetch active quizzes
$quizzes = [];
try {
    $stmt = $pdo->prepare("
        SELECT q.*, u.username AS created_by_name
        FROM quizzes q
        LEFT JOIN users u ON q.created_by = u.id
        WHERE q.is_active = TRUE
        ORDER BY q.created_at DESC
    ");
    $stmt->execute();
    $quizzes = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Failed to load quizzes: " . $e->getMessage());
    $quizzes = [];
}

// Fetch user stats
$stats = ['total_attempts' => 0, 'avg_score' => 0, 'best_score' => 0];
try {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_attempts,
            COALESCE(AVG(score),0) AS avg_score,
            COALESCE(MAX(score),0) AS best_score
        FROM quiz_attempts
        WHERE user_id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $f = $stmt->fetch();
    if ($f) {
        $stats = $f;
    }
} catch (Exception $e) {
    error_log("Failed to load user stats: " . $e->getMessage());
    // keep defaults
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?php echo htmlspecialchars(getUserTheme()); ?>">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Quizzes - Quiz App</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <!-- <style>
        /* small inline styles for dashboard layout (optional) */
        .container { max-width: 1100px; margin: 0 auto; padding: 1rem; }
        .quiz-container { display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; align-items: start; }
        .quiz-card { background: var(--card-bg); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color); }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem; margin-top: 1rem; }
        .dashboard-card { padding: 1rem; border-radius: 8px; border: 1px solid var(--border-color); background: var(--surface); }
        .quiz-meta { display:flex; gap:0.75rem; font-size:0.9rem; color:var(--muted); margin-top:0.5rem; }
        .btn { display:inline-block; padding:0.45rem 0.75rem; border-radius:6px; text-decoration:none; }
        .btn-primary { background:var(--primary-color); color:white; }
        .btn-secondary { background:var(--border-color); color:inherit; }
        .stats-grid { display:grid; grid-template-columns: repeat(3, 1fr); gap:0.5rem; margin-top:0.75rem; }
        .stat-item { background:transparent; padding:0.5rem; text-align:center; }
        .stat-number { font-size:1.25rem; font-weight:700; display:block; }
        .theme-toggle { background:transparent; border:none; cursor:pointer; font-size:1rem; }
    </style> -->
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar" style="display:flex;align-items:center;justify-content:space-between;">
                <div class="nav-brand" style="font-weight:700;">Quiz App</div>
                <ul class="nav-links" style="list-style:none;display:flex;gap:1rem;align-items:center;margin:0;padding:0;">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="quiz.php">Quizzes</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <?php if (isAdmin()): ?>
                        <li><a href="admin/dashboard.php">Admin</a></li>
                    <?php endif; ?>
                    <li>
                        <form method="POST" style="display:inline;">
                            <button type="submit" name="toggle_theme" class="theme-toggle" title="Toggle theme">
                                <?php echo (getUserTheme() === 'dark') ? '☀️' : '🌙'; ?>
                            </button>
                        </form>
                    </li>
                    <li><a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="quiz-container">
            <div class="quiz-card">
                <h1>Available Quizzes</h1>

                <?php if (!empty($quizzes)): ?>
                    <div class="dashboard-grid" role="list">
                        <?php foreach ($quizzes as $quiz):
                            // ensure we have expected fields
                            $quizId = $quiz['id'] ?? null;
                            $createdBy = $quiz['created_by_name'] ?? 'Unknown';
                            // check time limit in minutes (support both old 'time_limit' and new 'time_limit_minutes')
                            $timeLimitMinutes = null;
                            if (isset($quiz['time_limit_minutes']) && is_numeric($quiz['time_limit_minutes'])) {
                                $timeLimitMinutes = (int)$quiz['time_limit_minutes'];
                            } elseif (isset($quiz['time_limit']) && is_numeric($quiz['time_limit'])) {
                                // fallback if older column exists (assume minutes)
                                $timeLimitMinutes = (int)$quiz['time_limit'];
                            }
                            // determine if user can take this quiz
                            $canTakeQuiz = true;
                            try {
                                $canTakeQuiz = canRetakeQuiz($_SESSION['user_id'], $quizId);
                            } catch (Exception $e) {
                                $canTakeQuiz = true;
                            }
                        ?>
                        <div class="dashboard-card" role="listitem">
                            <h3 style="margin:0 0 0.25rem 0;"><?php echo htmlspecialchars($quiz['title'] ?? 'Untitled'); ?></h3>
                            <p style="margin:0 0 0.5rem 0;"><?php echo htmlspecialchars($quiz['description'] ?? ''); ?></p>

                            <div class="quiz-meta">
                                <span>By: <?php echo htmlspecialchars($createdBy); ?></span>
                                <?php if ($timeLimitMinutes !== null && $timeLimitMinutes > 0): ?>
                                    <span aria-label="Time limit">⏱️ <?php echo (int)$timeLimitMinutes; ?> min</span>
                                <?php endif; ?>
                                <?php if (isset($quiz['num_questions'])): ?>
                                    <span><?php echo (int)$quiz['num_questions']; ?> q</span>
                                <?php endif; ?>
                            </div>

                            <div style="margin-top:0.75rem; display:flex; gap:0.5rem; align-items:center;">
                                <?php if ($canTakeQuiz): ?>
                                    <a class="btn btn-primary" href="take_quiz.php?quiz_id=<?php echo $quizId; ?>">Take Quiz</a>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>Attempts Exhausted</button>
                                    <a class="btn" href="dashboard.php">View Results</a>
                                <?php endif; ?>

                                <?php if (isAdmin()): ?>
                                    <a class="btn" href="admin/edit_quiz.php?id=<?php echo $quizId; ?>">Edit</a>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert info" role="status" style="margin-top:1rem;">
                        <p>No quizzes available at the moment. Please check back later.</p>
                        <?php if (isAdmin()): ?>
                            <a class="btn btn-primary" href="admin/create_quiz.php">Create First Quiz</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Right-side: User Stats -->
            <div class="quiz-card" aria-labelledby="your-stats">
                <h2 id="your-stats">Your Quiz Statistics</h2>
                <div class="stats-grid" role="list">
                    <div class="stat-item" role="listitem">
                        <span class="stat-number"><?php echo (int)($stats['total_attempts'] ?? 0); ?></span>
                        <span class="stat-label">Total Attempts</span>
                    </div>
                    <div class="stat-item" role="listitem">
                        <span class="stat-number"><?php echo round($stats['avg_score'] ?? 0, 1); ?></span>
                        <span class="stat-label">Average Score</span>
                    </div>
                    <div class="stat-item" role="listitem">
                        <span class="stat-number"><?php echo (int)($stats['best_score'] ?? 0); ?></span>
                        <span class="stat-label">Best Score</span>
                    </div>
                </div>

                <div style="margin-top:1rem;">
                    <a class="btn" href="dashboard.php">View All Attempts</a>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
</body>
</html>
