<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Get repository ID, branch and file path from URL
$repo_id = $_GET['repo_id'] ?? 0;
$branch = $_GET['branch'] ?? 'main';
$file_path = $_GET['path'] ?? '';

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

// Fetch file details
$sql = "SELECT * FROM files WHERE repository_id = ? AND branch = ? AND path = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $repo_id, $branch, $file_path);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    header("Location: repository.php?id=$repo_id&branch=$branch");
    exit();
}

$file = $result->fetch_assoc();

// Get file metadata
$file_type = 'application/octet-stream'; // Default MIME type
if(isset($file['metadata'])) {
    $metadata = json_decode($file['metadata'], true);
    if($metadata && isset($metadata['type'])) {
        $file_type = $metadata['type'];
    }
}

// Set headers for file download
header('Content-Description: File Transfer');
header('Content-Type: ' . $file_type);
header('Content-Disposition: attachment; filename="' . basename($file_path) . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . strlen($file['content']));

// Output file content
echo $file['content'];
exit;
?>
