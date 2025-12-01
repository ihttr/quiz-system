<?php
require_once '../../src/config.php';
require_once '../../src/auth.php';
redirectIfNotAdmin();



if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $time_limit = $_POST['time_limit'] ?: 0;
    $passing_score = $_POST['passing_score'] ?: 60;
    $allow_retakes = isset($_POST['allow_retakes']) ? 1 : 0;
    
    
    // Debug: Check what we're receiving
    error_log("Creating quiz: " . $title);
    
    try {
        $pdo->beginTransaction();
        
        // Insert quiz
        $stmt = $pdo->prepare("
            INSERT INTO quizzes (title, description, time_limit, passing_score, 
                                allow_retakes, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $title, $description, $time_limit, $passing_score,
             $allow_retakes,  $_SESSION['user_id']
        ]);
        
        $quizId = $pdo->lastInsertId();
        error_log("Quiz created with ID: " . $quizId);
        
        // Insert questions if they exist
        if (isset($_POST['questions']) && is_array($_POST['questions'])) {
            foreach ($_POST['questions'] as $questionData) {
                if (!empty($questionData['text'])) {
                    // Insert question (removed hint, explanation, difficulty)
                    $stmt = $pdo->prepare("
                        INSERT INTO questions (quiz_id, question_text, question_type, points)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $quizId,
                        $questionData['text'],
                        $questionData['type'] ?? 'multiple_choice',
                        $questionData['points'] ?? 1
                    ]);
                    
                    $questionId = $pdo->lastInsertId();
                    
                    // Insert options
                    if (isset($questionData['options']) && is_array($questionData['options'])) {
                        foreach ($questionData['options'] as $optionIndex => $optionText) {
                            if (!empty($optionText)) {
                                $isCorrect = (isset($questionData['correct_option']) && $questionData['correct_option'] == $optionIndex) ? 1 : 0;
                                $stmt = $pdo->prepare("
                                    INSERT INTO options (question_id, option_text, is_correct)
                                    VALUES (?, ?, ?)
                                ");
                                $stmt->execute([$questionId, $optionText, $isCorrect]);
                            }
                        }
                    }
                }
            }
        }
        
        $pdo->commit();
        header("Location: quizzes.php?success=quiz_created");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error creating quiz: " . $e->getMessage();
        error_log("Quiz creation error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo getUserTheme(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Quiz - Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .form-section {
            background: var(--card-bg);
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1.5rem 0;
            border: 1px solid var(--border-color);
        }
        
        .question-block {
            background: var(--bg-color);
            padding: 1.5rem;
            border-radius: 8px;
            margin: 1rem 0;
            border: 1px solid var(--border-color);
            position: relative;
        }
        
        .option-row {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            margin: 0.5rem 0;
        }
        
        .option-row input[type="text"] {
            flex: 1;
            padding: 0.5rem;
        }
        
        .btn-remove {
            background: var(--danger-color);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9rem;
        }
        
        .btn-add {
            background: var(--success-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            margin: 0.5rem 0;
            font-size: 1rem;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .form-actions {
            text-align: center;
            margin: 2rem 0;
            padding: 1rem;
            background: var(--card-bg);
            border-radius: 8px;
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
                    <li><a href="quizzes.php">Quizzes</a></li>
                    <li><a href="create_quiz.php" class="active">Create Quiz</a></li>
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
        <h1>Create New Quiz</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" id="quizForm" onsubmit="return validateForm()">
            <!-- Basic Quiz Info -->
            <div class="form-section">
                <h2>Basic Information</h2>
                <div class="form-group">
                    <label for="title">Quiz Title *</label>
                    <input type="text" id="title" name="title" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3"></textarea>
                </div>
            </div>

            <!-- Quiz Settings -->
            <div class="form-section">
                <h2>Quiz Settings</h2>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="time_limit">Time Limit (minutes)</label>
                        <input type="number" id="time_limit" name="time_limit" min="0" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="passing_score">Passing Score (%)</label>
                        <input type="number" id="passing_score" name="passing_score" min="0" max="100" value="60">
                    </div>
                
                <div class="form-group">
                    <label>
                        <input type="checkbox" name="allow_retakes" value="1" checked>
                        Allow Retakes
                    </label>
                </div>
            </div>

            <!-- Questions -->
            <div class="form-section">
                <h2>Questions</h2>
                <div id="questions-container">
                    <!-- Questions will be added here dynamically -->
                </div>
                
                <button type="button" class="btn btn-primary" onclick="addQuestion()" id="addQuestionBtn">+ Add Question</button>
                <div id="question-count" style="margin-top: 10px; font-style: italic;">No questions added yet</div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-success" style="padding: 1rem 2rem; font-size: 1.1rem;">Create Quiz</button>
                <a href="quizzes.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>

    <script>
        let questionCount = 0;
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page loaded, initializing quiz form...');
            addQuestion(); // Add first question automatically
            updateQuestionCount();
        });
        
        function addQuestion() {
            questionCount++;
            const container = document.getElementById('questions-container');
            
            const questionHTML = `
                <div class="question-block" id="question-${questionCount}">
                    <h3>Question ${questionCount}</h3>
                    
                    <div class="form-group">
                        <label>Question Text *</label>
                        <textarea name="questions[${questionCount}][text]" rows="3" required placeholder="Enter your question here..."></textarea>
                    </div>
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Question Type</label>
                            <select name="questions[${questionCount}][type]" onchange="toggleQuestionType(${questionCount})">
                                <option value="multiple_choice">Multiple Choice</option>
                                <option value="true_false">True/False</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Points</label>
                            <input type="number" name="questions[${questionCount}][points]" value="1" min="1">
                        </div>
                    </div>
                    
                    <div id="options-container-${questionCount}">
                        <!-- Options will be added here -->
                    </div>
                    
                    <button type="button" class="btn-add" onclick="addOption(${questionCount})">+ Add Option</button>
                    
                    <div class="form-group">
                        <label>Correct Option *</label>
                        <select name="questions[${questionCount}][correct_option]" id="correct-option-${questionCount}" required>
                            <!-- Options will be populated here -->
                        </select>
                    </div>
                    
                    <button type="button" class="btn-remove" onclick="removeQuestion(${questionCount})">Remove Question</button>
                </div>
            `;
            
            container.insertAdjacentHTML('beforeend', questionHTML);
            console.log(`Added question ${questionCount}`);
            
            // Add initial options
            addOption(questionCount);
            addOption(questionCount);
            
            updateQuestionCount();
        }
        
        function addOption(questionId) {
            const optionsContainer = document.getElementById(`options-container-${questionId}`);
            const correctOptionSelect = document.getElementById(`correct-option-${questionId}`);
            
            const optionIndex = optionsContainer.children.length;
            
            const optionHTML = `
                <div class="option-row" id="option-${questionId}-${optionIndex}">
                    <input type="text" 
                           name="questions[${questionId}][options][${optionIndex}]" 
                           placeholder="Option ${optionIndex + 1}" 
                           required>
                    <button type="button" class="btn-remove" onclick="removeOption(${questionId}, ${optionIndex})">×</button>
                </div>
            `;
            
            optionsContainer.insertAdjacentHTML('beforeend', optionHTML);
            
            // Update correct option dropdown
            updateCorrectOptions(questionId);
            
            console.log(`Added option ${optionIndex} to question ${questionId}`);
        }
        
        function removeOption(questionId, optionIndex) {
            const optionElement = document.getElementById(`option-${questionId}-${optionIndex}`);
            if (optionElement) {
                optionElement.remove();
                updateCorrectOptions(questionId);
                console.log(`Removed option ${optionIndex} from question ${questionId}`);
            }
        }
        
        function updateCorrectOptions(questionId) {
            const correctOptionSelect = document.getElementById(`correct-option-${questionId}`);
            const optionsContainer = document.getElementById(`options-container-${questionId}`);
            
            correctOptionSelect.innerHTML = '';
            
            // Get all option inputs
            const optionInputs = optionsContainer.querySelectorAll('input[type="text"]');
            
            if (optionInputs.length === 0) {
                correctOptionSelect.innerHTML = '<option value="">No options available</option>';
                return;
            }
            
            optionInputs.forEach((input, index) => {
                const optionValue = input.value || `Option ${index + 1}`;
                const option = document.createElement('option');
                option.value = index;
                option.textContent = optionValue;
                correctOptionSelect.appendChild(option);
            });
        }
        
        function removeQuestion(questionId) {
            const questionElement = document.getElementById(`question-${questionId}`);
            if (questionElement) {
                questionElement.remove();
                updateQuestionCount();
                console.log(`Removed question ${questionId}`);
            }
        }
        
        function toggleQuestionType(questionId) {
            const questionType = document.querySelector(`select[name="questions[${questionId}][type]"]`).value;
            console.log(`Question ${questionId} type changed to: ${questionType}`);
            
            if (questionType === 'true_false') {
                // Auto-populate true/false options
                const optionsContainer = document.getElementById(`options-container-${questionId}`);
                optionsContainer.innerHTML = '';
                
                // Add True and False options
                const trueFalseOptions = [
                    { value: 'True', index: 0 },
                    { value: 'False', index: 1 }
                ];
                
                trueFalseOptions.forEach(opt => {
                    const optionHTML = `
                        <div class="option-row" id="option-${questionId}-${opt.index}">
                            <input type="text" 
                                   name="questions[${questionId}][options][${opt.index}]" 
                                   value="${opt.value}" 
                                   readonly
                                   style="background-color: #f0f0f0">
                            <span style="color: #666; font-style: italic;">(Auto-generated)</span>
                        </div>
                    `;
                    optionsContainer.insertAdjacentHTML('beforeend', optionHTML);
                });
                
                updateCorrectOptions(questionId);
            } else {
                // For multiple choice, ensure there are at least two options (if none)
                const optionsContainer = document.getElementById(`options-container-${questionId}`);
                if (optionsContainer.children.length === 0) {
                    addOption(questionId);
                    addOption(questionId);
                }
            }
        }
        
        function updateQuestionCount() {
            const questionCountElement = document.getElementById('question-count');
            const questions = document.querySelectorAll('.question-block');
            const count = questions.length;
            
            if (count === 0) {
                questionCountElement.textContent = 'No questions added yet';
                questionCountElement.style.color = 'var(--danger-color)';
            } else {
                questionCountElement.textContent = `${count} question(s) added`;
                questionCountElement.style.color = 'var(--success-color)';
            }
        }
        
        function validateForm() {
            const title = document.getElementById('title').value.trim();
            const questions = document.querySelectorAll('.question-block');
            
            if (!title) {
                alert('Please enter a quiz title');
                return false;
            }
            
            if (questions.length === 0) {
                alert('Please add at least one question');
                return false;
            }
            
            // Validate each question
            for (let i = 0; i < questions.length; i++) {
                const questionText = questions[i].querySelector('textarea[name*="[text]"]').value.trim();
                const options = questions[i].querySelectorAll('input[name*="[options]"]');
                let hasOptions = false;
                
                options.forEach(opt => {
                    if (opt.value.trim()) hasOptions = true;
                });
                
                if (!questionText) {
                    alert(`Question ${i + 1} is missing text`);
                    return false;
                }
                
                if (!hasOptions) {
                    alert(`Question ${i + 1} has no options`);
                    return false;
                }
            }
            
            console.log('Form validation passed, submitting...');
            return true;
        }
    </script>
</body>
</html>
