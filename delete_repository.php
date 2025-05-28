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

// Get repository ID
$repo_id = $_POST['repo_id'] ?? 0;

// Fetch repository details
$repository = getRepository($conn, $repo_id);

// Check if repository exists
if(!$repository) {
    header("Location: index.php");
    exit();
}

// Check if user is owner
if($_SESSION['user_id'] != $repository['user_id']) {
    header("Location: repository.php?id=$repo_id");
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Delete repository files
    $sql = "DELETE FROM files WHERE repository_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    
    // Delete repository branches
    $sql = "DELETE FROM branches WHERE repository_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    
    // Delete repository commits
    $sql = "DELETE FROM commits WHERE repository_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    
    // Delete repository issues
    $sql = "DELETE FROM issues WHERE repository_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    
    // Delete repository pull requests
    $sql = "DELETE FROM pull_requests WHERE repository_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    
    // Delete repository collaborators
    $sql = "DELETE FROM collaborators WHERE repository_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    
    // Delete repository stars
    $sql = "DELETE FROM stars WHERE repository_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    
    // Delete repository activities
    $sql = "DELETE FROM activities WHERE repository_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    
    // Delete repository
    $sql = "DELETE FROM repositories WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $repo_id);
    $stmt->execute();
    
    // Log activity
    logActivity($conn, $_SESSION['user_id'], 'delete_repository', null, 'Deleted repository ' . $repository['name']);
    
    // Commit transaction
    $conn->commit();
    
    // Set success message in session
    $_SESSION['success_message'] = "Repository deleted successfully";
    
    // Redirect to dashboard
    header("Location: index.php");
    exit();
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    // Set error message in session
    $_SESSION['error_message'] = "Error deleting repository: " . $e->getMessage();
    
    // Redirect back to edit page
    header("Location: edit_repository.php?id=$repo_id");
    exit();
}
?>
