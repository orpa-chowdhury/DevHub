<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Redirect if not logged in
if(!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$error = '';
$success = '';

// Process repository creation form
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'] ?? '';
    $description = $_POST['description'] ?? '';
    $visibility = $_POST['visibility'] ?? 'public';
    $user_id = $_SESSION['user_id'];
    
    // Validate input
    if(empty($name)) {
        $error = "Repository name is required";
    } elseif(!preg_match('/^[a-zA-Z0-9_-]+$/', $name)) {
        $error = "Repository name can only contain letters, numbers, hyphens, and underscores";
    } else {
        // Check if repository name already exists for this user
        $sql = "SELECT id FROM repositories WHERE user_id = ? AND name = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $user_id, $name);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $error = "You already have a repository with this name";
        } else {
            // Create repository
            $sql = "INSERT INTO repositories (name, description, visibility, user_id, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("sssi", $name, $description, $visibility, $user_id);
            
            if($stmt->execute()) {
                $repo_id = $stmt->insert_id;
                
                // Create default branch (main)
                $sql = "INSERT INTO branches (repository_id, name, is_default) VALUES (?, 'main', 1)";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $repo_id);
                $stmt->execute();
                
                // Log activity
                logActivity($conn, $user_id, 'create_repository', $repo_id, 'Created repository ' . $name);
                
                // Redirect to the new repository
                header("Location: repository.php?id=" . $repo_id);
                exit();
            } else {
                $error = "Error creating repository: " . $conn->error;
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
    <title>Create Repository - DevHub</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
</head>
<body>
    <?php include 'includes/header.php'; ?>
    
    <main class="container">
        <div class="page-header">
            <h1>Create a new repository</h1>
            <p>A repository contains all your project's files, revision history, and collaborator settings.</p>
        </div>
        
        <?php if(!empty($error)): ?>
        <div class="alert alert-danger">
            <?php echo $error; ?>
        </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form action="create_repository.php" method="POST">
                <div class="form-group">
                    <label for="name">Repository name *</label>
                    <input type="text" id="name" name="name" required>
                    <small>Great repository names are short and memorable. Need inspiration? How about <span id="suggestion">awesome-project</span>?</small>
                </div>
                
                <div class="form-group">
                    <label for="description">Description (optional)</label>
                    <input type="text" id="description" name="description">
                </div>
                
                <div class="form-group">
                    <label>Visibility</label>
                    <div class="radio-group">
                        <div class="radio-option">
                            <input type="radio" id="public" name="visibility" value="public" checked>
                            <label for="public">
                                <i class="fas fa-globe"></i>
                                <div>
                                    <strong>Public</strong>
                                    <p>Anyone can see this repository. You choose who can commit.</p>
                                </div>
                            </label>
                        </div>
                        <div class="radio-option">
                            <input type="radio" id="private" name="visibility" value="private">
                            <label for="private">
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
                    <button type="submit" class="btn btn-primary">Create repository</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </main>
    
    <?php include 'includes/footer.php'; ?>
    <script>
        // Generate random repository name suggestion
        document.addEventListener('DOMContentLoaded', function() {
            const adjectives = ['awesome', 'cool', 'super', 'amazing', 'brilliant', 'fantastic'];
            const nouns = ['project', 'app', 'tool', 'system', 'framework', 'platform'];
            
            const randomAdjective = adjectives[Math.floor(Math.random() * adjectives.length)];
            const randomNoun = nouns[Math.floor(Math.random() * nouns.length)];
            
            document.getElementById('suggestion').textContent = `${randomAdjective}-${randomNoun}`;
        });
    </script>
</body>
</html>
