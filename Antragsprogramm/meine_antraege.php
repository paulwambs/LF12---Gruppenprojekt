<?php
require_once __DIR__ . '/db.php';
$u = require_role('LK');
$active = 'meine';

$stmt = db()->prepare(
    'SELECT * FROM antraege WHERE antragsteller_id = ? ORDER BY created_at DESC'
);
$stmt->execute([$u['id']]);
$antraege = $stmt->fetchAll();

require __DIR__ . '/_header.php';
?>
<div class="card">
  <h2>Meine Anträge &amp; Bearbeitungsstand</h2>
  <?php if (!$antraege): ?>
    <p>Du hast noch keine Anträge gestellt. <a href="neuer_antrag.php">Jetzt einen Antrag stellen</a>.</p>
  <?php else: ?>
    <table>
      <tr><th>Nr.</th><th>Anlass</th><th>Eingereicht</th><th>Status</th><th></th></tr>
      <?php foreach ($antraege as $a): ?>
        <tr>
          <td><?= h($a['antragsnummer']) ?></td>
          <td><?= h(ANLASS_LABELS[$a['anlass']]) ?></td>
          <td><?= h(date('d.m.Y H:i', strtotime($a['created_at']))) ?></td>
          <td><span class="badge <?= h($a['status']) ?>"><?= h(STATUS_LABELS[$a['status']]) ?></span></td>
          <td><a class="btn secondary" style="margin:0;padding:5px 12px;" href="antrag.php?id=<?= (int)$a['id'] ?>">Verlauf</a></td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_footer.php'; ?>
