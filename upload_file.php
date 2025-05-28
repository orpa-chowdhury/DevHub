<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get repository ID and branch from URL
$repo_id = $_GET['repo_id'] ?? 0;
$branch = $_GET['branch'] ?? 'main';
$path = $_GET['path'] ?? '';

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

$error = '';
$success = '';

// Process file upload
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $file_name = $_POST['file_name'] ?? '';
    $file_content = $_POST['file_content'] ?? '';
    $commit_message = $_POST['commit_message'] ?? 'Add ' . $file_name;
    
    // Validate input
    if(empty($file_name)) {
        $error = "File name is required";
    } elseif(strpos($file_name, '/') !== false || strpos($file_name, '\\') !== false) {
        $error = "File name cannot contain slashes";
    } else {
        // Construct full path
        $full_path = $path ? $path . '/' . $file_name : $file_name;
        
        // Check if file already exists
        $sql = "SELECT id FROM files WHERE repository_id = ? AND branch = ? AND path = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("iss", $repo_id, $branch, $full_path);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $error = "A file with this name already exists in this location";
        } else {
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
                
                // Add file
                $sql = "INSERT INTO files (repository_id, branch, path, name, content, is_directory, last_commit_id, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, 0, ?, NOW(), NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("issssi", $repo_id, $branch, $full_path, $file_name, $file_content, $commit_id);
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
                
                $success = "File added successfully";
                
                // Redirect after short delay
                header("refresh:1;url=repository.php?id=$repo_id&branch=$branch" . ($path ? "&path=$path" : ""));
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollback();
                $error = "Error adding file: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add File - DevHub</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="page-header">
            <h1>Add File to Repository</h1>
            <p>
                <a href="repository.php?id=<?php echo $repo_id; ?>"><?php echo htmlspecialchars($repository['name']); ?></a>
                <?php if($path): ?>
                    / <?php echo htmlspecialchars($path); ?>
                <?php endif; ?>
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
        
        <div class="card">
            <div class="card-header">
                <h3>Create New File</h3>
            </div>
            <div class="card-body">
                <form action="upload_file.php?repo_id=<?php echo $repo_id; ?>&branch=<?php echo $branch; ?><?php echo $path ? '&path=' . $path : ''; ?>" method="POST">
                    <div class="form-group">
                        <label for="file_name">File name *</label>
                        <input type="text" id="file_name" name="file_name" required>
                        <small>Example: index.php, README.md, style.css</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="file_content">File content</label>
                        <div class="file-editor">
                            <div class="file-editor-header">
                                <span id="file-type">Text</span>
                                <div class="file-editor-actions">
                                    <button type="button" class="btn btn-sm btn-secondary" id="expand-editor">
                                        <i class="fas fa-expand"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="file-editor-body">
                                <textarea id="file_content" name="file_content" rows="15"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="commit_message">Commit message</label>
                        <input type="text" id="commit_message" name="commit_message" placeholder="Add new file">
                        <small>Describe the changes you're making</small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Commit new file</button>
                        <a href="repository.php?id=<?php echo $repo_id; ?>&branch=<?php echo $branch; ?><?php echo $path ? '&path=' . $path : ''; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="mt-4">
            <h3>Or upload files</h3>
            <p class="mb-3">You can also upload files directly from your computer</p>
            
            <form action="upload_file_binary.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="repo_id" value="<?php echo $repo_id; ?>">
                <input type="hidden" name="branch" value="<?php echo $branch; ?>">
                <input type="hidden" name="path" value="<?php echo $path; ?>">
                
                <label for="file-upload" class="file-upload">
                    <input type="file" id="file-upload" name="file" required>
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h3>Drag and drop a file here</h3>
                    <p>or click to select a file</p>
                    <button type="button" class="btn btn-primary" id="select-file-btn">Choose file</button>
                    
                    <div id="file-preview" class="file-preview"></div>
                </label>
                
                <div class="form-group mt-3" id="upload-commit-message" style="display: none;">
                    <label for="upload_commit_message">Commit message</label>
                    <input type="text" id="upload_commit_message" name="commit_message" placeholder="Add file via upload">
                </div>
                
                <div class="form-actions" id="upload-actions" style="display: none;">
                    <button type="submit" class="btn btn-primary">Upload file</button>
                    <button type="button" class="btn btn-secondary" id="cancel-upload">Cancel</button>
                </div>
            </form>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // File name to file type detection
        const fileNameInput = document.getElementById('file_name');
        const fileTypeDisplay = document.getElementById('file-type');
        const fileContentTextarea = document.getElementById('file_content');
        
        fileNameInput.addEventListener('input', function() {
            const fileName = this.value;
            let fileType = 'Text';
            
            if(fileName.endsWith('.php')) {
                fileType = 'PHP';
            } else if(fileName.endsWith('.js')) {
                fileType = 'JavaScript';
            } else if(fileName.endsWith('.css')) {
                fileType = 'CSS';
            } else if(fileName.endsWith('.html')) {
                fileType = 'HTML';
            } else if(fileName.endsWith('.md')) {
                fileType = 'Markdown';
            } else if(fileName.endsWith('.json')) {
                fileType = 'JSON';
            } else if(fileName.endsWith('.sql')) {
                fileType = 'SQL';
            }
            
            fileTypeDisplay.textContent = fileType;
        });
        
        // File upload preview
        const fileUploadInput = document.getElementById('file-upload');
        const filePreview = document.getElementById('file-preview');
        const selectFileBtn = document.getElementById('select-file-btn');
        const uploadCommitMessage = document.getElementById('upload-commit-message');
        const uploadActions = document.getElementById('upload-actions');
        const cancelUploadBtn = document.getElementById('cancel-upload');
        
        selectFileBtn.addEventListener('click', function() {
            fileUploadInput.click();
        });
        
        fileUploadInput.addEventListener('change', function() {
            const file = this.files[0];
            
            if(file) {
                filePreview.innerHTML = `
                    <div class="file-preview-item">
                        <div class="file-preview-name">${file.name}</div>
                        <div class="file-preview-size">${formatFileSize(file.size)}</div>
                    </div>
                `;
                
                uploadCommitMessage.style.display = 'block';
                uploadActions.style.display = 'flex';
                selectFileBtn.style.display = 'none';
            }
        });
        
        cancelUploadBtn.addEventListener('click', function() {
            fileUploadInput.value = '';
            filePreview.innerHTML = '';
            uploadCommitMessage.style.display = 'none';
            uploadActions.style.display = 'none';
            selectFileBtn.style.display = 'inline-block';
        });
        
        // Format file size
        function formatFileSize(bytes) {
            if(bytes === 0) return '0 Bytes';
            
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
        
        // Expand editor
        const expandEditorBtn = document.getElementById('expand-editor');
        
        expandEditorBtn.addEventListener('click', function() {
            if(fileContentTextarea.rows === 15) {
                fileContentTextarea.rows = 30;
                this.innerHTML = '<i class="fas fa-compress"></i>';
            } else {
                fileContentTextarea.rows = 15;
                this.innerHTML = '<i class="fas fa-expand"></i>';
            }
        });
    </script>
</body>
</html>
