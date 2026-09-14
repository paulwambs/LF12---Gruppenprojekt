<?php
require_once __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Liefert eine (einmalig aufgebaute) PDO-Verbindung.
 */
function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}

/* ------------------------------------------------------------------ */
/*  Authentifizierung / Session                                        */
/* ------------------------------------------------------------------ */

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        header('Location: login.php');
        exit;
    }
    return $u;
}

/** Erzwingt eine bestimmte Rolle, sonst Abbruch. */
function require_role(string ...$roles): array
{
    $u = require_login();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        echo 'Kein Zugriff für diese Rolle.';
        exit;
    }
    return $u;
}

/* ------------------------------------------------------------------ */
/*  CSRF-Schutz                                                        */
/* ------------------------------------------------------------------ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_check(): void
{
    $sent = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $sent)) {
        http_response_code(419);
        exit('Ungültiges oder abgelaufenes Formular (CSRF).');
    }
}

/* ------------------------------------------------------------------ */
/*  Fachliche Hilfsfunktionen / Beschriftungen                         */
/* ------------------------------------------------------------------ */

const ROLE_LABELS = [
    'LK'   => 'Lehrkraft / Antragsteller',
    'AL'   => 'Abteilungsleiter',
    'StSL' => 'Stellv. Schulleiter',
    'SL'   => 'Schulleiter',
];

const ANLASS_LABELS = [
    'vertretung'     => 'Unterrichtsvertretung aus dienstlichen Gründen',
    'tausch'         => 'Unterrichtstausch',
    'klasseAbwesend' => 'Klasse abwesend ohne Lehrkraft',
];

const STATUS_LABELS = [
    'gestellt'         => 'Eingereicht – wartet auf AL',
    'al_abgezeichnet'  => 'Von AL abgezeichnet – wartet auf StSL',
    'stsl_abgezeichnet'=> 'Von StSL abgezeichnet – wartet auf SL',
    'genehmigt'        => 'Genehmigt',
    'abgelehnt'        => 'Abgelehnt',
];

// Welche Rolle bearbeitet welchen Status, und welchen Status setzt das Abzeichnen?
const WORKFLOW = [
    'AL'   => ['from' => 'gestellt',          'to' => 'al_abgezeichnet',   'log' => 'al_abgezeichnet',   'verb' => 'abzeichnen'],
    'StSL' => ['from' => 'al_abgezeichnet',   'to' => 'stsl_abgezeichnet', 'log' => 'stsl_abgezeichnet', 'verb' => 'abzeichnen'],
    'SL'   => ['from' => 'stsl_abgezeichnet', 'to' => 'genehmigt',         'log' => 'genehmigt',         'verb' => 'genehmigen'],
];

// Anlass-spezifische Formularfelder (Key => Beschriftung), zentral wiederverwendbar
const ANLASS_FELDER = [
    'vertretung' => [
        'grund'    => 'Grund',
        'beginn'   => 'Beginn',
        'ende'     => 'Ende',
        'material' => 'Vertretungsmaterial',
    ],
    'tausch' => [
        'termin_datum'  => 'Ursprünglicher Termin (Datum)',
        'termin_stunde' => 'Stunde / Raum',
        'tausch_mit'    => 'Tausch mit (Kollege/in, neuer Termin)',
    ],
    'klasseAbwesend' => [
        'klasse' => 'Klasse',
        'grund'  => 'Grund',
        'beginn' => 'Beginn',
        'ende'   => 'Ende',
    ],
];

const LOG_LABELS = [
    'erstellt'          => 'Antrag gestellt',
    'al_abgezeichnet'   => 'Vom Abteilungsleiter abgezeichnet',
    'stsl_abgezeichnet' => 'Vom stellv. Schulleiter abgezeichnet',
    'genehmigt'         => 'Vom Schulleiter genehmigt',
    'abgelehnt'         => 'Abgelehnt',
];

function h(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Merkt eine einmalige Meldung für die nächste Seite vor. */
function flash(string $type, string $text): void
{
    $_SESSION['flash'][] = ['type' => $type, 'text' => $text];
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

/** Fortschrittsschritte für die Statusanzeige. */
function progress_steps(string $status): array
{
    $order = ['gestellt', 'al_abgezeichnet', 'stsl_abgezeichnet', 'genehmigt'];
    $labels = ['Gestellt', 'AL', 'StSL', 'SL'];
    $current = array_search($status, $order, true);
    if ($status === 'abgelehnt') {
        $current = -1;
    }
    $steps = [];
    foreach ($labels as $i => $label) {
        if ($status === 'genehmigt') {
            $cls = 'done';
        } elseif ($current === false) {
            $cls = '';
        } elseif ($i < $current) {
            $cls = 'done';
        } elseif ($i === $current) {
            $cls = 'done'; // erreichter Stand ist abgeschlossen
        } else {
            $cls = '';
        }
        // Der jeweils nächste offene Schritt = aktuell
        $steps[] = ['label' => $label, 'cls' => $cls];
    }
    // "current" markieren: der erste noch nicht erledigte Schritt
    if ($status !== 'genehmigt' && $status !== 'abgelehnt') {
        $next = ($current === false) ? 0 : $current + 1;
        if (isset($steps[$next])) {
            $steps[$next]['cls'] = 'current';
        }
    }
    return $steps;
}
