<?php
require_once 'config.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function getUserTheme() {
    return $_SESSION['theme'] ?? 'light';
}

function redirectIfNotLoggedIn() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

function redirectIfNotAdmin() {
    redirectIfNotLoggedIn();
    if (!isAdmin()) {
        header("Location: ../quiz.php");
        exit();
    }
}

// Admin-specific functions
function getAdminStats() {
    global $pdo;
    
    $stats = [];
    
    // Total users
    $stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users");
    $stats['total_users'] = $stmt->fetchColumn();
    
    // Total quizzes
    $stmt = $pdo->query("SELECT COUNT(*) as total_quizzes FROM quizzes WHERE is_active = TRUE");
    $stats['total_quizzes'] = $stmt->fetchColumn();
    
    // Total attempts
    $stmt = $pdo->query("SELECT COUNT(*) as total_attempts FROM quiz_attempts");
    $stats['total_attempts'] = $stmt->fetchColumn();
    
    // Recent signups (last 7 days)
    $stmt = $pdo->query("SELECT COUNT(*) as recent_signups FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_signups'] = $stmt->fetchColumn();
    
    return $stats;
}

function getAllUsers($page = 1, $perPage = 10) {
    global $pdo;
    
    $offset = ($page - 1) * $perPage;
    
    $stmt = $pdo->prepare("
        SELECT u.*, 
               (SELECT COUNT(*) FROM quiz_attempts WHERE user_id = u.id) as attempt_count,
               (SELECT MAX(completed_at) FROM quiz_attempts WHERE user_id = u.id) as last_activity
        FROM users u 
        ORDER BY u.created_at DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->bindValue(1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(2, $offset, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

function makeUserAdmin($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
    return $stmt->execute([$userId]);
}

function removeUserAdmin($userId) {
    global $pdo;
    
    $stmt = $pdo->prepare("UPDATE users SET role = 'user' WHERE id = ?");
    return $stmt->execute([$userId]);
}

function getUserPreferences($userId) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->execute([$userId]);
    $prefs = $stmt->fetch();
    
    if (!$prefs) {
        // Create default preferences
        $stmt = $pdo->prepare("INSERT INTO user_preferences (user_id) VALUES (?)");
        $stmt->execute([$userId]);
        
        $stmt = $pdo->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
        $stmt->execute([$userId]);
        $prefs = $stmt->fetch();
    }
    
    return $prefs;
}

function updateUserProgress($userId, $quizId, $attemptId, $score) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT * FROM user_progress 
        WHERE user_id = ? AND quiz_id = ?
    ");
    $stmt->execute([$userId, $quizId]);
    $progress = $stmt->fetch();
    
    if ($progress) {
        $bestScore = max($progress['best_score'], $score);
        $stmt = $pdo->prepare("
            UPDATE user_progress 
            SET last_attempt_id = ?, best_score = ?, attempts_count = attempts_count + 1,
                completed = ?, updated_at = CURRENT_TIMESTAMP
            WHERE user_id = ? AND quiz_id = ?
        ");
        $completed = ($score >= 60) ? 1 : 0;
        $stmt->execute([$attemptId, $bestScore, $completed, $userId, $quizId]);
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO user_progress (user_id, quiz_id, last_attempt_id, best_score, attempts_count, completed)
            VALUES (?, ?, ?, ?, 1, ?)
        ");
        $completed = ($score >= 60) ? 1 : 0;
        $stmt->execute([$userId, $quizId, $attemptId, $score, $completed]);
    }
}

function canRetakeQuiz($userId, $quizId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT q.allow_retakes, q.max_attempts, 
               COALESCE(up.attempts_count, 0) as attempts_count 
        FROM quizzes q 
        LEFT JOIN user_progress up ON up.quiz_id = q.id AND up.user_id = ?
        WHERE q.id = ?
    ");
    $stmt->execute([$userId, $quizId]);
    $data = $stmt->fetch();
    
    if (!$data) return true; // No quiz found
    
    if (!$data['allow_retakes']) return false;
    
    
    return true;
}

// Quiz management functions
function getQuizStats($quizId) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_attempts,
            AVG(score) as avg_score,
            MAX(score) as high_score,
            MIN(score) as low_score
        FROM quiz_attempts 
        WHERE quiz_id = ?
    ");
    $stmt->execute([$quizId]);
    return $stmt->fetch();
}

function getRecentActivity($limit = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT u.username, q.title, qa.score, qa.completed_at 
        FROM quiz_attempts qa
        JOIN users u ON qa.user_id = u.id
        JOIN quizzes q ON qa.quiz_id = q.id
        ORDER BY qa.completed_at DESC 
        LIMIT ?
    ");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}







?>