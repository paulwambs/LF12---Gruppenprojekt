<?php
require_once __DIR__ . '/db.php';
$u = require_role('AL', 'StSL', 'SL');
$active = 'bearbeitung';
$pdo = db();
$wf = WORKFLOW[$u['role']];

/* -------------------- Aktion verarbeiten -------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action    = $_POST['action'] ?? '';
    $antragId  = (int)($_POST['antrag_id'] ?? 0);
    $kommentar = trim($_POST['kommentar'] ?? '');

    // Antrag sperren und Status prüfen (verhindert doppelte/ungültige Übergänge)
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM antraege WHERE id = ? FOR UPDATE');
    $stmt->execute([$antragId]);
    $a = $stmt->fetch();

    $zulaessig = $a
        && $a['status'] === $wf['from']
        && ($u['role'] !== 'AL' || (int)$a['abteilung_id'] === (int)$u['abteilung_id']);

    if (!$zulaessig) {
        $pdo->rollBack();
        flash('err', 'Aktion nicht möglich – der Antrag wurde zwischenzeitlich bearbeitet.');
        redirect('bearbeitung.php');
    }

    if ($action === 'abzeichnen') {
        $pdo->prepare('UPDATE antraege SET status = ? WHERE id = ?')
            ->execute([$wf['to'], $antragId]);
        $pdo->prepare('INSERT INTO antrag_log (antrag_id, user_id, aktion, kommentar) VALUES (?,?,?,?)')
            ->execute([$antragId, $u['id'], $wf['log'], $kommentar ?: null]);
        $pdo->commit();
        $verb = $u['role'] === 'SL' ? 'genehmigt' : 'abgezeichnet';
        flash('ok', "Antrag {$a['antragsnummer']} wurde $verb. Log-Eintrag erstellt.");
    } elseif ($action === 'ablehnen') {
        $pdo->prepare('UPDATE antraege SET status = "abgelehnt" WHERE id = ?')
            ->execute([$antragId]);
        $pdo->prepare('INSERT INTO antrag_log (antrag_id, user_id, aktion, kommentar) VALUES (?,?,"abgelehnt",?)')
            ->execute([$antragId, $u['id'], $kommentar ?: null]);
        $pdo->commit();
        flash('ok', "Antrag {$a['antragsnummer']} wurde abgelehnt. Log-Eintrag erstellt.");
    } else {
        $pdo->rollBack();
    }
    redirect('bearbeitung.php');
}

/* -------------------- Liste laden -------------------- */
if ($u['role'] === 'AL') {
    $stmt = $pdo->prepare(
        'SELECT a.*, ut.display_name AS antragsteller_name
         FROM antraege a JOIN users ut ON ut.id = a.antragsteller_id
         WHERE a.status = ? AND a.abteilung_id = ? ORDER BY a.created_at ASC'
    );
    $stmt->execute([$wf['from'], $u['abteilung_id']]);
} else {
    $stmt = $pdo->prepare(
        'SELECT a.*, ut.display_name AS antragsteller_name
         FROM antraege a JOIN users ut ON ut.id = a.antragsteller_id
         WHERE a.status = ? ORDER BY a.created_at ASC'
    );
    $stmt->execute([$wf['from']]);
}
$offene = $stmt->fetchAll();

$verbLabel = $u['role'] === 'SL' ? 'Genehmigen' : 'Abzeichnen';

require __DIR__ . '/_header.php';
?>
<div class="card">
  <h2>Anträge zur Bearbeitung
    <?= $u['role']==='AL' ? '(meine Abteilung)' : '' ?></h2>
  <p class="hint">Als <?= h(ROLE_LABELS[$u['role']]) ?> bearbeitest du Anträge im Status
    „<?= h(STATUS_LABELS[$wf['from']]) ?>“.</p>

  <?php if (!$offene): ?>
    <p>Aktuell liegen keine Anträge zur Bearbeitung vor.</p>
  <?php else: ?>
    <table>
      <tr><th>Nr.</th><th>Antragsteller</th><th>Anlass</th><th>Eingereicht</th><th>Aktion</th></tr>
      <?php foreach ($offene as $a): ?>
        <tr>
          <td><?= h($a['antragsnummer']) ?></td>
          <td><?= h($a['antragsteller_name']) ?></td>
          <td><?= h(ANLASS_LABELS[$a['anlass']]) ?></td>
          <td><?= h(date('d.m.Y H:i', strtotime($a['created_at']))) ?></td>
          <td style="white-space:nowrap;">
            <a class="btn secondary" style="margin:0;padding:5px 10px;" href="antrag.php?id=<?= (int)$a['id'] ?>">Details</a>
            <button class="btn green" style="margin:0;padding:5px 10px;"
              onclick="openConfirm(<?= (int)$a['id'] ?>, '<?= h($a['antragsnummer']) ?>', '<?= h(addslashes($a['antragsteller_name'])) ?>')">
              <?= h($verbLabel) ?>
            </button>
            <button class="btn red" style="margin:0;padding:5px 10px;"
              onclick="ablehnen(<?= (int)$a['id'] ?>, '<?= h($a['antragsnummer']) ?>')">Ablehnen</button>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
</div>

<!-- verstecktes Formular für alle Aktionen -->
<form method="post" action="bearbeitung.php" id="actionForm" style="display:none;">
  <?= csrf_field() ?>
  <input type="hidden" name="action" id="fAction">
  <input type="hidden" name="antrag_id" id="fAntragId">
  <input type="hidden" name="kommentar" id="fKommentar">
</form>

<!-- doppelte Bestätigung -->
<div class="modal-overlay" id="confirmModal">
  <div class="modal-box">
    <h3><?= h($verbLabel) ?></h3>
    <div id="modalStep1">
      <p id="modalText"></p>
      <button class="btn green" onclick="confirmStep2()">Ja, <?= h(strtolower($verbLabel)) ?></button>
      <button class="btn secondary" onclick="closeModal()">Abbrechen</button>
    </div>
    <div id="modalStep2" style="display:none;">
      <p><strong>Sicherheitsabfrage:</strong> Bitte erneut bestätigen. Diese Aktion erzeugt einen Log-Eintrag.</p>
      <button class="btn green" onclick="finalize()">Endgültig bestätigen</button>
      <button class="btn secondary" onclick="closeModal()">Abbrechen</button>
    </div>
  </div>
</div>

<script>
var curId = null;
function openConfirm(id, nr, name){
  curId = id;
  document.getElementById('modalText').innerText =
    'Antrag ' + nr + ' von ' + name + ' wirklich freigeben?';
  document.getElementById('modalStep1').style.display='block';
  document.getElementById('modalStep2').style.display='none';
  document.getElementById('confirmModal').classList.add('active');
}
function confirmStep2(){
  document.getElementById('modalStep1').style.display='none';
  document.getElementById('modalStep2').style.display='block';
}
function finalize(){
  document.getElementById('fAction').value = 'abzeichnen';
  document.getElementById('fAntragId').value = curId;
  document.getElementById('actionForm').submit();
}
function closeModal(){
  document.getElementById('confirmModal').classList.remove('active');
  curId = null;
}
function ablehnen(id, nr){
  if(!confirm('Antrag ' + nr + ' wirklich ablehnen?')) return;
  var grund = prompt('Grund der Ablehnung (optional):', '');
  if(grund === null) return; // Abbrechen
  document.getElementById('fAction').value = 'ablehnen';
  document.getElementById('fAntragId').value = id;
  document.getElementById('fKommentar').value = grund;
  document.getElementById('actionForm').submit();
}
</script>
<?php require __DIR__ . '/_footer.php'; ?>
