<?php
require_once '../../src/config.php';
require_once '../../src/auth.php';

// Redirect if not admin
redirectIfNotAdmin();

// $stats = getAdminStats();
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo getUserTheme(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Quiz App</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .admin-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid var(--primary-color);
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
        
        .admin-nav {
            background: var(--card-bg);
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        
        .admin-nav ul {
            list-style: none;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        
        .admin-nav a {
            padding: 0.5rem 1rem;
            background: var(--bg-color);
            border-radius: 4px;
            text-decoration: none;
            color: var(--text-color);
        }
        
        .admin-nav a:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .action-card {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
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
                    <li><a href="dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="users.php">Users</a></li>
                    <li><a href="quizzes.php">Quizzes</a></li>
                    <li><a href="grades.php">Grades</a></li>
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
        <h1>Admin Dashboard</h1>
        <p>Welcome to the administration panel, <?php echo $_SESSION['username']; ?>!</p>

        <!-- Admin Navigation -->
        <div class="admin-nav">
            <ul>
                <li><a href="users.php">👥 Manage Users</a></li>
                <li><a href="quizzes.php">📝 Manage Quizzes</a></li>
                <li><a href="create_quiz.php">➕ Create Quiz</a></li>
            </ul>
        </div>

        

        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="action-card">
                <h3>Create New Quiz</h3>
                <p>Add a new quiz to the system</p>
                <a href="create_quiz.php" class="btn btn-primary">Create Quiz</a>
            </div>
            
            <div class="action-card">
                <h3>Manage Users</h3>
                <p>View and manage all users</p>
                <a href="users.php" class="btn btn-secondary">Manage Users</a>
            </div>
            
            <div class="action-card">
                <h3>View Grades</h3>
                <p>See system analytics and reports</p>
                <a href="grades.php" class="btn btn-success">View Grades</a>
            </div>
            
            
        </div>

        <!-- Recent Activity -->
        <div class="quiz-card">
            <h2>Recent Activity</h2>
            <?php
            $stmt = $pdo->query("
                SELECT u.username, q.title, qa.score, qa.completed_at 
                FROM quiz_attempts qa
                JOIN users u ON qa.user_id = u.id
                JOIN quizzes q ON qa.quiz_id = q.id
                ORDER BY qa.completed_at DESC 
                LIMIT 10
            ");
            $recentActivity = $stmt->fetchAll();
            
            if ($recentActivity): ?>
                <div class="activity-list">
                    <?php foreach ($recentActivity as $activity): ?>
                        <div class="activity-item">
                            <strong><?php echo htmlspecialchars($activity['username']); ?></strong> 
                            completed <strong><?php echo htmlspecialchars($activity['title']); ?></strong> 
                            with score <?php echo $activity['score']; ?> 
                            <small><?php echo date('M j, g:i A', strtotime($activity['completed_at'])); ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p>No recent activity.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>