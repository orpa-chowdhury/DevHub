// add_collaborator.php
<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$repo_id = $_POST['repo_id'] ?? 0;
$username_or_email = trim($_POST['username'] ?? '');
$permission = $_POST['permission'] ?? 'read';

if (!$repo_id || !$username_or_email) {
    $_SESSION['error'] = "Invalid input.";
    header("Location: repository_settings.php?id=$repo_id");
    exit();
}

if (!hasWriteAccess($conn, $repo_id, $_SESSION['user_id'])) {
    $_SESSION['error'] = "You do not have permission.";
    header("Location: repository_settings.php?id=$repo_id");
    exit();
}

$stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
$stmt->bind_param("ss", $username_or_email, $username_or_email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    $_SESSION['error'] = "User not found.";
    header("Location: repository_settings.php?id=$repo_id");
    exit();
}

$user_id = $user['id'];

$stmt = $conn->prepare("SELECT * FROM collaborators WHERE repository_id = ? AND user_id = ?");
$stmt->bind_param("ii", $repo_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

if ($result->num_rows > 0) {
    $_SESSION['error'] = "User is already a collaborator.";
    header("Location: repository_settings.php?id=$repo_id");
    exit();
}

$stmt = $conn->prepare("INSERT INTO collaborators (repository_id, user_id, permission, created_at) VALUES (?, ?, ?, NOW())");
$stmt->bind_param("iis", $repo_id, $user_id, $permission);
$stmt->execute();
$stmt->close();

$_SESSION['success'] = "Collaborator added successfully.";
header("Location: repository_settings.php?id=$repo_id");
exit();
?>


// remove_collaborator.php
<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = (int)($_POST['user_id'] ?? 0);
$repo_id = (int)($_POST['repo_id'] ?? 0);

if (!$user_id || !$repo_id) {
    $_SESSION['error'] = "Invalid request.";
    header("Location: repository_settings.php?id=$repo_id");
    exit();
}

if (!hasWriteAccess($conn, $repo_id, $_SESSION['user_id'])) {
    $_SESSION['error'] = "Permission denied.";
    header("Location: repository_settings.php?id=$repo_id");
    exit();
}

$stmt = $conn->prepare("DELETE FROM collaborators WHERE repository_id = ? AND user_id = ?");
$stmt->bind_param("ii", $repo_id, $user_id);
$stmt->execute();
$stmt->close();

$_SESSION['success'] = "Collaborator removed.";
header("Location: repository_settings.php?id=$repo_id");
exit();
?>


// delete_repository.php
<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$repo_id = (int)($_POST['repo_id'] ?? 0);

if (!$repo_id) {
    $_SESSION['error'] = "Invalid repository ID.";
    header("Location: index.php");
    exit();
}

$repository = getRepository($conn, $repo_id);
if (!$repository || $repository['user_id'] != $_SESSION['user_id']) {
    $_SESSION['error'] = "Only the repository owner can delete this repository.";
    header("Location: repository_settings.php?id=$repo_id");
    exit();
}

$stmt = $conn->prepare("DELETE FROM repositories WHERE id = ?");
$stmt->bind_param("i", $repo_id);
$stmt->execute();
$stmt->close();

$_SESSION['success'] = "Repository deleted successfully.";
header("Location: dashboard.php");
exit();
?>
