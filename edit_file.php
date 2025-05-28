<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

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
    header("Location: repository.php?id=$repo_id&branch=$branch");
    exit();
}

$file = $result->fetch_assoc();

// Check if file is binary (not editable in browser)
$is_binary = false;
if(isset($file['metadata'])) {
    $metadata = json_decode($file['metadata'], true);
    if($metadata && isset($metadata['type']) && !preg_match('/^(text\/|application\/(json|xml|javascript))/', $metadata['type'])) {
        $is_binary = true;
    }
}

$error = '';
$success = '';

// Process file edit
if($_SERVER['REQUEST_METHOD'] == 'POST' && !$is_binary) {
    $file_content = $_POST['file_content'] ?? '';
    $commit_message = $_POST['commit_message'] ?? 'Update ' . basename($file_path);
    
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
        $sql = "INSERT INTO commit_files (commit_id, file_path, change_type, content_before, content_after) 
                VALUES (?, ?, 'modify', ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("isss", $commit_id, $file_path, $file['content'], $file_content);
        $stmt->execute();
        
        // Update file
        $sql = "UPDATE files SET content = ?, last_commit_id = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sii", $file_content, $commit_id, $file['id']);
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
        
        $success = "File updated successfully";
        
        // Update file variable for display
        $file['content'] = $file_content;
        
        // Redirect after short delay
        header("refresh:1;url=view_file.php?repo_id=$repo_id&branch=$branch&path=$file_path");
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $error = "Error updating file: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit File - DevHub</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" />

</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="page-header">
            <h1>Edit File</h1>
            <p>
                <a href="repository.php?id=<?php echo $repo_id; ?>"><?php echo htmlspecialchars($repository['name']); ?></a>
                / 
                <a href="view_file.php?repo_id=<?php echo $repo_id; ?>&branch=<?php echo $branch; ?>&path=<?php echo $file_path; ?>"><?php echo htmlspecialchars(basename($file_path)); ?></a>
            </p>
        </div>
        
        <?php if(!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <?php if(!empty($success)): ?>
        <div class="alert alert-success">
            <?php echo $success; ?>
        </div>
        <?php endif; ?>
        
        <?php if($is_binary): ?>
        <div class="alert alert-warning">
            <p>This file is a binary file and cannot be edited in the browser.</p>
            <p>Please download the file, make your changes, and upload it again.</p>
        </div>
        
        <div class="form-actions">
            <a href="view_file.php?repo_id=<?php echo $repo_id; ?>&branch=<?php echo $branch; ?>&path=<?php echo $file_path; ?>" class="btn btn-primary">Back to file</a>
            <a href="repository.php?id=<?php echo $repo_id; ?>&branch=<?php echo $branch; ?>" class="btn btn-secondary">Back to repository</a>
        </div>
        <?php else: ?>
        <div class="card">
            <div class="card-header">
                <h3>Editing <?php echo htmlspecialchars(basename($file_path)); ?></h3>
            </div>
            <div class="card-body">
                <form action="edit_file.php?repo_id=<?php echo $repo_id; ?>&branch=<?php echo $branch; ?>&path=<?php echo $file_path; ?>" method="POST">
                    <div class="form-group">
                        <div class="file-editor">
                            <div class="file-editor-header">
                                <span id="file-type"><?php echo getFileType(basename($file_path)); ?></span>
                                <div class="file-editor-actions">
                                    <button type="button" class="btn btn-sm btn-secondary" id="expand-editor">
                                        <i class="fas fa-expand"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="file-editor-body">
                                <textarea id="file_content" name="file_content" rows="20"><?php echo htmlspecialchars($file['content']); ?></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="commit_message">Commit message</label>
                        <input type="text" id="commit_message" name="commit_message" placeholder="Update <?php echo htmlspecialchars(basename($file_path)); ?>">
                        <small>Describe the changes you're making</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Commit changes</button>
                        <a href="view_file.php?repo_id=<?php echo $repo_id; ?>&branch=<?php echo $branch; ?>&path=<?php echo $file_path; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Expand editor
        const expandEditorBtn = document.getElementById('expand-editor');
        const fileContentTextarea = document.getElementById('file_content');
        
        if(expandEditorBtn && fileContentTextarea) {
            expandEditorBtn.addEventListener('click', function() {
                if(fileContentTextarea.rows === 20) {
                    fileContentTextarea.rows = 40;
                    this.innerHTML = '<i class="fas fa-compress"></i>';
                } else {
                    fileContentTextarea.rows = 20;
                    this.innerHTML = '<i class="fas fa-expand"></i>';
                }
            });
        }
    </script>
</body>
</html>

<?php
// Helper function to get file type based on extension
function getFileType($filename) {
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    
    switch(strtolower($extension)) {
        case 'php':
            return 'PHP';
        case 'js':
            return 'JavaScript';
        case 'css':
            return 'CSS';
        case 'html':
        case 'htm':
            return 'HTML';
        case 'md':
            return 'Markdown';
        case 'json':
            return 'JSON';
        case 'sql':
            return 'SQL';
        case 'txt':
            return 'Text';
        case 'xml':
            return 'XML';
        case 'yml':
        case 'yaml':
            return 'YAML';
        default:
            return 'Text';
    }
}
?>
