<?php
require_once __DIR__ . '/db.php';
$u = require_login();
$active = 'dashboard';
$pdo = db();

// Rollenabhängige Kennzahlen
$offen = 0;
if ($u['role'] === 'LK') {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM antraege WHERE antragsteller_id = ?');
    $stmt->execute([$u['id']]);
    $meine = (int)$stmt->fetchColumn();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM antraege WHERE antragsteller_id = ? AND status = 'genehmigt'");
    $stmt->execute([$u['id']]);
    $genehmigt = (int)$stmt->fetchColumn();
} elseif (isset(WORKFLOW[$u['role']])) {
    $wf = WORKFLOW[$u['role']];
    if ($u['role'] === 'AL') {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM antraege WHERE status = ? AND abteilung_id = ?');
        $stmt->execute([$wf['from'], $u['abteilung_id']]);
    } else {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM antraege WHERE status = ?');
        $stmt->execute([$wf['from']]);
    }
    $offen = (int)$stmt->fetchColumn();
}

require __DIR__ . '/_header.php';
?>
<div class="card">
  <h2>Meine Übersicht</h2>
  <p>Angemeldet als <strong><?= h($u['display_name']) ?></strong> (<?= h(ROLE_LABELS[$u['role']]) ?>).</p>

  <?php if ($u['role'] === 'LK'): ?>
    <p>Du hast bisher <strong><?= $meine ?></strong> Anträge gestellt, davon <strong><?= $genehmigt ?></strong> genehmigt.</p>
    <a class="btn accent" href="neuer_antrag.php">+ Neuen Antrag stellen</a>
    <a class="btn secondary" href="meine_antraege.php">Meine Anträge &amp; Status</a>
  <?php else: ?>
    <p>Aktuell warten <strong><?= $offen ?></strong> Anträge auf deine
      <?= $u['role']==='SL' ? 'Genehmigung' : 'Abzeichnung' ?>.</p>
    <a class="btn accent" href="bearbeitung.php">Anträge zur Bearbeitung</a>
  <?php endif; ?>
</div>

<div class="card">
  <h2>Ablauf des Genehmigungsprozesses</h2>
  <div class="progress">
    <div class="step done">1. Lehrkraft stellt Antrag</div>
    <div class="step done">2. Abteilungsleiter zeichnet ab</div>
    <div class="step done">3. Stellv. Schulleiter zeichnet ab</div>
    <div class="step done">4. Schulleiter genehmigt</div>
  </div>
  <p class="hint">Jeder Schritt wird protokolliert. Der Antragsteller sieht den aktuellen Stand jederzeit unter „Meine Anträge“.</p>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
