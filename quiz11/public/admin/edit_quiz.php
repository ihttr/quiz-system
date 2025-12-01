<?php
require_once '../../src/config.php';
require_once '../../src/auth.php';
redirectIfNotAdmin();

$quizId = $_GET['id'] ?? null;
if (!$quizId) {
    header("Location: quizzes.php");
    exit();
}

// Get quiz data
$stmt = $pdo->prepare("
    SELECT q.*, u.username as created_by_name
    FROM quizzes q
    LEFT JOIN users u ON q.created_by = u.id
    WHERE q.id = ?
");
$stmt->execute([$quizId]);
$quiz = $stmt->fetch();

if (!$quiz) {
    header("Location: quizzes.php");
    exit();
}


// Get questions for this quiz
$stmt = $pdo->prepare("
    SELECT q.* 
    FROM questions q
    WHERE q.quiz_id = ?
    ORDER BY q.id
");
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll();

// Get options for each question
foreach ($questions as &$question) {
    $stmt = $pdo->prepare("
        SELECT o.* 
        FROM options o
        WHERE o.question_id = ?
        ORDER BY o.id
    ");
    $stmt->execute([$question['id']]);
    $question['options'] = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $time_limit = $_POST['time_limit'] ?: 0;
    $allow_retakes = isset($_POST['allow_retakes']) ? 1 : 0;
    $passing_score = $_POST['passing_score'] ?: 60;
    
    try {
        $pdo->beginTransaction();
        
        // Update quiz
        $stmt = $pdo->prepare("
            UPDATE quizzes 
            SET title = ?, description = ?, time_limit = ?,
                 allow_retakes = ?,  passing_score = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $title, $description, $time_limit,
             $allow_retakes, $passing_score, $quizId
        ]);
        
        $pdo->commit();
        header("Location: quizzes.php?success=updated");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error updating quiz: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo getUserTheme(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quiz - Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="nav-brand">Quiz App - Admin</div>
                <ul class="nav-links">
                    <li><a href="../quiz.php">← Back to Quiz</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="quizzes.php">Quizzes</a></li>
                    <li><a href="edit_quiz.php?id=<?php echo $quizId; ?>" class="active">Edit Quiz</a></li>
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
        <h1>Edit Quiz: <?php echo htmlspecialchars($quiz['title']); ?></h1>
        
        <?php if (isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST">
            <!-- Basic Quiz Info -->
            <div class="form-section">
                <h2>Basic Information</h2>
                <div class="form-group">
                    <label for="title">Quiz Title *</label>
                    <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($quiz['title']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($quiz['description']); ?></textarea>
                </div>
                
            </div>

            <!-- Quiz Settings -->
            <div class="form-section">
                <h2>Quiz Settings</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="time_limit">Time Limit (minutes)</label>
                        <input type="number" id="time_limit" name="time_limit" min="0" value="<?php echo $quiz['time_limit']; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="passing_score">Passing Score (%)</label>
                        <input type="number" id="passing_score" name="passing_score" min="0" max="100" value="<?php echo $quiz['passing_score']; ?>">
                    </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="allow_retakes" value="1" <?php echo $quiz['allow_retakes'] ? 'checked' : ''; ?>>
                        Allow Retakes
                    </label>
                </div>
            </div>

            <!-- Questions Overview -->
            <div class="form-section">
                <h2>Questions (<?php echo count($questions); ?>)</h2>
                <?php if ($questions): ?>
                    <div class="questions-list">
                        <?php foreach ($questions as $index => $question): ?>
                            <div class="question-item">
                                <h4>Question <?php echo $index + 1; ?>: <?php echo htmlspecialchars($question['question_text']); ?></h4>
                                <p><strong>Type:</strong> <?php echo ucfirst(str_replace('_', ' ', $question['question_type'])); ?> | 
                                   <strong>Difficulty:</strong> <?php echo ucfirst($question['difficulty']); ?> | 
                                   <strong>Points:</strong> <?php echo $question['points']; ?></p>
                                <p><strong>Options:</strong> <?php echo count($question['options']); ?> | 
                                   <strong>Correct:</strong> 
                                   <?php 
                                   $correctOption = array_filter($question['options'], function($opt) { return $opt['is_correct']; });
                                   echo count($correctOption) > 0 ? htmlspecialchars(current($correctOption)['option_text']) : 'None';
                                   ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No questions in this quiz.</p>
                <?php endif; ?>
                <p><a href="add_questions.php?quiz_id=<?php echo $quizId; ?>" class="btn btn-primary">Manage Questions</a></p>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-success">Update Quiz</button>
                <a href="quizzes.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</body>
</html>