<?php
require_once dirname(__DIR__) . '/includes/auth_member.php';
require_once dirname(__DIR__) . '/includes/layout.php';

$member = requireMember();
$projects = [];
$error = '';

try {
    $projects = getDb()->query('SELECT id, name, description, quote, image_path FROM projects WHERE is_active = 1 ORDER BY id DESC')->fetchAll();
} catch (Throwable $exception) {
    error_log('Active projects could not be loaded: ' . $exception->getMessage());
    $error = 'Projects are temporarily unavailable. Please try again later.';
}

pageHeader('Community projects', $member);
?>
<section class="workspace">
    <p class="eyebrow">COMMUNITY</p>
    <h1>Active projects</h1>
    <p class="intro">See the projects currently underway in our community.</p>
    <?php showError($error); ?>
    <?php if (!$error && $projects === []): ?>
        <div class="empty-state"><h2>No active projects right now</h2></div>
    <?php endif; ?>
    <div class="project-grid">
        <?php foreach ($projects as $project): ?>
            <article class="project-card">
                <?php if ($project['image_path']): ?>
                    <img class="project-image" src="<?= escapeHtml(appUrl($project['image_path'])) ?>" alt="<?= escapeHtml($project['name']) ?>">
                <?php endif; ?>
                <div class="project-card-content">
                    <h2><?= escapeHtml($project['name']) ?></h2>
                    <p><?= nl2br(escapeHtml($project['description'])) ?></p>
                    <?php if (trim((string) $project['quote']) !== ''): ?>
                        <blockquote><?= nl2br(escapeHtml($project['quote'])) ?></blockquote>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php pageFooter(); ?>
