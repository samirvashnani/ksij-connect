<?php
require_once dirname(__DIR__, 2) . '/includes/auth_staff.php';
require_once dirname(__DIR__, 2) . '/includes/layout.php';
require_once dirname(__DIR__, 2) . '/includes/donations.php';
$staff = requireRole('admin');
header('Cache-Control: no-store');
$error = '';
$projects = [];
$editId = filter_var($_GET['edit'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
$edit = ['id'=>0,'title'=>'','description'=>'','quote'=>'','is_active'=>1];
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        checkCsrf();
        $editId = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);
        if ($editId === false || $editId < 0) { throw new DomainException('Invalid project.'); }
        $title = donationText($_POST, 'title', 160);
        $description = donationText($_POST, 'description', 10000);
        $quote = donationText($_POST, 'quote', 300);
        $active = ($_POST['is_active'] ?? '') === '1' ? 1 : 0;
        $edit = ['id'=>$editId,'title'=>$title,'description'=>$description,'quote'=>$quote,'is_active'=>$active];
        $image = null;
        $mime = null;
        $file = $_FILES['image'] ?? null;
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            if (!is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name']) || filesize($file['tmp_name']) > 5 * 1024 * 1024) { throw new DomainException('Upload one JPG, PNG or WebP image, up to 5 MB.'); }
            $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
            $size = @getimagesize($file['tmp_name']);
            if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true) || !$size || $size[0] > 8000 || $size[1] > 8000 || $size[0] * $size[1] > 25000000) { throw new DomainException('Choose a valid JPG, PNG or WebP image under 25 megapixels.'); }
            $image = file_get_contents($file['tmp_name']);
            if ($image === false) { throw new DomainException('The image could not be read.'); }
        }
        if (!$editId && $image === null) { throw new DomainException('Please upload a project image.'); }
        if ($editId) {
            $s = getDb()->prepare('SELECT id FROM donation_projects WHERE id = ?'); $s->execute([$editId]);
            if (!$s->fetchColumn()) { throw new DomainException('Project not found.'); }
            $params = [$title,$description,$quote,$active];
            $sql = 'UPDATE donation_projects SET title=?,description=?,quote=?,is_active=?';
            if ($image !== null) { $sql .= ',image_data=?,image_mime=?'; $params[]=$image; $params[]=$mime; }
            $params[]=$editId;
            getDb()->prepare($sql . ' WHERE id=?')->execute($params);
        } else {
            getDb()->prepare('INSERT INTO donation_projects (title,description,quote,is_active,image_data,image_mime,created_by) VALUES (?,?,?,?,?,?,?)')->execute([$title,$description,$quote,$active,$image,$mime,$staff['id']]);
        }
        redirectTo('public/admin/donation_projects.php?saved=1');
    }
    if ($editId) {
        $s = getDb()->prepare('SELECT id,title,description,quote,is_active FROM donation_projects WHERE id=?'); $s->execute([$editId]);
        $edit = $s->fetch();
        if (!$edit) { throw new DomainException('Project not found.'); }
    }
} catch (DomainException $e) { $error = $e->getMessage(); }
catch (Throwable $e) { error_log('Donation project: ' . get_class($e)); $error = 'Projects are unavailable. Check that the donation tables in query.sql have been installed.'; }
try { $projects = getDb()->query('SELECT id,title,is_active,created_at FROM donation_projects ORDER BY id DESC')->fetchAll(); }
catch (Throwable $e) { $error = 'Projects are unavailable. Install the donation tables from query.sql.'; }
pageHeader('Donation projects', $staff, true);
?>
<section class="workspace donation-workspace"><header class="page-heading"><div><p class="eyebrow">COMMUNITY GIVING</p><h1>Donation projects</h1></div><a class="button-link button-secondary" href="<?= escapeHtml(appUrl('public/admin/donation_ledger.php')) ?>"><?= uiIcon('clipboard-list') ?>Payment ledger</a></header>
<?php showError($error); if (isset($_GET['saved'])): ?><p class="success">Project saved.</p><?php endif; ?>
<details class="donation-editor"<?= $editId || $error ? ' open' : '' ?>><summary><?= $editId ? 'Edit project' : 'New project / scheme' ?></summary>
<form method="post" enctype="multipart/form-data" class="donation-form"><?php csrfField(); ?><input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
<label>Title<input name="title" maxlength="160" required value="<?= escapeHtml($edit['title'] ?? '') ?>"></label>
<label>Quote<input name="quote" maxlength="300" required value="<?= escapeHtml($edit['quote'] ?? '') ?>"></label>
<label class="full-width">Description<textarea name="description" maxlength="10000" rows="5" required><?= escapeHtml($edit['description'] ?? '') ?></textarea></label>
<label>Project image (JPG, PNG or WebP; up to 5 MB)<input type="file" name="image" accept="image/jpeg,image/png,image/webp"<?= $editId ? '' : ' required' ?>></label>
<label class="checkbox-label"><input type="checkbox" name="is_active" value="1"<?= !empty($edit['is_active']) ? ' checked' : '' ?>>Accepting donations</label>
<div class="form-actions full-width"><button type="submit"><?= uiIcon('save') ?>Save project</button><a href="<?= escapeHtml(appUrl('public/admin/donation_projects.php')) ?>">Cancel</a></div></form></details>
<div class="donation-grid"><?php foreach ($projects as $project): ?><article class="donation-card"><img class="donation-cover" src="<?= escapeHtml(appUrl('public/donation_image.php?admin=1&id=' . $project['id'])) ?>" alt="<?= escapeHtml($project['title']) ?>" loading="lazy"><div class="donation-card-body"><span class="status-badge <?= $project['is_active'] ? 'status-approved' : 'status-pending' ?>"><?= $project['is_active'] ? 'Active' : 'Closed' ?></span><h2><?= escapeHtml($project['title']) ?></h2><div class="form-actions"><a href="<?= escapeHtml(appUrl('public/admin/donation_projects.php?edit=' . $project['id'])) ?>"><?= uiIcon('pencil') ?>Edit</a><a href="<?= escapeHtml(appUrl('public/admin/donation_ledger.php?project=' . $project['id'])) ?>">View payments</a></div></div></article><?php endforeach; ?></div>
<?php if (!$projects && !$error): ?><p class="notice">No donation projects yet.</p><?php endif; ?></section><?php pageFooter(); ?>
