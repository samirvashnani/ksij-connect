<?php
require_once __DIR__ . '/auth_member.php';
require_once __DIR__ . '/layout.php';
$member = requireMember();
header('Cache-Control: private, no-store');
$sections = [
    'events' => ['Events','calendar-days','events','id,title,description,event_date,event_time,venue,registration_required,registration_link',['title','description','venue'],'event_date IS NULL,event_date,event_time,id'],
    'scholarships' => ['Scholarships','graduation-cap','scholarships','id,name,eligibility,amount,deadline,how_to_apply',['name','eligibility','how_to_apply'],'deadline IS NULL,deadline,id'],
    'welfare' => ['Welfare schemes','hand-heart','welfare_schemes','id,scheme_name,type,eligibility,coverage,how_to_apply',['scheme_name','type','eligibility','coverage','how_to_apply'],'scheme_name,id'],
    'contacts' => ['Office contacts','contact','contacts','id,department,person_name,designation,phone,email,availability',['department','person_name','designation','phone','email'],'department,person_name,id'],
    'announcements' => ['Announcements','newspaper','news_updates','id,title,content,category,is_pinned,posted_at',['title','content','category'],'is_pinned DESC,posted_at DESC,id DESC'],
    'information' => ['General information','book-open','general_info','id,title,category,content,updated_at',['title','category','content'],'category,title,id'],
];
if (!isset($sections[$informationSection ?? ''])) { http_response_code(404); exit; }
[$title,$icon,$table,$fields,$searchFields,$order] = $sections[$informationSection];
$path = 'public/member_' . $informationSection . '.php';
$today = (new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
$options = [];
if ($informationSection === 'events') { $options = ['upcoming'=>'Upcoming & today','past'=>'Past events','all'=>'All events']; }
if ($informationSection === 'scholarships') { $options = ['current'=>'Current deadlines','closed'=>'Deadline passed','all'=>'All scholarships']; }
if ($informationSection === 'welfare') { $options = ['all'=>'All types','medical'=>'Medical','ration'=>'Ration','education'=>'Education','emergency'=>'Emergency','other'=>'Other']; }
$filter = is_string($_GET['filter'] ?? null) ? $_GET['filter'] : (array_key_first($options) ?? 'all');
if ($options && !isset($options[$filter])) { $filter = array_key_first($options); }
$search = is_string($_GET['search'] ?? null) && mb_check_encoding($_GET['search'], 'UTF-8') ? mb_substr(trim($_GET['search']),0,160) : '';
$page = max(1, filter_var($_GET['page'] ?? 1,FILTER_VALIDATE_INT) ?: 1);
$rows=[]; $total=0; $pages=1; $error='';
try {
    $where='1=1'; $params=[];
    if ($informationSection === 'events' && $filter !== 'all') {
        $where .= $filter === 'past' ? " AND event_date < ? AND event_date <> '0000-00-00'" : " AND (event_date >= ? OR event_date IS NULL OR event_date = '0000-00-00')";
        $params[]=$today;
        if ($filter === 'past') { $order='event_date DESC,event_time DESC,id DESC'; }
    }
    if ($informationSection === 'scholarships' && $filter !== 'all') {
        $where .= $filter === 'closed' ? " AND deadline < ? AND deadline <> '0000-00-00'" : " AND (deadline >= ? OR deadline IS NULL OR deadline = '0000-00-00')";
        $params[]=$today;
    }
    if ($informationSection === 'welfare' && $filter !== 'all') { $where .= ' AND type=?'; $params[]=$filter; }
    if ($search !== '') {
        $pattern='%' . str_replace(['!','%','_'],['!!','!%','!_'],$search) . '%';
        $clauses=[];
        foreach ($searchFields as $field) { $clauses[]=$field . " LIKE ? ESCAPE '!'"; $params[]=$pattern; }
        $where .= ' AND (' . implode(' OR ',$clauses) . ')';
    }
    // Table, fields and sort order are fixed above, never supplied by the request.
    $s=getDb()->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $where); $s->execute($params); $total=(int)$s->fetchColumn();
    $pages=max(1,(int)ceil($total/12)); $page=min($page,$pages);
    $s=getDb()->prepare('SELECT ' . $fields . ' FROM ' . $table . ' WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT 12 OFFSET ' . (($page-1)*12)); $s->execute($params); $rows=$s->fetchAll();
} catch (Throwable $e) { error_log('Member information ' . $informationSection . ': ' . get_class($e)); $error='This section is temporarily unavailable. Please try again shortly.'; }
function memberInformationDate(?string $date): string {
    if (!$date || substr($date,0,10)==='0000-00-00') { return 'To be announced'; }
    $parsed=DateTimeImmutable::createFromFormat('!Y-m-d',substr($date,0,10));
    return $parsed ? $parsed->format('d M Y') : 'To be announced';
}
function memberInformationField(string $label, ?string $value): void {
    echo '<div><dt>' . escapeHtml($label) . '</dt><dd>' . escapeHtml(trim((string)$value) ?: 'Contact the office for details.') . '</dd></div>';
}
function memberInformationWebLink(?string $url): ?string {
    $url=trim((string)$url);
    return filter_var($url,FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['https','http'],true) ? $url : null;
}
function memberInformationCopy(?string $text): void {
    $text=trim((string)$text);
    if ($text==='') { return; }
    if (mb_strlen($text)<=500) { echo '<p class="information-copy">' . escapeHtml($text) . '</p>'; return; }
    echo '<details class="information-expand"><summary><span class="information-excerpt">' . escapeHtml(mb_substr($text,0,240)) . '...</span><span class="information-read-more">Read full details</span></summary><p class="information-copy">' . escapeHtml($text) . '</p></details>';
}
function memberInformationApplication(?string $instructions): void {
    $url=memberInformationWebLink($instructions);
    echo '<section class="information-application"><h3>' . uiIcon('file-plus') . 'How to apply</h3>';
    if ($url) { echo '<a class="button-link button-secondary" target="_blank" rel="noopener noreferrer" href="' . escapeHtml($url) . '">Application details ' . uiIcon('arrow-up-right') . '</a>'; }
    else { echo '<p class="information-copy">' . escapeHtml(trim((string)$instructions) ?: 'Contact the office for application details.') . '</p>'; }
    echo '</section>';
}
pageHeader($title,$member);
?><section class="workspace member-information information-<?= escapeHtml($informationSection) ?>" data-information><header class="page-heading"><div><p class="eyebrow">COMMUNITY INFORMATION</p><h1><?= uiIcon($icon) ?><?= escapeHtml($title) ?></h1></div><a class="document-link" href="<?= escapeHtml(appUrl('public/member_chat.php')) ?>">Dashboard <?= uiIcon('arrow-up-right') ?></a></header>
<nav class="information-navigation" aria-label="Information sections"><?php foreach ($sections as $section=>$definition): ?><a href="<?= escapeHtml(appUrl('public/member_' . $section . '.php')) ?>"<?= $informationSection===$section ? ' aria-current="page"' : '' ?>><?= uiIcon($definition[1]) ?><span><?= escapeHtml($definition[0]) ?></span></a><?php endforeach; ?></nav>
<form method="get" action="<?= escapeHtml(appUrl($path)) ?>" class="information-filters" data-information-filter><label for="information-search">Search <?= escapeHtml(strtolower($title)) ?><span class="information-search-field"><?= uiIcon('search') ?><input id="information-search" name="search" type="search" maxlength="160" value="<?= escapeHtml($search) ?>" placeholder="Enter a name, topic or keyword"></span></label><?php if ($options): ?><label for="information-view"><?= $informationSection==='welfare' ? 'Scheme type' : 'View' ?><select id="information-view" name="filter"><?php foreach ($options as $key=>$label): ?><option value="<?= $key ?>"<?= $filter===$key ? ' selected' : '' ?>><?= escapeHtml($label) ?></option><?php endforeach; ?></select></label><?php endif; ?><div class="information-filter-actions"><button type="submit" class="icon-button" title="Search" aria-label="Search"><?= uiIcon('search') ?></button><a class="icon-button" href="<?= escapeHtml(appUrl($path)) ?>" title="Reset filters" aria-label="Reset filters"><?= uiIcon('refresh-cw') ?></a></div></form>
<p role="status" aria-live="polite" data-information-status></p>
<div data-information-results><?php showError($error); ?><div class="information-results-heading"><h2><?= escapeHtml($options[$filter] ?? $title) ?></h2><span class="muted" data-information-count><?= $total ? (($page-1)*12+1) . '-' . min($page*12,$total) . ' of ' : '' ?><?= $total ?> <?= $total===1 ? 'result' : 'results' ?></span></div>
<?php if (!$rows && !$error): ?><div class="operations-empty"><?= uiIcon($icon) ?><h2>No matching records</h2><p><?= $search!=='' ? 'Try another search or view.' : 'Please check back for updates.' ?></p></div><?php endif; ?>
<div class="information-list"><?php foreach ($rows as $row): ?><article class="information-record<?= !empty($row['is_pinned']) ? ' is-pinned' : '' ?>"><?php if ($informationSection==='events'): $eventDate=!empty($row['event_date']) && $row['event_date']!=='0000-00-00' ? DateTimeImmutable::createFromFormat('!Y-m-d',$row['event_date']) : false; ?><div class="information-date-tile"><span><?= $eventDate ? escapeHtml($eventDate->format('M')) : 'DATE' ?></span><strong><?= $eventDate ? escapeHtml($eventDate->format('d')) : '?' ?></strong><small><?= $eventDate ? escapeHtml($eventDate->format('Y')) : 'Pending' ?></small></div><?php else: ?><div class="information-record-icon"><?= uiIcon($icon) ?></div><?php endif; ?><div class="information-record-body">
<?php if ($informationSection==='events'): $past=!empty($row['event_date']) && $row['event_date']!=='0000-00-00' && $row['event_date']<$today; ?>
<div class="information-record-heading"><h2><?= escapeHtml($row['title']) ?></h2><span class="status-badge <?= $past || !$eventDate ? 'status-pending' : 'status-approved' ?>"><?= !$eventDate ? 'Date to be announced' : ($past ? 'Past event' : ($row['event_date']===$today ? 'Today' : 'Upcoming')) ?></span></div><dl class="information-meta"><?php memberInformationField('Time',$row['event_time'] ? substr($row['event_time'],0,5) : 'To be announced'); memberInformationField('Venue',$row['venue']); ?></dl><?php memberInformationCopy($row['description']); ?>
<?php if (!$past && $row['registration_required']): $url=memberInformationWebLink($row['registration_link']); ?><?php if ($url): ?><a class="button-link button-secondary" href="<?= escapeHtml($url) ?>" target="_blank" rel="noopener noreferrer">Register <?= uiIcon('arrow-up-right') ?></a><?php else: ?><p class="notice">Registration required. Contact the office for registration details.</p><?php endif; ?><?php elseif (!$row['registration_required']): ?><p class="muted">No registration required.</p><?php endif; ?>
<?php elseif ($informationSection==='scholarships'): $closed=!empty($row['deadline']) && $row['deadline']!=='0000-00-00' && $row['deadline']<$today; ?>
<div class="information-record-heading"><h2><?= escapeHtml($row['name']) ?></h2><span class="status-badge <?= $closed ? 'status-pending' : 'status-approved' ?>"><?= $closed ? 'Deadline passed' : ($row['deadline'] && $row['deadline']!=='0000-00-00' ? 'Within deadline' : 'Deadline not specified') ?></span></div><dl class="information-meta information-award"><?php memberInformationField('Financial support',$row['amount']); memberInformationField('Application deadline',memberInformationDate($row['deadline'])); ?></dl><div class="information-support-columns"><dl class="information-details"><?php memberInformationField('Eligibility',$row['eligibility']); ?></dl><?php memberInformationApplication($row['how_to_apply']); ?></div>
<?php elseif ($informationSection==='welfare'): ?>
<div class="information-record-heading"><h2><?= escapeHtml($row['scheme_name']) ?></h2><span class="status-badge status-open"><?= escapeHtml(ucfirst($row['type'])) ?></span></div><dl class="information-meta information-award"><?php memberInformationField('Coverage',$row['coverage']); ?></dl><div class="information-support-columns"><dl class="information-details"><?php memberInformationField('Eligibility',$row['eligibility']); ?></dl><?php memberInformationApplication($row['how_to_apply']); ?></div>
<?php elseif ($informationSection==='contacts'): ?>
<p class="information-department"><?= escapeHtml($row['department']) ?></p><h2><?= escapeHtml(trim((string)$row['person_name']) ?: $row['department'] . ' office') ?></h2><?php if (trim((string)$row['designation'])!==''): ?><p class="information-designation"><?= escapeHtml($row['designation']) ?></p><?php endif; ?><dl class="information-meta"><?php memberInformationField('Office availability',$row['availability']); ?></dl><div class="information-contact-links">
<?php $phone=trim((string)$row['phone']); $dial=preg_replace('/[\s().-]/','',$phone); if ($phone!==''): ?><?php if (preg_match('/^\+?[0-9]{5,15}$/D',$dial)): ?><a href="tel:<?= escapeHtml($dial) ?>"><?= escapeHtml($phone) ?></a><?php else: ?><span><?= escapeHtml($phone) ?></span><?php endif; ?><?php endif; ?>
<?php $email=trim((string)$row['email']); if ($email!==''): ?><?php if (filter_var($email,FILTER_VALIDATE_EMAIL)): ?><a href="mailto:<?= escapeHtml($email) ?>"><?= escapeHtml($email) ?></a><?php else: ?><span><?= escapeHtml($email) ?></span><?php endif; ?><?php endif; ?></div>
<?php else: ?>
<div class="information-news-meta"><span><?= escapeHtml(trim((string)$row['category']) ?: 'Community') ?></span><span><?= $informationSection==='announcements' ? 'Published' : 'Updated' ?> <?= escapeHtml(memberInformationDate($row[$informationSection==='announcements' ? 'posted_at' : 'updated_at'])) ?></span><?php if (!empty($row['is_pinned'])): ?><span class="status-badge status-pending">Pinned notice</span><?php endif; ?></div><div class="information-record-heading"><h2><?= escapeHtml($row['title']) ?></h2></div><?php memberInformationCopy($row['content']); ?>
<?php endif; ?></div></article><?php endforeach; ?></div>
<?php if ($pages>1): ?><nav class="information-pagination" aria-label="Results pages"><?php if ($page>1): ?><a data-information-page href="<?= escapeHtml(appUrl($path . '?' . http_build_query(['search'=>$search,'filter'=>$filter,'page'=>$page-1]))) ?>"><?= uiIcon('chevron-left') ?>Previous</a><?php endif; ?><span>Page <?= $page ?> of <?= $pages ?></span><?php if ($page<$pages): ?><a data-information-page href="<?= escapeHtml(appUrl($path . '?' . http_build_query(['search'=>$search,'filter'=>$filter,'page'=>$page+1]))) ?>">Next <?= uiIcon('chevron-right') ?></a><?php endif; ?></nav><?php endif; ?></div></section><script src="<?= escapeHtml(appUrl('assets/js/member_information.js?v=2')) ?>" defer></script><?php pageFooter(); ?>
