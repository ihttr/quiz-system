<?php
require_once '../../src/config.php';
require_once '../../src/auth.php';
redirectIfNotAdmin();

// Handle quiz actions
if (isset($_POST['delete_quiz'])) {
    $quizId = $_POST['quiz_id'];
    
    try {
        $pdo->beginTransaction();
        
        // First delete related records to maintain referential integrity
        $stmt = $pdo->prepare("DELETE FROM answers WHERE attempt_id IN (SELECT id FROM quiz_attempts WHERE quiz_id = ?)");
        $stmt->execute([$quizId]);
        
        $stmt = $pdo->prepare("DELETE FROM quiz_attempts WHERE quiz_id = ?");
        $stmt->execute([$quizId]);
        
        $stmt = $pdo->prepare("DELETE FROM options WHERE question_id IN (SELECT id FROM questions WHERE quiz_id = ?)");
        $stmt->execute([$quizId]);
        
        $stmt = $pdo->prepare("DELETE FROM questions WHERE quiz_id = ?");
        $stmt->execute([$quizId]);
        
        $stmt = $pdo->prepare("DELETE FROM user_progress WHERE quiz_id = ?");
        $stmt->execute([$quizId]);
        
        // Finally delete the quiz
        $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ?");
        $stmt->execute([$quizId]);
        
        $pdo->commit();
        header("Location: quizzes.php?success=deleted");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error deleting quiz: " . $e->getMessage();
    }
}

if (isset($_POST['toggle_quiz'])) {
    $quizId = $_POST['quiz_id'];
    $stmt = $pdo->prepare("UPDATE quizzes SET is_active = NOT is_active WHERE id = ?");
    $stmt->execute([$quizId]);
    header("Location: quizzes.php?success=updated");
    exit();
}

// Get all quizzes with their questions count
$stmt = $pdo->prepare("
    SELECT q.*, 
           u.username as created_by_name,
           c.name as category_name,
           (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) as question_count,
           (SELECT COUNT(*) FROM quiz_attempts WHERE quiz_id = q.id) as attempt_count
    FROM quizzes q
    LEFT JOIN users u ON q.created_by = u.id
    LEFT JOIN categories c ON q.category_id = c.id
    ORDER BY q.created_at DESC
");
$stmt->execute();
$quizzes = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo getUserTheme(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Quizzes - Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .quizzes-grid {
            display: grid;
            gap: 1rem;
            margin: 1rem 0;
        }
        
        .quiz-item {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 8px;
            border-left: 4px solid var(--primary-color);
        }
        
        .quiz-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 1rem;
        }
        
        .quiz-meta {
            display: flex;
            gap: 1rem;
            margin: 0.5rem 0;
            flex-wrap: wrap;
        }
        
        .quiz-meta span {
            background: var(--bg-color);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .quiz-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }
        
        .status-active {
            color: var(--success-color);
            font-weight: bold;
        }
        
        .status-inactive {
            color: var(--danger-color);
            font-weight: bold;
        }
        
        .delete-warning {
            color: var(--danger-color);
            font-weight: bold;
            margin-top: 0.5rem;
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
                    <li><a href="quizzes.php" class="active">Quizzes</a></li>
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
        <div class="page-header">
            <h1>Manage Quizzes</h1>
            <a href="create_quiz.php" class="btn btn-primary">Create New Quiz</a>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert success">
                <?php 
                switch($_GET['success']) {
                    case 'deleted': echo 'Quiz has been deleted!'; break;
                    case 'updated': echo 'Quiz status updated!'; break;
                }
                ?>
            </div>
        <?php endif; ?>

        <div class="quiz-card">
            <h2>All Quizzes (<?php echo count($quizzes); ?>)</h2>
            
            <?php if ($quizzes): ?>
                <div class="quizzes-grid">
                    <?php foreach ($quizzes as $quiz): ?>
                        <div class="quiz-item">
                            <div class="quiz-header">
                                <div>
                                    <h3><?php echo htmlspecialchars($quiz['title']); ?></h3>
                                    <p><?php echo htmlspecialchars($quiz['description']); ?></p>
                                </div>
                                <span class="<?php echo $quiz['is_active'] ? 'status-active' : 'status-inactive'; ?>">
                                    <?php echo $quiz['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>
                            
                            <div class="quiz-meta">
                                <span>By: <?php echo htmlspecialchars($quiz['created_by_name']); ?></span>
                                <span>Category: <?php echo htmlspecialchars($quiz['category_name'] ?? 'Uncategorized'); ?></span>
                                <span>Questions: <?php echo $quiz['question_count']; ?></span>
                                <span>Attempts: <?php echo $quiz['attempt_count']; ?></span>
                                <?php if ($quiz['time_limit'] > 0): ?>
                                    <span>Time: <?php echo $quiz['time_limit']; ?> min</span>
                                <?php endif; ?>
                                <span>Created: <?php echo date('M j, Y', strtotime($quiz['created_at'])); ?></span>
                            </div>
                            
                            <div class="quiz-actions">
                                <a href="edit_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                <a href="../take_quiz.php?quiz_id=<?php echo $quiz['id']; ?>" class="btn btn-sm btn-secondary" target="_blank">Preview</a>
                                
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                                    <button type="submit" name="toggle_quiz" class="btn btn-sm btn-warning">
                                        <?php echo $quiz['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                    </button>
                                </form>
                                
                                <form method="POST" style="display: inline;" onsubmit="return confirmDelete(<?php echo $quiz['id']; ?>, '<?php echo htmlspecialchars($quiz['title']); ?>', <?php echo $quiz['attempt_count']; ?>)">
                                    <input type="hidden" name="quiz_id" value="<?php echo $quiz['id']; ?>">
                                    <button type="submit" name="delete_quiz" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </div>
                            
                            <?php if ($quiz['attempt_count'] > 0): ?>
                                <div class="delete-warning">
                                    ⚠️ This quiz has <?php echo $quiz['attempt_count']; ?> attempt(s). Deleting will remove all attempt history.
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert info">
                    <p>No quizzes found. <a href="create_quiz.php">Create the first quiz!</a></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    function confirmDelete(quizId, quizTitle, attemptCount) {
        let message = `Are you sure you want to delete the quiz "${quizTitle}"?`;
        
        message += "\n\nThis action cannot be undone!";
        
        return confirm(message);
    }
    </script>
</body>
</html>