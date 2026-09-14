<?php
/**
 * Einmaliges Setup: legt Abteilungen und Demo-Benutzer an.
 * Aufruf: http://localhost/Antragsprogramm/setup.php
 * Kann gefahrlos erneut aufgerufen werden (INSERT IGNORE / vorhandene bleiben).
 */
require_once __DIR__ . '/db.php';

header('Content-Type: text/html; charset=utf-8');
$pdo = db();

// --- Abteilungen ---
$abteilungen = ['Metalltechnik', 'Elektrotechnik', 'Allgemeinbildung'];
$abtIds = [];
foreach ($abteilungen as $name) {
    $stmt = $pdo->prepare('SELECT id FROM abteilung WHERE name = ?');
    $stmt->execute([$name]);
    $id = $stmt->fetchColumn();
    if (!$id) {
        $pdo->prepare('INSERT INTO abteilung (name) VALUES (?)')->execute([$name]);
        $id = $pdo->lastInsertId();
    }
    $abtIds[$name] = $id;
}

// --- Demo-Benutzer ---  (username, passwort, anzeigename, rolle, abteilung)
$users = [
    ['mueller',  'test1234', 'Müller, Anna',      'LK',   'Metalltechnik'],
    ['schmidt',  'test1234', 'Schmidt, Ben',      'LK',   'Elektrotechnik'],
    ['al_meier', 'test1234', 'Meier, Carla (AL)', 'AL',   'Metalltechnik'],
    ['al_kraus', 'test1234', 'Kraus, Dirk (AL)',  'AL',   'Elektrotechnik'],
    ['stsl_wolf','test1234', 'Wolf, Eva (StSL)',  'StSL', null],
    ['sl_becker','test1234', 'Becker, Frank (SL)','SL',   null],
];

$created = [];
foreach ($users as [$username, $pw, $display, $role, $abtName]) {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
    $stmt->execute([$username]);
    if ($stmt->fetchColumn()) {
        $created[] = "$username (existiert bereits)";
        continue;
    }
    $abtId = $abtName ? $abtIds[$abtName] : null;
    $pdo->prepare(
        'INSERT INTO users (username, password_hash, display_name, role, abteilung_id)
         VALUES (?,?,?,?,?)'
    )->execute([$username, password_hash($pw, PASSWORD_DEFAULT), $display, $role, $abtId]);
    $created[] = "$username angelegt";
}
?>
<!DOCTYPE html>
<html lang="de"><head><meta charset="utf-8"><title>Setup</title>
<style>body{font-family:Segoe UI,Arial,sans-serif;max-width:640px;margin:40px auto;color:#243447}
table{border-collapse:collapse;width:100%;margin-top:12px}td,th{border:1px solid #c9d3de;padding:6px 10px;text-align:left}
th{background:#e8f0fa}code{background:#f4f6f9;padding:1px 5px;border-radius:4px}
a.btn{display:inline-block;margin-top:20px;background:#1d4e89;color:#fff;padding:9px 18px;border-radius:5px;text-decoration:none}</style>
</head><body>
<h1>Setup abgeschlossen</h1>
<p><?= implode('<br>', array_map('htmlspecialchars', $created)) ?></p>
<h3>Demo-Zugänge (Passwort jeweils <code>test1234</code>)</h3>
<table>
<tr><th>Benutzer</th><th>Rolle</th><th>Abteilung</th></tr>
<tr><td>mueller</td><td>Lehrkraft</td><td>Metalltechnik</td></tr>
<tr><td>schmidt</td><td>Lehrkraft</td><td>Elektrotechnik</td></tr>
<tr><td>al_meier</td><td>Abteilungsleiter</td><td>Metalltechnik</td></tr>
<tr><td>al_kraus</td><td>Abteilungsleiter</td><td>Elektrotechnik</td></tr>
<tr><td>stsl_wolf</td><td>Stellv. Schulleiter</td><td>–</td></tr>
<tr><td>sl_becker</td><td>Schulleiter</td><td>–</td></tr>
</table>
<a class="btn" href="login.php">Zur Anmeldung</a>
</body></html>
