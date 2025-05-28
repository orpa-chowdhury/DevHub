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

// Get repository ID, branch and path
$repo_id = $_POST['repo_id'] ?? 0;
$branch = $_POST['branch'] ?? 'main';
$path = $_POST['path'] ?? '';
$commit_message = $_POST['commit_message'] ?? 'Add file via upload';

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

// Check if file was uploaded
if(!isset($_FILES['file']) || $_FILES['file']['error'] != UPLOAD_ERR_OK) {
    $_SESSION['error_message'] = "Error uploading file: " . ($_FILES['file']['error'] ?? 'No file uploaded');
    header("Location: upload_file.php?repo_id=$repo_id&branch=$branch" . ($path ? "&path=$path" : ""));
    exit();
}

// Get file details
$file_name = $_FILES['file']['name'];
$file_tmp = $_FILES['file']['tmp_name'];
$file_size = $_FILES['file']['size'];
$file_type = $_FILES['file']['type'];

// Read file content
$file_content = file_get_contents($file_tmp);

// Construct full path
$full_path = $path ? $path . '/' . $file_name : $file_name;

// Check if file already exists
$sql = "SELECT id FROM files WHERE repository_id = ? AND branch = ? AND path = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $repo_id, $branch, $full_path);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows > 0) {
    $_SESSION['error_message'] = "A file with this name already exists in this location";
    header("Location: upload_file.php?repo_id=$repo_id&branch=$branch" . ($path ? "&path=$path" : ""));
    exit();
}

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
    
    // Add file - for binary files, we'll store the file type and size as metadata
    $metadata = json_encode([
        'type' => $file_type,
        'size' => $file_size
    ]);
    
    $sql = "INSERT INTO files (repository_id, branch, path, name, content, is_directory, last_commit_id, created_at, updated_at, metadata) 
            VALUES (?, ?, ?, ?, ?, 0, ?, NOW(), NOW(), ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("issssiss", $repo_id, $branch, $full_path, $file_name, $file_content, $commit_id, $metadata);
    $stmt->execute();
    
    // Record file change in commit
    $sql = "INSERT INTO commit_files (commit_id, file_path, change_type, content_after) 
            VALUES (?, ?, 'add', ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $commit_id, $full_path, $file_content);
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
    
    $_SESSION['success_message'] = "File uploaded successfully";
    
    // Redirect to repository
    header("Location: repository.php?id=$repo_id&branch=$branch" . ($path ? "&path=$path" : ""));
    exit();
} catch (Exception $e) {
    // Rollback transaction on error
    $conn->rollback();
    
    $_SESSION['error_message'] = "Error uploading file: " . $e->getMessage();
    header("Location: upload_file.php?repo_id=$repo_id&branch=$branch" . ($path ? "&path=$path" : ""));
    exit();
}
?>
