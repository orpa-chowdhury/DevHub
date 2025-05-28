<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Get repository ID from URL
$repo_id = $_GET['repo_id'] ?? 0;

// Fetch repository details
$repository = getRepository($conn, $repo_id);

// Check if repository exists
if(!$repository) {
    header("Location: index.php");
    exit();
}

// Check if user has access to this repository
if($repository['visibility'] == 'private' && (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $repository['user_id'] && !isCollaborator($conn, $repo_id, $_SESSION['user_id']))) {
    header("Location: index.php");
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Get stargazers
$sql = "SELECT u.id, u.username, u.email, s.created_at 
        FROM stars s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.repository_id = ? 
        ORDER BY s.created_at DESC 
        LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $repo_id, $per_page, $offset);
$stmt->execute();
$stargazers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count total stargazers
$sql = "SELECT COUNT(*) as total FROM stars WHERE repository_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $repo_id);
$stmt->execute();
$total_stars = $stmt->get_result()->fetch_assoc()['total'];
$total_pages = ceil($total_stars / $per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stargazers · <?php echo htmlspecialchars($repository['name']); ?> - DevHub</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="page-header">
            <h1>
                <a href="repository.php?id=<?php echo $repository['id']; ?>">
                    <?php echo htmlspecialchars($repository['username'] . '/' . $repository['name']); ?>
                </a>
                <span class="text-secondary">: Stargazers</span>
            </h1>
        </div>
        
        <div class="repository-nav">
            <ul>
                <li><a href="repository.php?id=<?php echo $repository['id']; ?>"><i class="fas fa-code"></i> Code</a></li>
                <li><a href="issues.php?repo_id=<?php echo $repository['id']; ?>"><i class="fas fa-exclamation-circle"></i> Issues</a></li>
                <li><a href="pull_requests.php?repo_id=<?php echo $repository['id']; ?>"><i class="fas fa-code-pull-request"></i> Pull requests</a></li>
                <li class="active"><a href="insights.php?repo_id=<?php echo $repository['id']; ?>"><i class="fas fa-chart-bar"></i> Insights</a></li>
            </ul>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-star"></i> <?php echo $total_stars; ?> Stargazers</h3>
            </div>
            <div class="card-body">
                <?php if(empty($stargazers)): ?>
                    <div class="empty-state">
                        <i class="fas fa-star"></i>
                        <h3>No stargazers yet</h3>
                        <p>Be the first to star this repository!</p>
                    </div>
                <?php else: ?>
                    <div class="user-list">
                        <?php foreach($stargazers as $user): ?>
                            <div class="user-item">
                                <div class="user-avatar">
                                    <img src="https://www.gravatar.com/avatar/<?php echo md5(strtolower(trim($user['email']))); ?>?s=60&d=mp" alt="<?php echo htmlspecialchars($user['username']); ?>" class="avatar">
                                </div>
                                <div class="user-info">
                                    <h4><a href="profile.php?username=<?php echo htmlspecialchars($user['username']); ?>"><?php echo htmlspecialchars($user['username']); ?></a></h4>
                                    <p class="text-muted">Starred <?php echo timeAgo($user['created_at']); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if($total_pages > 1): ?>
                        <div class="pagination mt-4">
                            <?php if($page > 1): ?>
                                <a href="?repo_id=<?php echo $repo_id; ?>&page=<?php echo $page - 1; ?>" class="btn btn-secondary">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <span class="pagination-info">Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
                            
                            <?php if($page < $total_pages): ?>
                                <a href="?repo_id=<?php echo $repo_id; ?>&page=<?php echo $page + 1; ?>" class="btn btn-secondary">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
</body>
</html>
