<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Get repository ID from URL
$repo_id = $_GET['id'] ?? 0;

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

$error = '';
$success = '';

// Process repository update form
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $visibility = $_POST['visibility'] ?? 'public';
    
    // Validate input
    if(empty($name)) {
        $error = "Repository name is required";
    } elseif(!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
        $error = "Repository name can only contain letters, numbers, hyphens, and underscores";
    } else {
        // Check if repository name already exists for this user (if name changed)
        if($name != $repository['name']) {
            $sql = "SELECT id FROM repositories WHERE user_id = ? AND name = ? AND id != ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("isi", $_SESSION['user_id'], $name, $repo_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if($result->num_rows > 0) {
                $error = "You already have a repository with this name";
            }
        }
        
        if(empty($error)) {
            // Update repository
            $sql = "UPDATE repositories SET name = ?, description = ?, visibility = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $name, $description, $visibility, $repo_id);
            
            if($stmt->execute()) {
                // Log activity
                logActivity($conn, $_SESSION['user_id'], 'update_repository', $repo_id, 'Updated repository ' . $name);
                
                $success = "Repository updated successfully";
                
                // Update repository variable for display
                $repository['name'] = $name;
                $repository['description'] = $description;
                $repository['visibility'] = $visibility;
            } else {
                $error = "Error updating repository: " . $conn->error;
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
    <title>Edit Repository - DevHub</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="page-header">
            <h1>Edit Repository</h1>
            <p>Update your repository details</p>
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
        
        <div class="card mb-4">
            <div class="card-header">
                <h3>Repository Settings</h3>
            </div>
            <div class="card-body">
                <form action="edit_repository.php?id=<?php echo $repo_id; ?>" method="POST">
                    <div class="form-group">
                        <label for="name">Repository name *</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($repository['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="description">Description (optional)</label>
                        <textarea id="description" name="description" rows="3"><?php echo htmlspecialchars($repository['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Visibility</label>
                        <div class="radio-group">
                            <div class="radio-option <?php echo $repository['visibility'] == 'public' ? 'selected' : ''; ?>">
                                <label>
                                    <input type="radio" name="visibility" value="public" <?php echo $repository['visibility'] == 'public' ? 'checked' : ''; ?>>
                                    <i class="fas fa-globe"></i>
                                    <div>
                                        <strong>Public</strong>
                                        <p>Anyone can see this repository. You choose who can commit.</p>
                                    </div>
                                </label>
                            </div>
                            <div class="radio-option <?php echo $repository['visibility'] == 'private' ? 'selected' : ''; ?>">
                                <label>
                                    <input type="radio" name="visibility" value="private" <?php echo $repository['visibility'] == 'private' ? 'checked' : ''; ?>>
                                    <i class="fas fa-lock"></i>
                                    <div>
                                        <strong>Private</strong>
                                        <p>You choose who can see and commit to this repository.</p>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Update repository</button>
                        <a href="repository.php?id=<?php echo $repo_id; ?>" class="btn btn-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="card danger-zone">
            <div class="card-header">
                <h3>Danger Zone</h3>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4>Delete this repository</h4>
                        <p>Once you delete a repository, there is no going back. Please be certain.</p>
                    </div>
                    <button class="btn btn-danger" id="delete-repo-btn">Delete this repository</button>
                </div>
            </div>
        </div>
        
        <!-- Delete Repository Modal -->
        <div class="modal-backdrop" id="delete-modal">
            <div class="modal">
                <div class="modal-header">
                    <h3>Are you sure?</h3>
                    <button class="modal-close" id="close-modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>This action <strong>cannot</strong> be undone. This will permanently delete the <strong><?php echo htmlspecialchars($repository['name']); ?></strong> repository, all of its files, issues, and pull requests.</p>
                    
                    <div class="form-group mt-4">
                        <label for="confirm-name">Please type <strong><?php echo htmlspecialchars($repository['name']); ?></strong> to confirm.</label>
                        <input type="text" id="confirm-name" class="mt-2">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" id="cancel-delete">Cancel</button>
                    <form action="delete_repository.php" method="POST">
                        <input type="hidden" name="repo_id" value="<?php echo $repo_id; ?>">
                        <button type="submit" class="btn btn-danger" id="confirm-delete" disabled>I understand the consequences, delete this repository</button>
                    </form>
                </div>
            </div>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    
    <script>
        // Radio option selection
        const radioOptions = document.querySelectorAll('.radio-option');
        const radioInputs = document.querySelectorAll('.radio-option input[type="radio"]');
        
        radioInputs.forEach(input => {
            input.addEventListener('change', function() {
                // Remove selected class from all options
                radioOptions.forEach(option => {
                    option.classList.remove('selected');
                });
                
                // Add selected class to the parent of the checked input
                if(this.checked) {
                    this.closest('.radio-option').classList.add('selected');
                }
            });
        });
        
        // Delete repository modal
        const deleteBtn = document.getElementById('delete-repo-btn');
        const deleteModal = document.getElementById('delete-modal');
        const closeModal = document.getElementById('close-modal');
        const cancelDelete = document.getElementById('cancel-delete');
        const confirmDelete = document.getElementById('confirm-delete');
        const confirmNameInput = document.getElementById('confirm-name');
        const repoName = '<?php echo htmlspecialchars($repository['name']); ?>';
        
        deleteBtn.addEventListener('click', function() {
            deleteModal.classList.add('show');
            document.body.style.overflow = 'hidden';
        });
        
        function closeDeleteModal() {
            deleteModal.classList.remove('show');
            document.body.style.overflow = '';
            confirmNameInput.value = '';
        }
        
        closeModal.addEventListener('click', closeDeleteModal);
        cancelDelete.addEventListener('click', closeDeleteModal);
        
        // Close modal when clicking outside
        deleteModal.addEventListener('click', function(e) {
            if(e.target === deleteModal) {
                closeDeleteModal();
            }
        });
        
        // Enable/disable delete button based on confirmation input
        confirmNameInput.addEventListener('input', function() {
            confirmDelete.disabled = this.value !== repoName;
        });
    </script>
</body>
</html>
