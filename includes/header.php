<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/db_connect.php';

// Get user info for navbar (profile picture, username, email)
$user_nav = null;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $sql = "SELECT username, email, profile_picture FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_nav = $stmt->get_result()->fetch_assoc();

    // Ensure session has updated info
    $_SESSION['username'] = $user_nav['username'];
    $_SESSION['email'] = $user_nav['email'];
}
?>

<header class="main-header">
    <div class="container">
        <div class="header-left">
            <a href="index.php" class="logo">
                <i class="fas fa-code-branch"></i> DevHub
            </a>
            
            <div class="search-container">
                <form action="search.php" method="GET">
                    <input type="text" name="q" placeholder="Search repositories, users..." class="search-input">
                    <button type="submit" class="search-button">
                        <i class="fas fa-search"></i>
                    </button>
                </form>
            </div>
        </div>
        
        <div class="header-right">
            <nav class="main-nav">
                <ul>
                    <li><a href="explore.php">Explore</a></li>
                    <?php if(isset($_SESSION['user_id'])): ?>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle">
                                <i class="fas fa-plus"></i> <i class="fas fa-caret-down"></i>
                            </a>
                            <div class="dropdown-menu">
                                <a href="create_repository.php">New repository</a>
                                <a href="import_repository.php">Import repository</a>
                                <a href="create_issue.php">New issue</a>
                            </div>
                        </li>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle">
                                <i class="fas fa-bell"></i>
                            </a>
                            <div class="dropdown-menu notifications-menu">
                                <div class="dropdown-header">Notifications</div>
                                <?php
                                $sql = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
                                $stmt = $conn->prepare($sql);
                                $stmt->bind_param("i", $user_id);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                
                                if($result->num_rows > 0):
                                    while($notification = $result->fetch_assoc()):
                                ?>
                                <a href="<?php echo $notification['link']; ?>" class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                                    <div class="notification-icon">
                                        <i class="<?php echo getNotificationIcon($notification['type']); ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                        <span class="notification-time"><?php echo timeAgo($notification['created_at']); ?></span>
                                    </div>
                                </a>
                                <?php
                                    endwhile;
                                else:
                                ?>
                                <div class="empty-state">
                                    <p>No notifications yet</p>
                                </div>
                                <?php endif; ?>
                                <div class="dropdown-footer">
                                    <a href="notifications.php">View all notifications</a>
                                </div>
                            </div>
                        </li>
                        <li class="dropdown">
                            <a href="#" class="dropdown-toggle user-dropdown">
                                <?php
                                $avatar_src = 'https://www.gravatar.com/avatar/' . md5(strtolower(trim($user_nav['email']))) . '?s=30&d=mp';
                                if (!empty($user_nav['profile_picture']) && file_exists('uploads/profile_pics/' . $user_nav['profile_picture'])) {
                                    $avatar_src = 'uploads/profile_pics/' . $user_nav['profile_picture'];
                                }
                                ?>
                                <img src="<?php echo $avatar_src; ?>" alt="<?php echo htmlspecialchars($user_nav['username']); ?>" class="avatar">
                                <i class="fas fa-caret-down"></i>
                            </a>
                            <div class="dropdown-menu">
                                <div class="dropdown-header">
                                    Signed in as <strong><?php echo htmlspecialchars($user_nav['username']); ?></strong>
                                </div>
                                <a href="profile.php?username=<?php echo htmlspecialchars($user_nav['username']); ?>">Your profile</a>
                                <a href="repositories.php">Your repositories</a>
                                <a href="stars.php">Your stars</a>
                                <div class="dropdown-divider"></div>
                                <a href="settings.php">Settings</a>
                                <a href="logout.php">Sign out</a>
                            </div>
                        </li>
                    <?php else: ?>
                        <li><a href="login.php">Sign in</a></li>
                        <li><a href="signup.php" class="btn btn-primary">Sign up</a></li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    </div>
</header>

<?php
// Helper function for notification icons
function getNotificationIcon($type) {
    switch($type) {
        case 'pull_request':
            return 'fas fa-code-pull-request';
        case 'issue':
            return 'fas fa-exclamation-circle';
        case 'mention':
            return 'fas fa-at';
        case 'star':
            return 'fas fa-star';
        case 'fork':
            return 'fas fa-code-branch';
        default:
            return 'fas fa-bell';
    }
}
?>
