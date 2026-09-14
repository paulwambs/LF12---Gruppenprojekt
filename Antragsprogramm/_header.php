<?php
// Erwartet: $u = current_user() (kann null sein), $active = aktiver Navi-Key
$u = $u ?? current_user();
$active = $active ?? '';
?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Unterrichtsorganisation – Antragssystem</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>
<div class="topbar">
  <h1>Unterrichtsorganisation – Antragssystem</h1>
  <div class="userbox">
    <?php if ($u): ?>
      <span><?= h($u['display_name']) ?> — <?= h(ROLE_LABELS[$u['role']]) ?></span>
      <a href="logout.php">Abmelden</a>
    <?php else: ?>
      <span>Nicht angemeldet</span>
    <?php endif; ?>
  </div>
</div>
<?php if ($u): ?>
<div class="nav">
  <a href="index.php" class="<?= $active==='dashboard'?'active':'' ?>">Übersicht</a>
  <?php if ($u['role'] === 'LK'): ?>
    <a href="neuer_antrag.php" class="<?= $active==='neu'?'active':'' ?>">Neuer Antrag</a>
    <a href="meine_antraege.php" class="<?= $active==='meine'?'active':'' ?>">Meine Anträge</a>
  <?php endif; ?>
  <?php if (in_array($u['role'], ['AL','StSL','SL'], true)): ?>
    <a href="bearbeitung.php" class="<?= $active==='bearbeitung'?'active':'' ?>">Zu bearbeiten</a>
  <?php endif; ?>
</div>
<?php endif; ?>
<div class="wrap">
<?php
// Flash-Meldung anzeigen (einmalig)
if (!empty($_SESSION['flash'])) {
    foreach ($_SESSION['flash'] as $f) {
        echo '<div class="msg ' . h($f['type']) . '">' . h($f['text']) . '</div>';
    }
    unset($_SESSION['flash']);
}
