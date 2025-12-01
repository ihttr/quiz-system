<?php
require_once '../../src/config.php';
require_once '../../src/auth.php';
redirectIfNotAdmin();

$page = $_GET['page'] ?? 1;
$perPage = 10;

// Handle make admin/remove admin actions
if (isset($_POST['make_admin'])) {
    $userId = $_POST['user_id'];
    if (makeUserAdmin($userId)) {
        header("Location: users.php?success=made_admin");
        exit();
    } else {
        $error = "Failed to make user admin";
    }
}

if (isset($_POST['remove_admin'])) {
    $userId = $_POST['user_id'];
    if (removeUserAdmin($userId)) {
        header("Location: users.php?success=removed_admin");
        exit();
    } else {
        $error = "Failed to remove admin privileges";
    }
}

// Check if getAllUsers function exists
if (!function_exists('getAllUsers')) {
    die("Error: getAllUsers function not found. Please check auth.php");
}

try {
    $users = getAllUsers($page, $perPage);

    // Get total users for pagination
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $totalUsers = $stmt->fetchColumn();
    $totalPages = ceil($totalUsers / $perPage);
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $users = [];
    $totalPages = 0;
}
?>

<!DOCTYPE html>
<html lang="en" data-theme="<?php echo getUserTheme(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
        }
        
        .users-table th,
        .users-table td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .users-table th {
            background-color: var(--card-bg);
            font-weight: bold;
        }
        
        .role-admin {
            background-color: rgba(40, 167, 69, 0.1);
            color: var(--success-color);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .role-user {
            background-color: rgba(108, 117, 125, 0.1);
            color: var(--secondary-color);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
        }
        
        .pagination {
            display: flex;
            gap: 0.5rem;
            justify-content: center;
            margin: 2rem 0;
        }
        
        .pagination a {
            padding: 0.5rem 1rem;
            background-color: var(--card-bg);
            border-radius: 4px;
            text-decoration: none;
            color: var(--text-color);
        }
        
        .pagination a.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .text-muted {
            color: var(--secondary-color);
            font-style: italic;
        }
        
        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
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
                    <li><a href="users.php" class="active">Users</a></li>
                    <li><a href="quizzes.php">Quizzes</a></li>
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
        <h1>Manage Users</h1>
        
        <?php if (isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($_GET['success'])): ?>
            <div class="alert success">
                <?php 
                switch($_GET['success']) {
                    case 'made_admin': echo 'User has been made an admin!'; break;
                    case 'removed_admin': echo 'User admin privileges removed!'; break;
                }
                ?>
            </div>
        <?php endif; ?>

        <div class="quiz-card">
            <div class="table-responsive">
                <table class="users-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Attempts</th>
                            <th>Joined</th>
                            <th>Last Activity</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="role-<?php echo $user['role']; ?>">
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user['attempt_count'] ?? 0; ?></td>
                                    <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <?php echo $user['last_activity'] ? date('M j, Y', strtotime($user['last_activity'])) : 'Never'; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['role'] === 'user'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <button type="submit" name="make_admin" class="btn btn-sm btn-success">Make Admin</button>
                                            </form>
                                        <?php else: ?>
                                            <?php if ($user['id'] != $_SESSION['user_id']): // Don't allow removing own admin ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                    <button type="submit" name="remove_admin" class="btn btn-sm btn-warning">Remove Admin</button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">Current User</span>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">No users found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>">Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>