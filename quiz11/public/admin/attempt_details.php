<?php
require_once '../../src/config.php';
require_once '../../src/auth.php';
redirectIfNotAdmin();

$attemptId = $_GET['id'] ?? null;
if (!$attemptId) {
    header("Location: grades.php");
    exit();
}

// Get attempt details
$stmt = $pdo->prepare("
    SELECT 
        qa.*,
        u.username,
        u.email,
        q.title as quiz_title,
        q.passing_score,
        ROUND((qa.score / qa.total_questions) * 100, 2) as percentage
    FROM quiz_attempts qa
    JOIN users u ON qa.user_id = u.id
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.id = ?
");
$stmt->execute([$attemptId]);
$attempt = $stmt->fetch();

if (!$attempt) {
    header("Location: grades.php");
    exit();
}

// Get detailed answers
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        q.question_text,
        q.question_type,
        q.points,
        so.option_text as selected_option,
        co.option_text as correct_option,
        so.is_correct as selected_correct
    FROM answers a
    JOIN questions q ON a.question_id = q.id
    LEFT JOIN options so ON a.selected_option_id = so.id
    LEFT JOIN options co ON co.question_id = q.id AND co.is_correct = 1
    WHERE a.attempt_id = ?
    ORDER BY a.id
");
$stmt->execute([$attemptId]);
$answers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo getUserTheme(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attempt Details - Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .attempt-header {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .attempt-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 1.5rem 0;
        }
        
        .stat-item {
            text-align: center;
            padding: 1rem;
            background: var(--bg-color);
            border-radius: 4px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            display: block;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
        }
        
        .answer-item {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            border-left: 4px solid var(--border-color);
        }
        
        .answer-item.correct {
            border-left-color: var(--success-color);
        }
        
        .answer-item.incorrect {
            border-left-color: var(--danger-color);
        }
        
        .question-text {
            font-weight: 500;
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }
        
        .answer-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .answer-box {
            padding: 1rem;
            border-radius: 4px;
            background: var(--bg-color);
        }
        
        .answer-box.correct {
            background: rgba(40, 167, 69, 0.1);
            border: 1px solid var(--success-color);
        }
        
        .answer-box.incorrect {
            background: rgba(220, 53, 69, 0.1);
            border: 1px solid var(--danger-color);
        }
        
        .answer-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="nav-brand">Quiz App - Admin</div>
                <ul class="nav-links">
                    <li><a href="grades.php">← Back to Grades</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
                    <li><a href="grades.php" class="active">Grades</a></li>
                    <li>
                        <form method="POST" style="display: inline;">
                            <button type="submit" name="toggle_theme" class="theme-toggle">
                                <?php echo (getUserTheme() === 'dark') ? '☀️' : '🌙'; ?>
                            </button>
                        </form>
                    </li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <div class="attempt-header">
            <h1>Attempt Details</h1>
            <p>
                <strong>User:</strong> <?php echo htmlspecialchars($attempt['username']); ?> 
                (<?php echo htmlspecialchars($attempt['email']); ?>)<br>
                <strong>Quiz:</strong> <?php echo htmlspecialchars($attempt['quiz_title']); ?><br>
                <strong>Completed:</strong> <?php echo date('F j, Y g:i A', strtotime($attempt['completed_at'])); ?>
            </p>
            
            <div class="attempt-stats">
                <div class="stat-item">
                    <span class="stat-value"><?php echo $attempt['score']; ?>/<?php echo $attempt['total_questions']; ?></span>
                    <span class="stat-label">Score</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value"><?php echo $attempt['percentage']; ?>%</span>
                    <span class="stat-label">Percentage</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value <?php echo $attempt['percentage'] >= $attempt['passing_score'] ? 'status-passed' : 'status-failed'; ?>">
                        <?php echo $attempt['percentage'] >= $attempt['passing_score'] ? 'Passed' : 'Failed'; ?>
                    </span>
                    <span class="stat-label">Result</span>
                </div>
                <div class="stat-item">
                    <span class="stat-value">
                        <?php
                        $minutes = floor($attempt['time_taken'] / 60);
                        $seconds = $attempt['time_taken'] % 60;
                        echo sprintf('%d:%02d', $minutes, $seconds);
                        ?>
                    </span>
                    <span class="stat-label">Time Taken</span>
                </div>
            </div>
        </div>

        <h2>Question Details</h2>
        
        <?php foreach ($answers as $index => $answer): ?>
            <div class="answer-item <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                <div class="question-text">
                    Q<?php echo $index + 1; ?>: <?php echo htmlspecialchars($answer['question_text']); ?>
                    <small>(<?php echo ucfirst(str_replace('_', ' ', $answer['question_type'])); ?> - <?php echo $answer['points']; ?> points)</small>
                </div>
                
                <div class="answer-details">
                    <div class="answer-box <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                        <span class="answer-label">User's Answer:</span>
                        <?php echo htmlspecialchars($answer['selected_option'] ?? 'Not answered'); ?>
                        <?php if ($answer['is_correct']): ?>
                            <span style="color: var(--success-color); font-weight: 600;"> ✓ Correct</span>
                        <?php else: ?>
                            <span style="color: var(--danger-color); font-weight: 600;"> ✗ Incorrect</span>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!$answer['is_correct'] && !empty($answer['correct_option'])): ?>
                        <div class="answer-box correct">
                            <span class="answer-label">Correct Answer:</span>
                            <?php echo htmlspecialchars($answer['correct_option']); ?>
                            <span style="color: var(--success-color); font-weight: 600;"> ✓</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <?php if ($answer['points_earned'] > 0): ?>
                    <div style="margin-top: 0.5rem; font-weight: 600; color: var(--success-color);">
                        +<?php echo $answer['points_earned']; ?> points earned
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</body>
</html>