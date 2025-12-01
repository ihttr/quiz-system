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
    SELECT 
        qa.*,
        u.username,
        q.title as quiz_title,
        q.description as quiz_description,
        ROUND((qa.score / qa.total_questions) * 100, 2) as percentage
    FROM quiz_attempts qa
    JOIN users u ON qa.user_id = u.id
    JOIN quizzes q ON qa.quiz_id = q.id
    WHERE qa.id = ? AND qa.user_id = ?
");
$stmt->execute([$attemptId, $_SESSION['user_id']]);
$attempt = $stmt->fetch();

if (!$attempt) {
    header("Location: quiz.php");
    exit();
}

// Get detailed answers with questions and options
$stmt = $pdo->prepare("
    SELECT 
        a.*,
        q.question_text,
        q.question_type,
        q.points,
        so.option_text as selected_option_text,
        so.is_correct as selected_correct,
        co.option_text as correct_option_text
    FROM answers a
    JOIN questions q ON a.question_id = q.id
    LEFT JOIN options so ON a.selected_option_id = so.id
    LEFT JOIN options co ON co.question_id = q.id AND co.is_correct = 1
    WHERE a.attempt_id = ?
    ORDER BY a.id
");
$stmt->execute([$attemptId]);
$answers = $stmt->fetchAll();

// Calculate stats
$correctAnswers = 0;
$totalPoints = 0;
$earnedPoints = 0;

foreach ($answers as $answer) {
    if ($answer['is_correct']) {
        $correctAnswers++;
    }
    $totalPoints += $answer['points'];
    $earnedPoints += $answer['points_earned'];
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo getUserTheme(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Review - <?php echo htmlspecialchars($attempt['quiz_title']); ?></title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .review-header {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .score-display {
            font-size: 3rem;
            font-weight: bold;
            color: var(--primary-color);
            margin: 1rem 0;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin: 2rem 0;
        }
        
        .stat-card {
            background: var(--bg-color);
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            display: block;
        }
        
        .stat-label {
            color: var(--text-color);
            opacity: 0.8;
        }
        
        .question-review {
            background: var(--card-bg);
            padding: 2rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            border-left: 4px solid var(--border-color);
        }
        
        .question-review.correct {
            border-left-color: var(--success-color);
        }
        
        .question-review.incorrect {
            border-left-color: var(--danger-color);
        }
        
        .question-text {
            font-size: 1.2rem;
            font-weight: 500;
            margin-bottom: 1.5rem;
            line-height: 1.5;
        }
        
        .answer-comparison {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin: 1.5rem 0;
        }
        
        @media (max-width: 768px) {
            .answer-comparison {
                grid-template-columns: 1fr;
            }
        }
        
        .answer-box {
            padding: 1.5rem;
            border-radius: 8px;
            border: 2px solid var(--border-color);
        }
        
        .answer-box.user-answer.correct {
            background: rgba(40, 167, 69, 0.1);
            border-color: var(--success-color);
        }
        
        .answer-box.user-answer.incorrect {
            background: rgba(220, 53, 69, 0.1);
            border-color: var(--danger-color);
        }
        
        .answer-box.correct-answer {
            background: rgba(40, 167, 69, 0.05);
            border-color: var(--success-color);
        }
        
        .answer-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            display: block;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .answer-text {
            font-size: 1.1rem;
            margin: 0.5rem 0;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            margin-top: 0.5rem;
        }
        
        .status-correct {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
        }
        
        .status-incorrect {
            background: rgba(220, 53, 69, 0.1);
            color: var(--danger-color);
        }
        
        .points-info {
            text-align: right;
            font-weight: 600;
            margin-top: 1rem;
        }
        
        .points-earned {
            color: var(--success-color);
        }
        
        .points-missed {
            color: var(--danger-color);
        }
        
        .review-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-color);
        }
        
        .question-number {
            display: inline-block;
            background: var(--primary-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .question-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .question-type {
            background: var(--bg-color);
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.8rem;
            color: var(--text-color);
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="nav-brand">Quiz App</div>
                <ul class="nav-links">
                    <li><a href="quiz.php">Quizzes</a></li>
                    <li><a href="dashboard.php">Dashboard</a></li>
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
        <!-- Quiz Overview -->
        <div class="review-header">
            <h1>Quiz Review: <?php echo htmlspecialchars($attempt['quiz_title']); ?></h1>
            <div class="score-display">
                <?php echo $attempt['percentage']; ?>%
            </div>
            <p>Completed on <?php echo date('F j, Y \a\t g:i A', strtotime($attempt['completed_at'])); ?></p>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <span class="stat-number"><?php echo $attempt['score']; ?>/<?php echo $attempt['total_questions']; ?></span>
                    <span class="stat-label">Questions Correct</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo $correctAnswers; ?>/<?php echo count($answers); ?></span>
                    <span class="stat-label">Correct Answers</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number"><?php echo $earnedPoints; ?>/<?php echo $totalPoints; ?></span>
                    <span class="stat-label">Points Earned</span>
                </div>
                <div class="stat-card">
                    <span class="stat-number">
                        <?php
                        $timeTaken = $attempt['time_taken'] ?? 0;
                        $minutes = floor($timeTaken / 60);
                        $seconds = $timeTaken % 60;
                        echo sprintf('%d:%02d', $minutes, $seconds);
                        ?>
                    </span>
                    <span class="stat-label">Time Taken</span>
                </div>
            </div>
        </div>

        <!-- Questions Review -->
        <h2>Question Review</h2>
        
        <?php foreach ($answers as $index => $answer): ?>
            <div class="question-review <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                <div class="question-meta">
                    <div class="question-number">Question <?php echo $index + 1; ?></div>
                    <div class="question-type">
                        <?php echo ucfirst(str_replace('_', ' ', $answer['question_type'])); ?> 
                        • <?php echo $answer['points']; ?> point<?php echo $answer['points'] > 1 ? 's' : ''; ?>
                    </div>
                </div>
                
                <div class="question-text">
                    <?php echo htmlspecialchars($answer['question_text']); ?>
                </div>
                
                <div class="answer-comparison">
                    <!-- User's Answer -->
                    <div class="answer-box user-answer <?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                        <span class="answer-label">Your Answer</span>
                        <div class="answer-text">
                            <?php 
                            if (!empty($answer['selected_option_text'])) {
                                echo htmlspecialchars($answer['selected_option_text']);
                            } else {
                                echo '<em>No answer selected</em>';
                            }
                            ?>
                        </div>
                        <div class="status-indicator status-<?php echo $answer['is_correct'] ? 'correct' : 'incorrect'; ?>">
                            <?php if ($answer['is_correct']): ?>
                                ✓ Correct
                            <?php else: ?>
                                ✗ Incorrect
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Correct Answer (only show if user was wrong) -->
                    <?php if (!$answer['is_correct'] && !empty($answer['correct_option_text'])): ?>
                        <div class="answer-box correct-answer">
                            <span class="answer-label">Correct Answer</span>
                            <div class="answer-text">
                                <?php echo htmlspecialchars($answer['correct_option_text']); ?>
                            </div>
                            <div class="status-indicator status-correct">
                                ✓ Correct
                            </div>
                        </div>
                    <?php elseif ($answer['is_correct']): ?>
                        <div class="answer-box correct-answer">
                            <span class="answer-label">Result</span>
                            <div class="answer-text">
                                Great job! You selected the correct answer.
                            </div>
                            <div class="status-indicator status-correct">
                                ✓ Perfect!
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- <div class="points-info">
                    <?php if ($answer['points_earned'] > 0): ?>
                        <span class="points-earned">+<?php echo $answer['points_earned']; ?> point<?php echo $answer['points_earned'] > 1 ? 's' : ''; ?> earned</span>
                    <?php else: ?>
                        <span class="points-missed">0 points earned (<?php echo $answer['points']; ?> possible)</span>
                    <?php endif; ?>
                </div> -->
            </div>
        <?php endforeach; ?>

        <!-- Action Buttons -->
        <div class="review-actions">
            <?php if (canRetakeQuiz($_SESSION['user_id'], $attempt['quiz_id'])): ?>
                <a href="take_quiz.php?quiz_id=<?php echo $attempt['quiz_id']; ?>" class="btn btn-primary">Retake Quiz</a>
            <?php endif; ?>
            <a href="results.php?attempt_id=<?php echo $attemptId; ?>" class="btn btn-secondary">Back to Results</a>
            <a href="quiz.php" class="btn btn-success">Take Another Quiz</a>
        </div>
    </div>

    <script>
        // Add smooth scrolling to questions
        document.addEventListener('DOMContentLoaded', function() {
            // Highlight the first incorrect answer if any
            const firstIncorrect = document.querySelector('.question-review.incorrect');
            if (firstIncorrect) {
                firstIncorrect.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Add a visual pulse to draw attention
                firstIncorrect.style.animation = 'pulse 2s ease-in-out';
                
                // Create CSS for pulse animation
                const style = document.createElement('style');
                style.textContent = `
                    @keyframes pulse {
                        0% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0.4); }
                        70% { box-shadow: 0 0 0 10px rgba(220, 53, 69, 0); }
                        100% { box-shadow: 0 0 0 0 rgba(220, 53, 69, 0); }
                    }
                `;
                document.head.appendChild(style);
            }
        });
        
        // Print functionality
        function printReview() {
            window.print();
        }
    </script>
</body>
</html>