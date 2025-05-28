<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$repo_id = $_POST['repo_id'] ?? 0;
$user_id = $_POST['user_id'] ?? 0;
$permission = $_POST['permission'] ?? 'read';

// Validate inputs
if (!$repo_id || !$user_id) {
    $_SESSION['error'] = "Invalid input.";
    header("Location: repository_settings.php?id=$repo_id");
    exit();
}

// Check if current user has write/admin access to repo
if (!hasWriteAccess($conn, $repo_id, $_SESSION['user_id'])) {
    $_SESSION['error'] = "You do not have permission.";
    header("Location: repository_settings.php?id=$repo_id");
    exit();
}

// Update permission
$stmt = $conn->prepare("UPDATE collaborators SET permission = ? WHERE repo_id = ? AND user_id = ?");
$stmt->bind_param("sii", $permission, $repo_id, $user_id);
$stmt->execute();

$_SESSION['success'] = "Collaborator permission updated.";
header("Location: repository_settings.php?id=$repo_id");
exit();
?>
