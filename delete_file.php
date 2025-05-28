<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Check if form was submitted
if($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: index.php");
    exit();
}

// Get repository ID, branch and file path
$repo_id = $_POST['repo_id'] ?? 0;
$branch = $_POST['branch'] ?? 'main';
$file_path = $_POST['path'] ?? '';
$commit_message = $_POST['commit_message'] ?? 'Delete ' . basename($file_path);

// Fetch repository details
$repository = getRepository($conn, $repo_id);

// Check if repository exists
if(!$repository) {
    header("Location: index.php");
    exit();
}

// Check if user has write access
$is_owner = $_SESSION['user_id'] == $repository['user_id'];
$is_collaborator = isCollaborator($conn, $repo_id, $_SESSION['user_id']);
$has_write_access = $is_owner || $is_collaborator;

if(!$has_write_access) {
    header("Location: repository.php?id=$repo_id");
    exit();
}

// Fetch file details
$sql = "SELECT * FROM files WHERE repository_id = ? AND branch = ? AND path = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $repo_id, $branch, $file_path);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    $_SESSION['error_message'] = "File not found";
    header("Location: repository.php?id=$repo_id&branch=$branch");
    exit();
}

$file = $result->fetch_assoc();

// Start transaction
$conn->begin_transaction();

try {
    // Create commit
    $sql = "INSERT INTO commits (repository_id, user_id, branch, message, created_at) 
            VALUES (?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiss", $repo_id, $_SESSION['user_id'], $branch, $commit_message);
    $stmt->execute();
    $commit_id = $stmt->insert_id;
    
    // Record file change in commit
    $sql = "INSERT INTO commit_files (commit_id, file_path, change_type, content_before) 
            VALUES (?, ?, 'delete', ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $commit_id, $file_path, $file['content']);
    $stmt->execute();
    
    // Delete file
    $sql = "DELETE FROM files WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $file['id']);
    $stmt->execute();
    
    // Update repository updated_at
    $sql = "UPDATE repositories SET updated_at = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    
    // Log activity
    logActivity($conn, $_SESSION['user_id'], 'commit', $repo_id, $commit_message);
    
    // Commit transaction
    $conn->commit();
    
    $_SESSION['success_message'] = "File deleted successfully";
    
    // Get parent directory path
    $parent_path = dirname($file_path);
    $redirect_path = $parent_path == '.' ? '' : $parent_path;
    
    // Redirect to repository or parent directory
    header("Location: repository.php?id=$repo_id&branch=$branch" . ($redirect_path ? "&path=$redirect_path" : ""));
    exit();
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    $_SESSION['error_message'] = "Error deleting file: " . $e->getMessage();
    header("Location: view_file.php?repo_id=$repo_id&branch=$branch&path=$file_path");
    exit();
}
?>
