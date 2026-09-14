<?php
require_once __DIR__ . '/db.php';
$u = require_role('LK');
$active = 'neu';
$pdo = db();

// Welche Felder gehören zu welchem Anlass (zentral in db.php definiert)
$FELDER = ANLASS_FELDER;

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $anlass = $_POST['anlass'] ?? '';
    if (!isset($FELDER[$anlass])) {
        $errors[] = 'Bitte einen Anlass auswählen.';
    } else {
        // Nur die zum Anlass gehörenden Felder übernehmen
        $daten = [];
        foreach ($FELDER[$anlass] as $key => $label) {
            $daten[$key] = trim($_POST[$anlass . '_' . $key] ?? '');
        }
        // Minimale Pflichtprüfung: mindestens ein Grund-/Beschreibungsfeld
        $pflicht = $anlass === 'tausch' ? 'tausch_mit' : 'grund';
        if ($daten[$pflicht] === '') {
            $errors[] = 'Bitte das Feld „' . $FELDER[$anlass][$pflicht] . '“ ausfüllen.';
        }

        if (!$errors) {
            // Antragsnummer JJJJ-NNNN generieren
            $jahr = date('Y');
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM antraege WHERE antragsnummer LIKE ?");
            $stmt->execute([$jahr . '-%']);
            $nummer = sprintf('%s-%04d', $jahr, ((int)$stmt->fetchColumn()) + 1);

            $pdo->beginTransaction();
            $ins = $pdo->prepare(
                'INSERT INTO antraege (antragsnummer, antragsteller_id, abteilung_id, anlass, daten, status)
                 VALUES (?,?,?,?,?, "gestellt")'
            );
            $ins->execute([
                $nummer,
                $u['id'],
                $u['abteilung_id'],
                $anlass,
                json_encode($daten, JSON_UNESCAPED_UNICODE),
            ]);
            $antragId = (int)$pdo->lastInsertId();
            $pdo->prepare(
                'INSERT INTO antrag_log (antrag_id, user_id, aktion, kommentar)
                 VALUES (?,?, "erstellt", ?)'
            )->execute([$antragId, $u['id'], 'Antrag eingereicht']);
            $pdo->commit();

            flash('ok', "Antrag $nummer wurde abgesendet und protokolliert.");
            redirect('meine_antraege.php');
        }
    }
}

require __DIR__ . '/_header.php';
?>
<div class="card">
  <h2>Neuer Antrag</h2>
  <?php foreach ($errors as $e): ?><div class="msg err"><?= h($e) ?></div><?php endforeach; ?>

  <div class="grid2">
    <div><label>Antragsteller</label><input type="text" value="<?= h($u['display_name']) ?>" disabled></div>
    <div><label>Datum</label><input type="text" value="<?= date('d.m.Y') ?>" disabled></div>
  </div>

  <form method="post" action="neuer_antrag.php" id="antragForm">
    <?= csrf_field() ?>
    <label>Anlass wählen (Punkt 1)</label>
    <div class="radio-list">
      <?php foreach (ANLASS_LABELS as $key => $label): ?>
        <label>
          <input type="radio" name="anlass" value="<?= h($key) ?>"
                 onclick="showAnlass('<?= h($key) ?>')"
                 <?= (($_POST['anlass'] ?? '') === $key) ? 'checked' : '' ?>>
          <?= h($label) ?>
        </label>
      <?php endforeach; ?>
    </div>

    <!-- Unterrichtsvertretung -->
    <div class="formular-anlass" id="anlass-vertretung">
      <label>Grund</label>
      <input type="text" name="vertretung_grund" placeholder="z.B. Fortbildung, dienstliche Verpflichtung"
             value="<?= h($_POST['vertretung_grund'] ?? '') ?>">
      <div class="grid2">
        <div><label>Beginn</label><input type="date" name="vertretung_beginn" value="<?= h($_POST['vertretung_beginn'] ?? '') ?>"></div>
        <div><label>Ende</label><input type="date" name="vertretung_ende" value="<?= h($_POST['vertretung_ende'] ?? '') ?>"></div>
      </div>
      <label>Vertretungsmaterial</label>
      <select name="vertretung_material">
        <option>auf Lernplattform</option>
        <option>per Mail</option>
        <option>im Sekretariat hinterlegt</option>
      </select>
    </div>

    <!-- Unterrichtstausch -->
    <div class="formular-anlass" id="anlass-tausch">
      <label>Ursprünglicher Termin</label>
      <div class="grid2">
        <div><input type="date" name="tausch_termin_datum" value="<?= h($_POST['tausch_termin_datum'] ?? '') ?>"></div>
        <div><input type="text" name="tausch_termin_stunde" placeholder="Stunde / Raum" value="<?= h($_POST['tausch_termin_stunde'] ?? '') ?>"></div>
      </div>
      <label>Tausch mit (Kollege/in, neuer Termin)</label>
      <input type="text" name="tausch_tausch_mit" placeholder="z.B. Weber, am 05.09. 3. Std"
             value="<?= h($_POST['tausch_tausch_mit'] ?? '') ?>">
    </div>

    <!-- Klasse abwesend -->
    <div class="formular-anlass" id="anlass-klasseAbwesend">
      <div class="grid2">
        <div><label>Klasse</label><input type="text" name="klasseAbwesend_klasse" value="<?= h($_POST['klasseAbwesend_klasse'] ?? '') ?>"></div>
        <div><label>Grund</label><input type="text" name="klasseAbwesend_grund" placeholder="z.B. Prüfung, ÜLU" value="<?= h($_POST['klasseAbwesend_grund'] ?? '') ?>"></div>
      </div>
      <div class="grid2">
        <div><label>Beginn</label><input type="date" name="klasseAbwesend_beginn" value="<?= h($_POST['klasseAbwesend_beginn'] ?? '') ?>"></div>
        <div><label>Ende</label><input type="date" name="klasseAbwesend_ende" value="<?= h($_POST['klasseAbwesend_ende'] ?? '') ?>"></div>
      </div>
    </div>

    <br>
    <button class="btn accent" type="submit">Antrag absenden</button>
    <a class="btn secondary" href="index.php">Abbrechen</a>
  </form>
</div>

<script>
function showAnlass(id){
  document.querySelectorAll('.formular-anlass').forEach(f=>f.classList.remove('show'));
  var el = document.getElementById('anlass-'+id);
  if(el) el.classList.add('show');
}
// Bei Neuladen mit Fehlern die zuvor gewählte Sektion wieder zeigen
var pre = document.querySelector('input[name=anlass]:checked');
if(pre) showAnlass(pre.value);
</script>
<?php require __DIR__ . '/_footer.php'; ?>
