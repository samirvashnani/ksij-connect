<?php
require_once dirname(__DIR__, 2) . '/includes/auth_staff.php';
require_once dirname(__DIR__, 2) . '/includes/admin_helpers.php';

$staff = requireRole('admin');
$pdo = getDb();
$success = '';
$error = '';
$editId = filter_var($_GET['edit'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
$record = null;

function projectImageFile(?string $relativePath): ?string
{
    if (!is_string($relativePath) || !preg_match('#^assets/project_images/[a-f0-9]{32}\.(jpg|png|webp)$#D', $relativePath)) {
        return null;
    }

    return dirname(__DIR__, 2) . '/' . $relativePath;
}

if ($editId > 0) {
    $statement = $pdo->prepare('SELECT * FROM projects WHERE id = ?');
    $statement->execute([$editId]);
    $record = $statement->fetch();
    if (!$record) {
        $error = 'Project not found.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    checkCsrf();
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    $id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;

    try {
        if ($action === 'delete' || $action === 'toggle') {
            $statement = $pdo->prepare('SELECT id, image_path, name, description, quote, is_active FROM projects WHERE id = ?');
            $statement->execute([$id]);
            $project = $statement->fetch();
            if (!$project) {
                $error = 'Project not found.';
            } elseif ($action === 'delete') {
                $pdo->prepare('DELETE FROM projects WHERE id = ?')->execute([$id]);
                $imageFile = projectImageFile($project['image_path']);
                if ($imageFile && is_file($imageFile) && !unlink($imageFile)) {
                    error_log('Could not remove project image: ' . $imageFile);
                    $error = 'Project deleted, but its image file could not be removed.';
                } else {
                    $success = 'Project deleted.';
                }
            } elseif ((int) $project['is_active'] === 0
                && (!$project['image_path'] || trim((string) $project['quote']) === '' || trim((string) $project['description']) === '')) {
                $error = 'Add an image, description, and quote before activating this project.';
            } else {
                $pdo->prepare('UPDATE projects SET is_active = ? WHERE id = ?')->execute([(int) $project['is_active'] === 1 ? 0 : 1, $id]);
                $success = (int) $project['is_active'] === 1 ? 'Project deactivated.' : 'Project activated.';
            }
        } elseif ($action === 'save') {
            $name = is_string($_POST['name'] ?? null) ? trim($_POST['name']) : '';
            $description = is_string($_POST['description'] ?? null) ? trim($_POST['description']) : '';
            $quote = is_string($_POST['quote'] ?? null) ? trim($_POST['quote']) : '';
            $existing = null;
            if ($id > 0) {
                $statement = $pdo->prepare('SELECT * FROM projects WHERE id = ?');
                $statement->execute([$id]);
                $existing = $statement->fetch();
            }

            if ($name === '' || strlen($name) > 150 || $description === '' || strlen($description) > 10000 || $quote === '' || strlen($quote) > 2000) {
                $error = 'Enter a project name (up to 150 characters), description (up to 10,000 characters), and quote (up to 2,000 characters).';
            } elseif ($id > 0 && !$existing) {
                $error = 'Project not found.';
            } else {
                $upload = $_FILES['image'] ?? null;
                $newImagePath = null;
                if ($upload !== null && (!is_array($upload) || !isset($upload['error']) || is_array($upload['error']))) {
                    $error = 'The image upload is invalid.';
                } elseif ($upload !== null && $upload['error'] !== UPLOAD_ERR_NO_FILE) {
                    if ($upload['error'] !== UPLOAD_ERR_OK || !isset($upload['tmp_name']) || !is_string($upload['tmp_name']) || !is_uploaded_file($upload['tmp_name'])) {
                        $error = 'The image could not be uploaded. Please choose it again.';
                    } else {
                        $size = filesize($upload['tmp_name']);
                        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
                        $extensions = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                        if (!$size || $size > 5 * 1024 * 1024 || !isset($extensions[$mime])) {
                            $error = 'Upload a JPG, PNG, or WebP image no larger than 5 MB.';
                        } else {
                            $relativeDirectory = 'assets/project_images';
                            $directory = dirname(__DIR__, 2) . '/' . $relativeDirectory;
                            if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
                                throw new RuntimeException('Could not create project image storage.');
                            }
                            $newImagePath = $relativeDirectory . '/' . bin2hex(random_bytes(16)) . '.' . $extensions[$mime];
                            if (!move_uploaded_file($upload['tmp_name'], dirname(__DIR__, 2) . '/' . $newImagePath)) {
                                throw new RuntimeException('Could not store the project image.');
                            }
                        }
                    }
                }

                if ($error === '' && !$newImagePath && !$existing) {
                    $error = 'Choose an image for this project.';
                }

                if ($error === '' && $existing && (int) $existing['is_active'] === 1 && !$newImagePath
                    && (!$existing['image_path'] || trim((string) $existing['quote']) === '' || trim((string) $existing['description']) === '')) {
                    $error = 'Add an image, description, and quote before keeping this project active.';
                }

                if ($error === '') {
                    $imagePath = $newImagePath ?: $existing['image_path'];
                    try {
                        if ($existing) {
                            $statement = $pdo->prepare('UPDATE projects SET name = ?, description = ?, quote = ?, image_path = ? WHERE id = ?');
                            $statement->execute([$name, $description, $quote, $imagePath, $id]);
                            $success = 'Project updated.';
                        } else {
                            $statement = $pdo->prepare("INSERT INTO projects (name, status, description, quote, image_path, is_active) VALUES (?, 'ongoing', ?, ?, ?, 0)");
                            $statement->execute([$name, $description, $quote, $imagePath]);
                            $success = 'Project created. Activate it when it is ready to display.';
                        }
                    } catch (Throwable $exception) {
                        if ($newImagePath) {
                            $newImageFile = projectImageFile($newImagePath);
                            if ($newImageFile && is_file($newImageFile)) {
                                unlink($newImageFile);
                            }
                        }
                        throw $exception;
                    }
                    if ($newImagePath && $existing && $existing['image_path']) {
                        $oldImageFile = projectImageFile($existing['image_path']);
                        if ($oldImageFile && is_file($oldImageFile) && !unlink($oldImageFile)) {
                            error_log('Could not remove replaced project image: ' . $oldImageFile);
                            $success = '';
                            $error = 'Project updated, but the previous image file could not be removed.';
                        }
                    }
                } elseif ($newImagePath) {
                    $newImageFile = projectImageFile($newImagePath);
                    if ($newImageFile && is_file($newImageFile)) {
                        unlink($newImageFile);
                    }
                }
            }
        } else {
            $error = 'Choose a valid project action.';
        }
    } catch (Throwable $exception) {
        error_log('Project administration failed: ' . $exception->getMessage());
        $error = 'The project could not be saved. Please try again.';
    }
}

adminRedirectAfterSuccess('public/admin/projects.php', $success);
$rows = $pdo->query('SELECT * FROM projects ORDER BY is_active DESC, id DESC')->fetchAll();

adminPageStart('Manage projects', $staff);
renderAdminMessages($success, $error);
?>
<div class="admin-grid two-columns">
    <section class="admin-card">
        <h2><?= $record ? 'Edit project' : 'Add project' ?></h2>
        <form method="post" class="admin-form" enctype="multipart/form-data">
            <?php csrfField(); ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= escapeHtml((string) ($record['id'] ?? 0)) ?>">
            <label>Project name<input name="name" maxlength="150" value="<?= escapeHtml((string) ($record['name'] ?? '')) ?>" required></label>
            <label>Description<textarea name="description" rows="5" maxlength="10000" required><?= escapeHtml((string) ($record['description'] ?? '')) ?></textarea></label>
            <label>Quote<textarea name="quote" rows="3" maxlength="2000" required><?= escapeHtml((string) ($record['quote'] ?? '')) ?></textarea></label>
            <label>Project image (JPG, PNG, or WebP; max 5 MB)
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" <?= $record && !empty($record['image_path']) ? '' : 'required' ?>>
            </label>
            <?php if ($record && !empty($record['image_path'])): ?>
                <img class="project-admin-thumbnail" src="<?= escapeHtml(appUrl($record['image_path'])) ?>" alt="<?= escapeHtml($record['name']) ?>">
            <?php endif; ?>
            <button type="submit">Save project</button>
            <?php if ($record): ?><a href="<?= escapeHtml(appUrl('public/admin/projects.php')) ?>">Cancel editing</a><?php endif; ?>
        </form>
    </section>

    <section class="admin-card">
        <h2>Projects</h2>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Image</th><th>Name</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if ($rows === []): ?>
                        <tr><td colspan="4" class="empty-state">No projects have been added yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $row): ?>
                        <tr>
                            <td><?php if ($row['image_path']): ?><img class="project-admin-thumbnail" src="<?= escapeHtml(appUrl($row['image_path'])) ?>" alt=""><?php else: ?>—<?php endif; ?></td>
                            <td><?= escapeHtml($row['name']) ?></td>
                            <td><span class="project-status <?= (int) $row['is_active'] === 1 ? 'active' : 'inactive' ?>"><?= (int) $row['is_active'] === 1 ? 'Active' : 'Inactive' ?></span></td>
                            <td class="row-actions project-actions">
                                <a href="<?= escapeHtml(appUrl('public/admin/projects.php?edit=' . (int) $row['id'])) ?>">Edit</a>
                                <form method="post">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="small-button"><?= (int) $row['is_active'] === 1 ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                                <form method="post" onsubmit="return confirm('Delete this project?');">
                                    <?php csrfField(); ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                    <button type="submit" class="small-button danger-button">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>
<?php adminPageEnd(); ?>
