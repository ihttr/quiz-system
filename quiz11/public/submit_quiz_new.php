<?php
require_once '../src/config.php';
require_once '../src/auth.php';
redirectIfNotLoggedIn();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: quiz.php");
    exit();
}

$quizId = $_POST['quiz_id'] ?? null;
$answers = $_POST['answers'] ?? [];

if (!$quizId) {
    header("Location: quiz.php?error=no_quiz_id");
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Calculate score
    $score = 0;
    $totalQuestions = 0;
    
    // Get total questions count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM questions WHERE quiz_id = ?");
    $stmt->execute([$quizId]);
    $totalQuestions = $stmt->fetchColumn();
    
    // Create quiz attempt
    $stmt = $pdo->prepare("
        INSERT INTO quiz_attempts (user_id, quiz_id, total_questions) 
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$_SESSION['user_id'], $quizId, $totalQuestions]);
    $attemptId = $pdo->lastInsertId();
    
    // Process each answer
    foreach ($answers as $questionId => $selectedOptionId) {
        // Check if answer is correct
        $stmt = $pdo->prepare("
            SELECT is_correct FROM options WHERE id = ?
        ");
        $stmt->execute([$selectedOptionId]);
        $isCorrect = $stmt->fetchColumn();
        
        if ($isCorrect) {
            $score++;
        }
        
        // Save answer
        $stmt = $pdo->prepare("
            INSERT INTO answers (attempt_id, question_id, selected_option_id, is_correct) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$attemptId, $questionId, $selectedOptionId, $isCorrect]);
    }
    
    // Update attempt with final score
    $stmt = $pdo->prepare("
        UPDATE quiz_attempts SET score = ? WHERE id = ?
    ");
    $stmt->execute([$score, $attemptId]);
    
    $pdo->commit();
    
    // Redirect to results page
    header("Location: results.php?attempt_id=" . $attemptId);
    exit();
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Quiz submission error: " . $e->getMessage());
    header("Location: quiz.php?error=submission_failed");
    exit();
}
?>