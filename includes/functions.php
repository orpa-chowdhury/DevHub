<?php
// Get user repositories
function getUserRepositories($conn, $user_id) {
    $sql = "SELECT r.*, 
            (SELECT COUNT(*) FROM stars WHERE repository_id = r.id) as star_count,
            (SELECT COUNT(*) FROM repositories WHERE forked_from = r.id) as fork_count
            FROM repositories r 
            WHERE r.user_id = ? 
            ORDER BY r.updated_at DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $repositories = [];
    while($row = $result->fetch_assoc()) {
        $repositories[] = $row;
    }
    
    return $repositories;
}

// Get repository by ID
function getRepository($conn, $repo_id) {
    $sql = "SELECT r.*, u.username,
            (SELECT COUNT(*) FROM stars WHERE repository_id = r.id) as star_count,
            (SELECT COUNT(*) FROM repositories WHERE forked_from = r.id) as fork_count
            FROM repositories r 
            JOIN users u ON r.user_id = u.id 
            WHERE r.id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows == 1) {
        return $result->fetch_assoc();
    }
    
    return null;
}

// Get repository files
function getRepositoryFiles($conn, $repo_id, $branch, $path = '') {
    $sql = "SELECT f.*, c.id as last_commit_id, c.message as last_commit_message 
            FROM files f 
            LEFT JOIN commits c ON f.last_commit_id = c.id 
            WHERE f.repository_id = ? AND f.branch = ? AND f.path LIKE ?
            ORDER BY f.is_directory DESC, f.name ASC";
    
    $path_pattern = $path . '%';
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $repo_id, $branch, $path_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $files = [];
    while($row = $result->fetch_assoc()) {
        // Only include files directly in this path, not in subdirectories
        $file_path = $row['path'];
        $relative_path = substr($file_path, strlen($path));
        
        // Skip files in subdirectories
        if($path != '' && strpos($relative_path, '/') !== false) {
            continue;
        }
        
        $files[] = $row;
    }
    
    return $files;
}

// Get repository branches
function getRepositoryBranches($conn, $repo_id) {
    $sql = "SELECT * FROM branches WHERE repository_id = ? ORDER BY is_default DESC, name ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $branches = [];
    while($row = $result->fetch_assoc()) {
        $branches[] = $row;
    }
    
    return $branches;
}

// Get repository commits
function getRepositoryCommits($conn, $repo_id, $branch, $limit = 10) {
    $sql = "SELECT c.*, u.username, u.email FROM commits c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.repository_id = ? AND c.branch = ? 
            ORDER BY c.created_at DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("isi", $repo_id, $branch, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $commits = [];
    while($row = $result->fetch_assoc()) {
        $commits[] = $row;
    }
    
    return $commits;
}

// Check if user is collaborator
function isCollaborator($conn, $repo_id, $user_id) {
    $sql = "SELECT * FROM collaborators WHERE repository_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $repo_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

// Check if user has starred repository
function hasStarred($conn, $repo_id, $user_id) {
    $sql = "SELECT * FROM stars WHERE repository_id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $repo_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->num_rows > 0;
}

// Get branch count for repository
function getBranchCount($conn, $repo_id) {
    $sql = "SELECT COUNT(*) as count FROM branches WHERE repository_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

// Get star count for repository
function getStarCount($conn, $repo_id) {
    $sql = "SELECT COUNT(*) as count FROM stars WHERE repository_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

// Get fork count for repository
function getForkCount($conn, $repo_id) {
    $sql = "SELECT COUNT(*) as count FROM repositories WHERE forked_from = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

// Get issue count for repository
function getIssueCount($conn, $repo_id) {
    $sql = "SELECT COUNT(*) as count FROM issues WHERE repository_id = ? AND status = 'open'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'];
}

// Get user activities
function getUserActivities($conn, $user_id, $limit = 10) {
    $sql = "SELECT a.*, r.name as repository_name, r.id as repository_id 
            FROM activities a 
            LEFT JOIN repositories r ON a.repository_id = r.id 
            WHERE a.user_id = ? 
            ORDER BY a.created_at DESC LIMIT ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $activities = [];
    while($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    
    return $activities;
}

// Log activity
function logActivity($conn, $user_id, $type, $repository_id = null, $details = '') {
    $sql = "INSERT INTO activities (user_id, repository_id, type, details, created_at) 
            VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $user_id, $repository_id, $type, $details);
    $stmt->execute();
}

// Format activity for display
function formatActivity($activity) {
    $type = $activity['type'];
    $details = $activity['details'];
    $repo_name = $activity['repository_name'] ?? '';
    $repo_id = $activity['repository_id'] ?? 0;
    
    $formatted = '';
    
    switch($type) {
        case 'create_repository':
            $formatted = "Created repository <a href='repository.php?id=$repo_id'>$repo_name</a>";
            break;
        case 'update_repository':
            $formatted = "Updated repository <a href='repository.php?id=$repo_id'>$repo_name</a>";
            break;
        case 'delete_repository':
            $formatted = "Deleted repository $details";
            break;
        case 'fork_repository':
            $formatted = "Forked repository <a href='repository.php?id=$repo_id'>$repo_name</a>";
            break;
        case 'star_repository':
            $formatted = "Starred repository <a href='repository.php?id=$repo_id'>$repo_name</a>";
            break;
        case 'unstar_repository':
            $formatted = "Unstarred repository <a href='repository.php?id=$repo_id'>$repo_name</a>";
            break;
        case 'commit':
            $formatted = "Pushed to <a href='repository.php?id=$repo_id'>$repo_name</a>: $details";
            break;
        case 'create_issue':
            $formatted = "Opened issue in <a href='repository.php?id=$repo_id'>$repo_name</a>: $details";
            break;
        case 'create_pull_request':
            $formatted = "Created pull request in <a href='repository.php?id=$repo_id'>$repo_name</a>: $details";
            break;
        case 'login':
        case 'signup':
        default:
            $formatted = $details;
            break;
    }
    
    return $formatted;
}

// Get activity icon
function getActivityIcon($type) {
    switch($type) {
        case 'create_repository':
            return 'fas fa-plus-circle';
        case 'update_repository':
            return 'fas fa-edit';
        case 'delete_repository':
            return 'fas fa-trash';
        case 'fork_repository':
            return 'fas fa-code-branch';
        case 'star_repository':
            return 'fas fa-star';
        case 'unstar_repository':
            return 'fas fa-star';
        case 'commit':
            return 'fas fa-code-commit';
        case 'create_issue':
            return 'fas fa-exclamation-circle';
        case 'create_pull_request':
            return 'fas fa-code-pull-request';
        case 'login':
            return 'fas fa-sign-in-alt';
        case 'signup':
            return 'fas fa-user-plus';
        default:
            return 'fas fa-circle';
    }
}

// Format time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if($diff < 60) {
        return 'just now';
    } elseif($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . ' minute' . ($minutes > 1 ? 's' : '') . ' ago';
    } elseif($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } elseif($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . ' week' . ($weeks > 1 ? 's' : '') . ' ago';
    } elseif($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . ' month' . ($months > 1 ? 's' : '') . ' ago';
    } else {
        $years = floor($diff / 31536000);
        return $years . ' year' . ($years > 1 ? 's' : '') . ' ago';
    }
}

function getCollaborators($conn, $repo_id) {
    $stmt = $conn->prepare("
        SELECT users.id AS user_id, users.username, collaborators.permission
        FROM collaborators
        JOIN users ON collaborators.user_id = users.id
        WHERE collaborators.repository_id = ?
    ");
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    return $stmt->get_result(); // Returns a MySQLi result set
}


function hasWriteAccess($conn, $repo_id, $user_id) {
    // Check if user is owner
    $stmt = $conn->prepare("SELECT user_id FROM repositories WHERE id = ?");
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['user_id'] == $user_id) {
            return true;
        }
    }
    $stmt->close();

    // Check if user is collaborator with write or admin permissions
    $stmt = $conn->prepare("SELECT permission FROM collaborators WHERE repo_id = ? AND user_id = ?");
    $stmt->bind_param("ii", $repo_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($collab = $result->fetch_assoc()) {
        if (in_array($collab['permission'], ['write', 'admin'])) {
            return true;
        }
    }
    $stmt->close();

    return false;
}

?>
