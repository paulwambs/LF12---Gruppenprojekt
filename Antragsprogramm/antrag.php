<?php
require_once __DIR__ . '/db.php';
$u = require_login();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare(
    'SELECT a.*, ut.display_name AS antragsteller_name, ab.name AS abteilung_name
     FROM antraege a
     JOIN users ut ON ut.id = a.antragsteller_id
     LEFT JOIN abteilung ab ON ab.id = a.abteilung_id
     WHERE a.id = ?'
);
$stmt->execute([$id]);
$a = $stmt->fetch();

if (!$a) {
    http_response_code(404);
    $active = '';
    require __DIR__ . '/_header.php';
    echo '<div class="card"><h2>Nicht gefunden</h2><p>Dieser Antrag existiert nicht.</p></div>';
    require __DIR__ . '/_footer.php';
    exit;
}

// Zugriffsschutz: Lehrkraft darf nur eigene Anträge sehen
if ($u['role'] === 'LK' && (int)$a['antragsteller_id'] !== $u['id']) {
    http_response_code(403);
    exit('Kein Zugriff auf diesen Antrag.');
}

$daten = json_decode($a['daten'], true) ?: [];
$felder = ANLASS_FELDER[$a['anlass']] ?? [];

// Log laden
$logStmt = $pdo->prepare(
    'SELECT l.*, us.display_name AS user_name
     FROM antrag_log l LEFT JOIN users us ON us.id = l.user_id
     WHERE l.antrag_id = ? ORDER BY l.created_at ASC, l.id ASC'
);
$logStmt->execute([$id]);
$logs = $logStmt->fetchAll();

// Datumswerte hübsch darstellen
function fmt_wert(string $v): string
{
    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
        return date('d.m.Y', strtotime($v));
    }
    return $v;
}

$active = ($u['role'] === 'LK') ? 'meine' : 'bearbeitung';
require __DIR__ . '/_header.php';
?>
<div class="card">
  <h2>Antrag <?= h($a['antragsnummer']) ?></h2>
  <div class="progress">
    <?php foreach (progress_steps($a['status']) as $s): ?>
      <div class="step <?= h($s['cls']) ?>"><?= h($s['label']) ?></div>
    <?php endforeach; ?>
  </div>
  <p>Status: <span class="badge <?= h($a['status']) ?>"><?= h(STATUS_LABELS[$a['status']]) ?></span></p>

  <dl class="detail">
    <dt>Antragsteller</dt><dd><?= h($a['antragsteller_name']) ?></dd>
    <dt>Abteilung</dt><dd><?= h($a['abteilung_name'] ?? '–') ?></dd>
    <dt>Anlass</dt><dd><?= h(ANLASS_LABELS[$a['anlass']]) ?></dd>
    <dt>Eingereicht am</dt><dd><?= h(date('d.m.Y H:i', strtotime($a['created_at']))) ?></dd>
    <?php foreach ($felder as $key => $label): ?>
      <dt><?= h($label) ?></dt><dd><?= h(fmt_wert((string)($daten[$key] ?? ''))) ?: '–' ?></dd>
    <?php endforeach; ?>
  </dl>
</div>

<div class="card">
  <h2>Bearbeitungsprotokoll (Log)</h2>
  <table class="logtable">
    <tr><th>Zeitpunkt</th><th>Aktion</th><th>Durch</th><th>Kommentar</th></tr>
    <?php foreach ($logs as $l): ?>
      <tr>
        <td><?= h(date('d.m.Y H:i', strtotime($l['created_at']))) ?></td>
        <td><?= h(LOG_LABELS[$l['aktion']] ?? $l['aktion']) ?></td>
        <td><?= h($l['user_name'] ?? '–') ?></td>
        <td><?= h($l['kommentar'] ?? '') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
  <a class="btn secondary" href="<?= $u['role']==='LK' ? 'meine_antraege.php' : 'bearbeitung.php' ?>">Zurück</a>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
