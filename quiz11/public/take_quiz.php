<?php
require_once '../src/config.php';
require_once '../src/auth.php';
redirectIfNotLoggedIn();

$quizId = $_GET['quiz_id'] ?? null;
if (!$quizId) {
    header("Location: quiz.php");
    exit();
}

// Get quiz details and questions in one query
$stmt = $pdo->prepare("
    SELECT 
        q.*,
        qu.id as question_id,
        qu.question_text,
        qu.points,
        o.id as option_id,
        o.option_text,
        o.is_correct
    FROM quizzes q
    LEFT JOIN questions qu ON q.id = qu.quiz_id
    LEFT JOIN options o ON qu.id = o.question_id
    WHERE q.id = ? AND q.is_active = TRUE
    ORDER BY qu.id, o.id
");
$stmt->execute([$quizId]);
$results = $stmt->fetchAll();

if (!$results) {
    header("Location: quiz.php?error=quiz_not_found");
    exit();
}

// Determine time limit for quiz (in seconds)
// If your quizzes table has a `time_limit` column (seconds), it will be used.
// Otherwise fallback to 300 seconds (5 minutes).
$timeLimitSeconds = isset($results[0]['time_limit']) && is_numeric($results[0]['time_limit'])
    ? (int)$results[0]['time_limit']
    : 300; // default 5 minutes
    $timeLimitSeconds = max(1, $timeLimitSeconds) * 60; // ensure at least 1 minute -> 60s

// Organize data
$quiz = [
    'id' => $results[0]['id'],
    'title' => $results[0]['title'],
    'description' => $results[0]['description']
];

$questions = [];
foreach ($results as $row) {
    if ($row['question_id']) {
        if (!isset($questions[$row['question_id']])) {
            $questions[$row['question_id']] = [
                'id' => $row['question_id'],
                'text' => $row['question_text'],
                'points' => $row['points'],
                'options' => []
            ];
        }
        
        if ($row['option_id']) {
            $questions[$row['question_id']]['options'][] = [
                'id' => $row['option_id'],
                'text' => $row['option_text'],
                'is_correct' => $row['is_correct']
            ];
        }
    }
}

$questions = array_values($questions); // Reset array keys
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo getUserTheme(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($quiz['title']); ?> - Quiz App</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        .question-block {
            background: var(--card-bg);
            padding: 1.5rem;
            margin: 1rem 0;
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }
        
        .options {
            margin: 1rem 0;
        }
        
        .option {
            padding: 1rem;
            margin: 0.5rem 0;
            border: 1px solid var(--border-color);
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .option:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .option.selected {
            background: var(--primary-color);
            color: white;
            border-color: var(--primary-color);
        }
        
        .quiz-progress {
            background: var(--card-bg);
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            text-align: center;
        }
        
        .progress-bar {
            width: 100%;
            height: 10px;
            background: var(--border-color);
            border-radius: 5px;
            margin: 0.5rem 0;
            overflow: hidden;
        }
        
        .progress {
            height: 100%;
            background: var(--primary-color);
            transition: width 0.3s;
        }
        
        .navigation {
            display: flex;
            justify-content: space-between;
            margin: 2rem 0;
            padding: 1rem;
            background: var(--card-bg);
            border-radius: 8px;
        }

        /* NEW - Timer styles */
        .timer-container {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.75rem 1rem;
            background: var(--card-bg);
            border-radius: 8px;
            border: 1px solid var(--border-color);
            margin: 1rem 0;
        }

        .time-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.25rem;
        }

        .time-remaining {
            font-weight: 600;
            font-size: 1.1rem;
        }

        .time-bar-wrap {
            flex: 1;
            margin-left: 1rem;
        }

        .time-bar {
            width: 100%;
            height: 12px;
            background: var(--border-color);
            border-radius: 6px;
            overflow: hidden;
        }

        .time-bar-fill {
            height: 100%;
            width: 100%;
            transition: width 0.5s linear, background-color 0.3s;
            background: #28a745; /* green by default */
        }

        .time-warning {
            color: #b45f00;
            font-weight: 600;
        }

        .time-ended {
            color: #c82333;
            font-weight: 700;
        }
    </style>
</head>
<body data-time-limit="<?php echo $timeLimitSeconds; ?>">
    <header class="header">
        <div class="container">
            <nav class="navbar">
                <div class="nav-brand"><?php echo htmlspecialchars($quiz['title']); ?></div>
                <ul class="nav-links">
                    <li><span id="currentQuestion">1/<?php echo count($questions); ?></span></li>
                    <li><a href="quiz.php">Exit Quiz</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <form id="quizForm" action="submit_quiz_new.php" method="POST">
            <input type="hidden" name="quiz_id" value="<?php echo $quizId; ?>">
            
            <!-- TIMER -->
            <div class="timer-container" id="timerContainer" role="region" aria-live="polite">
                <div class="time-info">
                    <div>Time Remaining</div>
                    <div class="time-remaining" id="timeRemaining">--:--</div>
                </div>

                <div class="time-bar-wrap">
                    <div class="time-bar" aria-hidden="true">
                        <div class="time-bar-fill" id="timeBarFill" style="width: 100%"></div>
                    </div>
                    <!-- <div style="display:flex;justify-content:space-between;font-size:0.85rem;margin-top:0.25rem;">
                        <span id="timeLabelStart">Full</span>
                        <span id="timeLabelEnd">0:00</span>
                    </div> -->
                </div>

                <div style="min-width:120px;text-align:right;">
                    <div id="timerStatus" aria-hidden="true"></div>
                </div>
            </div>

            <div class="quiz-progress">
                <div>Progress: <span id="progressText">1</span> of <?php echo count($questions); ?> questions</div>
                <div class="progress-bar">
                    <div class="progress" id="progressBar" style="width: <?php echo (1/count($questions))*100; ?>%"></div>
                </div>
            </div>

            <?php foreach ($questions as $index => $question): ?>
                <div class="question-block" id="question-<?php echo $index + 1; ?>" style="<?php echo $index > 0 ? 'display: none;' : ''; ?>">
                    <h2>Question <?php echo $index + 1; ?></h2>
                    <p><?php echo htmlspecialchars($question['text']); ?></p>
                    
                    <div class="options">
                        <?php foreach ($question['options'] as $option): ?>
                            <div class="option" onclick="selectOption(this, <?php echo $question['id']; ?>, <?php echo $option['id']; ?>)">
                                <input type="radio" 
                                       name="answers[<?php echo $question['id']; ?>]" 
                                       value="<?php echo $option['id']; ?>" 
                                       style="display: none;">
                                <?php echo htmlspecialchars($option['text']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <div class="navigation">
                <button type="button" id="prevBtn" class="btn btn-secondary" onclick="previousQuestion()" disabled>Previous</button>
                <button type="button" id="nextBtn" class="btn btn-primary" onclick="nextQuestion()">
                    <?php echo count($questions) > 1 ? 'Next' : 'Submit Quiz'; ?>
                </button>
                <button type="submit" id="submitBtn" class="btn btn-success" style="display: none;">Submit Quiz</button>
            </div>
        </form>
    </div>

    <script>
        let currentQuestion = 1;
        const totalQuestions = <?php echo count($questions); ?>;
        const answers = {};
        const form = document.getElementById('quizForm');

        // Timer setup
        const timeLimit = parseInt(document.body.getAttribute('data-time-limit'), 10) || 300; // seconds
        let timeRemainingSeconds = timeLimit;
        let timerInterval = null;
        const timeRemainingEl = document.getElementById('timeRemaining');
        const timeBarFill = document.getElementById('timeBarFill');
        const timerStatus = document.getElementById('timerStatus');

        function formatTime(s) {
            const mm = Math.floor(s / 60).toString().padStart(2, '0');
            const ss = (s % 60).toString().padStart(2, '0');
            return `${mm}:${ss}`;
        }

        function startTimer() {
            // Initial render
            updateTimerUI();

            timerInterval = setInterval(() => {
                timeRemainingSeconds--;

                if (timeRemainingSeconds < 0) {
                    clearInterval(timerInterval);
                    timeRemainingSeconds = 0;
                    updateTimerUI();
                    handleTimeUp();
                    return;
                }

                updateTimerUI();
            }, 1000);
        }

        function stopTimer() {
            if (timerInterval) {
                clearInterval(timerInterval);
                timerInterval = null;
            }
        }

        function updateTimerUI() {
            // Update text
            timeRemainingEl.textContent = formatTime(timeRemainingSeconds);

            // Update bar width
            const percent = Math.max(0, (timeRemainingSeconds / timeLimit) * 100);
            timeBarFill.style.width = percent + '%';

            // Update bar color / status based on thresholds
            if (percent > 50) {
                // green
                timeBarFill.style.background = ''; // use default / CSS var color if desired
                timerStatus.textContent = '';
            } else if (percent > 20) {
                // yellow / warning
                timeBarFill.style.background = '#e0a800';
                timerStatus.textContent = 'Hurry up';
                timerStatus.className = 'time-warning';
            } else {
                // red / urgent
                timeBarFill.style.background = '#c82333';
                timerStatus.textContent = 'Almost out of time';
                timerStatus.className = 'time-ended';
            }
        }

        function handleTimeUp() {
            // Indicate end
            timerStatus.textContent = 'Time is up — submitting';
            timerStatus.className = 'time-ended';

            // Auto-submit the form. If user hasn't answered all, submission will proceed as usual.
            // Show small visual cue
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.style.display = 'inline-block';
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting... (time up)';

            // Submit form via POST
            // Using a short delay to allow UI to update (not required, but nicer)
            setTimeout(() => {
                // Remove check to confirm on beforeunload / any handlers
                form.submit();
            }, 600);
        }

        function showQuestion(questionNum) {
            // Hide all questions
            document.querySelectorAll('.question-block').forEach(q => {
                q.style.display = 'none';
            });
            
            // Show current question
            const el = document.getElementById('question-' + questionNum);
            if (el) el.style.display = 'block';
            
            // Update progress
            document.getElementById('currentQuestion').textContent = questionNum + '/' + totalQuestions;
            document.getElementById('progressText').textContent = questionNum;
            document.getElementById('progressBar').style.width = ((questionNum / totalQuestions) * 100) + '%';
            
            // Update navigation buttons
            document.getElementById('prevBtn').disabled = questionNum === 1;
            
            if (questionNum === totalQuestions) {
                document.getElementById('nextBtn').style.display = 'none';
                document.getElementById('submitBtn').style.display = 'inline-block';
            } else {
                document.getElementById('nextBtn').style.display = 'inline-block';
                document.getElementById('submitBtn').style.display = 'none';
                document.getElementById('nextBtn').textContent = 'Next';
            }
            
            // If it's the last question, change next button text
            if (questionNum === totalQuestions - 1) {
                document.getElementById('nextBtn').textContent = 'Last Question';
            }
        }
        
        function selectOption(optionElement, questionId, optionId) {
            // Remove selected class from all options in this question
            const questionBlock = optionElement.closest('.question-block');
            questionBlock.querySelectorAll('.option').forEach(opt => {
                opt.classList.remove('selected');
                const radio = opt.querySelector('input[type="radio"]');
                if (radio) radio.checked = false;
            });
            
            // Select this option
            optionElement.classList.add('selected');
            const radio = optionElement.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
            
            // Store answer
            answers[questionId] = optionId;
            // Update progress UI
            updateProgress();
        }
        
        function nextQuestion() {
            if (currentQuestion < totalQuestions) {
                currentQuestion++;
                showQuestion(currentQuestion);
            }
        }
        
        function previousQuestion() {
            if (currentQuestion > 1) {
                currentQuestion--;
                showQuestion(currentQuestion);
            }
        }
        
        function updateProgress() {
            const answered = Object.keys(answers).length;
            const progress = (answered / totalQuestions) * 100;
            document.getElementById('progressBar').style.width = progress + '%';
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            showQuestion(1);
            console.log('Quiz loaded with', totalQuestions, 'questions');
            // Start timer
            startTimer();

            // Prevent accidental navigation (optional)
            window.addEventListener('beforeunload', function(e) {
                // If timer still running and unanswered questions exist, warn user
                const answered = Object.keys(answers).length;
                if (answered < totalQuestions && timeRemainingSeconds > 0) {
                    const msg = 'You have unanswered questions — are you sure you want to leave?';
                    e.preventDefault();
                    e.returnValue = msg;
                    return msg;
                }
            });
        });
        
        // Form submission
        document.getElementById('quizForm').addEventListener('submit', function(e) {
            stopTimer(); // stop timer when form submitted manually
            const answered = Object.keys(answers).length;
            if (answered < totalQuestions) {
                // Let the user confirm if they want to submit early.
                // We show a native confirm; if they cancel, keep timer running.
                if (!confirm(`You have answered ${answered} out of ${totalQuestions} questions. Submit anyway?`)) {
                    e.preventDefault();
                    // If they cancel the manual submit, restart timer if needed
                    if (!timerInterval && timeRemainingSeconds > 0) {
                        startTimer();
                    }
                    return;
                }
            }

            // Show loading state
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.textContent = 'Submitting...';
        });
    </script>
</body>
</html>
