<?php
require_once '../src/config.php';
require_once '../src/auth.php';
redirectIfNotLoggedIn();

$attemptId = $_GET['attempt_id'] ?? null;
if (!$attemptId) {
    header("Location: quiz.php");
    exit();
}

// Get attempt details
$stmt = $pdo->prepare("
    SELECT qa.*, q.title, u.username
    FROM quiz_attempts qa
    JOIN quizzes q ON qa.quiz_id = q.id
    JOIN users u ON qa.user_id = u.id
    WHERE qa.id = ? AND qa.user_id = ?
");
$stmt->execute([$attemptId, $_SESSION['user_id']]);
$attempt = $stmt->fetch();

if (!$attempt) {
    header("Location: quiz.php");
    exit();
}

// Calculate percentage
$percentage = ($attempt['score'] / $attempt['total_questions']) * 100;
$passed = $percentage >= 60; // 60% passing score

// Get detailed results
$stmt = $pdo->prepare("
    SELECT q.question_text, o.option_text as selected_answer, 
           (SELECT option_text FROM options WHERE question_id = q.id AND is_correct = 1) as correct_answer,
           a.is_correct
    FROM answers a
    JOIN questions q ON a.question_id = q.id
    LEFT JOIN options o ON a.selected_option_id = o.id
    WHERE a.attempt_id = ?
");
$stmt->execute([$attemptId]);
$answers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo getUserTheme(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - Quiz App</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .results-summary {
            text-align: center;
            padding: 2rem;
            background: var(--card-bg);
            border-radius: 8px;
            margin: 2rem 0;
        }
        
        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            border: 8px solid;
            margin: 0 auto 2rem;
        }
        
        .passed { border-color: var(--success-color); background: rgba(40, 167, 69, 0.1); }
        .failed { border-color: var(--danger-color); background: rgba(220, 53, 69, 0.1); }
        
        .score { font-size: 2rem; }
        .score-text { font-size: 1rem; margin-top: 0.5rem; }
        
        .answer-item {
            background: var(--card-bg);
            padding: 1rem;
            margin: 1rem 0;
            border-radius: 8px;
            border-left: 4px solid;
        }
        
        .correct { border-left-color: var(--success-color); }
        .incorrect { border-left-color: var(--danger-color); }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="nav-brand">Quiz Results</div>
                <ul class="nav-links">
                    <li><a href="quiz.php">Back to Quizzes</a></li>
                    <li><a href="logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="results-summary">
            <h1><?php echo htmlspecialchars($attempt['title']); ?> - Results</h1>
            
            <div class="score-circle <?php echo $passed ? 'passed' : 'failed'; ?>">
                <span class="score"><?php echo round($percentage); ?>%</span>
                <span class="score-text"><?php echo $passed ? 'Passed' : 'Failed'; ?></span>
            </div>
            
            <div class="score-details">
                <p><strong>Score:</strong> <?php echo $attempt['score']; ?> out of <?php echo $attempt['total_questions']; ?></p>
                <p><strong>Date:</strong> <?php echo date('F j, Y g:i A', strtotime($attempt['completed_at'])); ?></p>
            </div>
            
            <div class="results-actions">
                <a href="take_quiz.php?quiz_id=<?php echo $attempt['quiz_id']; ?>" class="btn btn-primary">Retake Quiz</a>
                <a href="quiz.php" class="btn btn-secondary">Back to Quizzes</a>
            </div>
        </div>

        <div class="quiz-card">
            <h2>Detailed Results</h2>
            
            <?php foreach ($answers as $index => $answer): ?>
                <div class="answer-item <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                    <h3>Question <?php echo $index + 1; ?></h3>
                    <p><strong>Question:</strong> <?php echo htmlspecialchars($answer['question_text']); ?></p>
                    <p><strong>Your Answer:</strong> <?php echo htmlspecialchars($answer['selected_answer'] ?? 'Not answered'); ?></p>
                    <?php if (!$answer['is_correct']): ?>
                        <p><strong>Correct Answer:</strong> <?php echo htmlspecialchars($answer['correct_answer']); ?></p>
                    <?php endif; ?>
                    <p><strong>Result:</strong> 
                        <span style="color: <?php echo $answer['is_correct'] ? 'var(--success-color)' : 'var(--danger-color)'; ?>">
                            <?php echo $answer['is_correct'] ? '✓ Correct' : '✗ Incorrect'; ?>
                        </span>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>