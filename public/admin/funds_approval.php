<?php
require_once dirname(__DIR__, 2) . '/includes/auth_staff.php';

requireRole('admin');
header('Cache-Control: no-store');
// Retired module: old bookmarks and submissions must not approve funds.
redirectTo('public/admin/index.php');
