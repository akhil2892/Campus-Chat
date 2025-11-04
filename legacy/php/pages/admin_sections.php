<?php
// pages/admin_sections.php
require_once '../config/config.php';
require_once '../config/database.php';

// Check admin access
if (!validateSession() || getCurrentUserRole() !== 'admin') {
    redirectTo('dashboard.php');
}

$currentUserId = getCurrentUserId();

// Handle section actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'create') {
            $sectionCode = sanitizeInput($_POST['section_code']);
            $sectionName = sanitizeInput($_POST['section_name']);
            $department = sanitizeInput($_POST['department']);

            $stmt = $pdo->prepare("INSERT INTO sections (section_code, section_name, department, created_by, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$sectionCode, $sectionName, $department, $currentUserId]);

            // Log action
            logAdminAction($pdo, $currentUserId, 'create_section', 'section', $pdo->lastInsertId(), "Created section: $sectionCode");

            $_SESSION['success_message'] = 'Section created successfully!';
        }
        elseif ($action === 'edit') {
            $sectionId = (int)$_POST['section_id'];
            $sectionCode = sanitizeInput($_POST['section_code']);
            $sectionName = sanitizeInput($_POST['section_name']);
            $department = sanitizeInput($_POST['department']);

            $stmt = $pdo->prepare("UPDATE sections SET section_code = ?, section_name = ?, department = ? WHERE section_id = ?");
            $stmt->execute([$sectionCode, $sectionName, $department, $sectionId]);

            logAdminAction($pdo, $currentUserId, 'edit_section', 'section', $sectionId, "Updated section: $sectionCode");

            $_SESSION['success_message'] = 'Section updated successfully!';
        }
        elseif ($action === 'delete') {
            $sectionId = (int)$_POST['section_id'];

            $stmt = $pdo->prepare("UPDATE sections SET is_active = 0 WHERE section_id = ?");
            $stmt->execute([$sectionId]);

            logAdminAction($pdo, $currentUserId, 'delete_section', 'section', $sectionId, "Deactivated section");

            $_SESSION['success_message'] = 'Section deactivated successfully!';
        }
        elseif ($action === 'activate') {
            $sectionId = (int)$_POST['section_id'];

            $stmt = $pdo->prepare("UPDATE sections SET is_active = 1 WHERE section_id = ?");
            $stmt->execute([$sectionId]);

            logAdminAction($pdo, $currentUserId, 'activate_section', 'section', $sectionId, "Activated section");

            $_SESSION['success_message'] = 'Section activated successfully!';
        }
    } catch (PDOException $e) {
        logError("Admin sections error: " . $e->getMessage());
        $_SESSION['error_message'] = 'Operation failed: ' . $e->getMessage();
    }

    header('Location: admin_sections.php');
    exit;
}

// Get all sections
try {
    $stmt = $pdo->query("
        SELECT s.*, u.first_name, u.last_name,
               (SELECT COUNT(*) FROM users WHERE section = s.section_code AND status = 'active') as student_count
        FROM sections s
        LEFT JOIN users u ON s.created_by = u.user_id
        ORDER BY s.is_active DESC, s.section_code ASC
    ");
    $sections = $stmt->fetchAll();
} catch (PDOException $e) {
    $sections = [];
}

// Helper function
function logAdminAction($pdo, $adminId, $action, $targetType, $targetId, $description) {
    $stmt = $pdo->prepare("INSERT INTO admin_logs (admin_id, action_type, target_type, target_id, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$adminId, $action, $targetType, $targetId, $description, $_SERVER['REMOTE_ADDR']]);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Sections - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body { background: #f8f9fa; }
        .admin-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="admin-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="fas fa-th-list me-2"></i>Manage Sections</h2>
                    <p class="mb-0">Create and manage sections for student registration</p>
                </div>
                <a href="admin_dashboard.php" class="btn btn-light">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Create Section Button -->
        <div class="mb-4">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSectionModal">
                <i class="fas fa-plus me-2"></i>Create New Section
            </button>
        </div>

        <!-- Sections Table -->
        <div class="card">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>All Sections (<?php echo count($sections); ?>)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Section Code</th>
                                <th>Section Name</th>
                                <th>Department</th>
                                <th>Students</th>
                                <th>Created By</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sections)): ?>
                                <tr><td colspan="8" class="text-center text-muted">No sections found</td></tr>
                            <?php else: ?>
                                <?php foreach ($sections as $section): ?>
                                    <tr>
                                        <td><?php echo $section['section_id']; ?></td>
                                        <td><strong><?php echo htmlspecialchars($section['section_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($section['section_name']); ?></td>
                                        <td><?php echo htmlspecialchars($section['department'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $section['student_count']; ?> students</span>
                                        </td>
                                        <td><?php echo $section['first_name'] ? htmlspecialchars($section['first_name'] . ' ' . $section['last_name']) : 'System'; ?></td>
                                        <td>
                                            <?php if ($section['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" onclick="editSection(<?php echo htmlspecialchars(json_encode($section)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($section['is_active']): ?>
                                                <form method="POST" style="display:inline;" onsubmit="return confirm('Deactivate this section?')">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="section_id" value="<?php echo $section['section_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-ban"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display:inline;">
                                                    <input type="hidden" name="action" value="activate">
                                                    <input type="hidden" name="section_id" value="<?php echo $section['section_id']; ?>">
                                                    <button type="submit" class="btn btn-sm btn-success">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Section Modal -->
    <div class="modal fade" id="createSectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="create">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus-circle me-2"></i>Create New Section</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Section Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="section_code" placeholder="e.g., CS-3" required>
                            <small class="text-muted">This will be used by students during registration</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Section Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="section_name" placeholder="e.g., Computer Science Section 3" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" name="department" placeholder="e.g., Computer Science">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Section
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Section Modal -->
    <div class="modal fade" id="editSectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="section_id" id="edit_section_id">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Edit Section</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Section Code <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="section_code" id="edit_section_code" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Section Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="section_name" id="edit_section_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" name="department" id="edit_department">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Section
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editSection(section) {
            document.getElementById('edit_section_id').value = section.section_id;
            document.getElementById('edit_section_code').value = section.section_code;
            document.getElementById('edit_section_name').value = section.section_name;
            document.getElementById('edit_department').value = section.department || '';

            new bootstrap.Modal(document.getElementById('editSectionModal')).show();
        }
    </script>
</body>
</html>