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
$sql = "SELECT f.*, c.message as commit_message, c.created_at as commit_date, u.username as commit_author 
        FROM files f 
        LEFT JOIN commits c ON f.last_commit_id = c.id 
        LEFT JOIN users u ON c.user_id = u.id 
        WHERE f.repository_id = ? AND f.branch = ? AND f.path = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $repo_id, $branch, $file_path);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows == 0) {
    header("Location: repository.php?id=$repo_id&branch=$branch");
    exit();
}

$file = $result->fetch_assoc();

// Check if user has write access
$is_owner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $repository['user_id'];
$is_collaborator = isset($_SESSION['user_id']) && isCollaborator($conn, $repo_id, $_SESSION['user_id']);
$has_write_access = $is_owner || $is_collaborator;

// Check if file is binary
$is_binary = false;
$file_type = 'text';
if(isset($file['metadata'])) {
    $metadata = json_decode($file['metadata'], true);
    if($metadata && isset($metadata['type'])) {
        $file_type = $metadata['type'];
        if(!preg_match('/^(text\/|application\/(json|xml|javascript))/', $file_type)) {
            $is_binary = true;
        }
    }
}

// Get file extension
$file_extension = pathinfo($file_path, PATHINFO_EXTENSION);

// Get file size
$file_size = isset($metadata['size']) ? $metadata['size'] : strlen($file['content']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars(basename($file_path)); ?> - <?php echo htmlspecialchars($repository['name']); ?> - DevHub</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <?php if(!$is_binary): ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.3.1/styles/github.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.3.1/highlight.min.js"></script>
    <?php endif; ?>
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <?php if(isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success_message']; 
            unset($_SESSION['success_message']);
            ?>
        </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['error_message']; 
            unset($_SESSION['error_message']);
            ?>
        </div>
        <?php endif; ?>
        
        <div class="file-header card mb-4">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div class="file-path">
                        <a href="repository.php?id=<?php echo $repo_id; ?>&branch=<?php echo $branch; ?>"><?php echo htmlspecialchars($repository['name']); ?></a>
                        <?php
                        $path_parts = explode('/', $file_path);
                        $current_path = '';
                        for($i = 0; $i < count($path_parts) - 1; $i++) {
                            $current_path .= $path_parts[$i] . '/';
                            echo ' / <a href="repository.php?id=' . $repo_id . '&branch=' . $branch . '&path=' . urlencode(rtrim($current_path, '/')) . '">' . htmlspecialchars($path_parts[$i]) . '</a>';
                        }
                        echo ' / <strong>' . htmlspecialchars(end($path_parts)) . '</strong>';
                        ?>
                    </div>
                    <div class="file-actions">
                        <?php if($has_write_access): ?>
                        <a href="edit_file.php?repo_id=<?php echo $repo_id; ?>&branch=<?php echo $branch; ?>&path=<?php echo $file_path; ?>" class="btn btn-primary">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                        <button class="btn btn-danger" id="delete-file-btn">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                        <?php endif; ?>
                        <a href="download_file.php?repo_id=<?php echo $repo_id; ?>&branch=<?php echo $branch; ?>&path=<?php echo $file_path; ?>" class="btn btn-secondary">
                            <i class="fas fa-download"></i> Download
                        </a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="file-info">
                    <div class="file-meta">
                        <span class="file-size"><?php echo formatFileSize($file_size); ?></span>
                        <?php if($file_extension): ?>
                        <span class="file-extension"><?php echo strtoupper($file_extension); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="file-commit">
                        <img src="https://www.gravatar.com/avatar/<?php echo md5(strtolower(trim($file['commit_author'] ?? ''))); ?>?s=20&d=mp" alt="<?php echo htmlspecialchars($file['commit_author'] ?? ''); ?>" class="avatar avatar-sm">
                        <a href="profile.php?username=<?php echo urlencode($file['commit_author'] ?? ''); ?>"><?php echo htmlspecialchars($file['commit_author'] ?? ''); ?></a>
                        <span class="commit-message"><?php echo htmlspecialchars($file['commit_message'] ?? ''); ?></span>
                        <span class="commit-date"><?php echo timeAgo($file['commit_date'] ?? ''); ?></span>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="file-content card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h3><?php echo htmlspecialchars(basename($file_path)); ?></h3>
                    <div class="file-stats">
                        <span class="file-lines"><?php echo !$is_binary ? count(explode("\n", $file['content'])) . ' lines' : ''; ?></span>
                    </div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if($is_binary): ?>
                    <?php if(strpos($file_type, 'image/') === 0): ?>
                        <div class="binary-preview text-center p-4">
                            <img src="data:<?php echo $file_type; ?>;base64,<?php echo base64_encode($file['content']); ?>" alt="<?php echo htmlspecialchars(basename($file_path)); ?>" class="img-fluid">
                        </div>
                    <?php else: ?>
                        <div class="binary-message text-center p-4">
                            <i class="fas fa-file-alt fa-3x mb-3"></i>
                            <h4>Binary file not shown</h4>
                            <p>This file is a binary file and cannot be displayed in the browser.</p>
                            <p>Please download the file to view its contents.</p>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="code-container">
                        <pre><code class="<?php echo getLanguageClass($file_extension); ?>"><?php echo htmlspecialchars($file['content']); ?></code></pre>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Delete File Modal -->
        <div class="modal-backdrop" id="delete-modal">
            <div class="modal">
                <div class="modal-header">
                    <h3>Delete File</h3>
                    <button class="modal-close" id="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong><?php echo htmlspecialchars(basename($file_path)); ?></strong>?</p>
                    <p>This action cannot be undone. The file will be permanently deleted from the repository.</p>
                    
                    <form action="delete_file.php" method="POST" id="delete-file-form">
                        <input type="hidden" name="repo_id" value="<?php echo $repo_id; ?>">
                        <input type="hidden" name="branch" value="<?php echo $branch; ?>">
                        <input type="hidden" name="path" value="<?php echo $file_path; ?>">
                        
                        <div class="form-group mt-3">
                            <label for="commit_message">Commit message</label>
                            <input type="text" id="commit_message" name="commit_message" value="Delete <?php echo htmlspecialchars(basename($file_path)); ?>">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancel-delete">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirm-delete">Delete this file</button>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <?php if(!$is_binary): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Syntax highlighting
            document.querySelectorAll('pre code').forEach((block) => {
                hljs.highlightBlock(block);
            });
        });
    </script>
    <?php endif; ?>
    
    <script>
        // Delete file modal
        const deleteBtn = document.getElementById('delete-file-btn');
        const deleteModal = document.getElementById('delete-modal');
        const closeModal = document.getElementById('close-modal');
        const cancelDelete = document.getElementById('cancel-delete');
        const confirmDelete = document.getElementById('confirm-delete');
        const deleteForm = document.getElementById('delete-file-form');
        
        if(deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                deleteModal.classList.add('show');
                document.body.style.overflow = 'hidden';
            });
        }
        
        function closeDeleteModal() {
            deleteModal.classList.remove('show');
            document.body.style.overflow = '';
        }
        
        if(closeModal) closeModal.addEventListener('click', closeDeleteModal);
        if(cancelDelete) cancelDelete.addEventListener('click', closeDeleteModal);
        
        // Close modal when clicking outside
        if(deleteModal) {
            deleteModal.addEventListener('click', function(e) {
                if(e.target === deleteModal) {
                    closeDeleteModal();
                }
            });
        }
        
        // Submit delete form
        if(confirmDelete && deleteForm) {
            confirmDelete.addEventListener('click', function() {
                deleteForm.submit();
            });
        }
    </script>
</body>
</html>

<?php
// Helper function to format file size
function formatFileSize($bytes) {
    if($bytes == 0) {
        return '0 Bytes';
    }
    
    $units = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    $i = floor(log($bytes, 1024));
    
    return round($bytes / pow(1024, $i), 2) . ' ' . $units[$i];
}

// Helper function to get language class for syntax highlighting
function getLanguageClass($extension) {
    $map = [
        'php' => 'php',
        'js' => 'javascript',
        'css' => 'css',
        'html' => 'html',
        'htm' => 'html',
        'md' => 'markdown',
        'json' => 'json',
        'sql' => 'sql',
        'xml' => 'xml',
        'yml' => 'yaml',
        'yaml' => 'yaml',
        'py' => 'python',
        'rb' => 'ruby',
        'java' => 'java',
        'c' => 'c',
        'cpp' => 'cpp',
        'cs' => 'csharp',
        'go' => 'go',
        'ts' => 'typescript',
        'sh' => 'bash',
        'bash' => 'bash'
    ];
    
    return isset($map[$extension]) ? $map[$extension] : '';
}
?>
