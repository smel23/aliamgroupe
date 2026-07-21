<?php
/* ============================================================
   AliamGroup — Passerelle d'envoi Brevo (côté serveur)
   ------------------------------------------------------------
   Le navigateur n'appelle plus Brevo directement : il poste ici.
   Avantages :
     • La clé API n'apparaît plus dans le code source public.
     • Brevo voit toujours la MÊME IP (celle de ton hébergeur),
       donc une seule IP à autoriser au lieu de celle de chaque
       visiteur.
     • Le destinataire est forcé ici : personne ne peut détourner
       le formulaire pour envoyer des mails à des tiers.
   ============================================================ */

// ⚠️ Mets ta clé ici. Ce fichier n'est jamais envoyé au navigateur.
const BREVO_API_KEY = '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';

const SENDER_NAME   = 'Spark — AliamGroup';
const SENDER_EMAIL  = 'no-reply@aliamflow.com';  // doit être un expéditeur validé dans Brevo
const RH_EMAIL      = 'holding.rhflow@yahoo.com';
const MAX_ATTACH_MO = 5;

header('Content-Type: application/json; charset=utf-8');

function fail($msg, $code = 400) {
    http_response_code($code);
    echo json_encode(['ok' => false, 'message' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Méthode non autorisée.', 405);

$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) fail('Requête invalide.');

$type = $data['type'] ?? '';
$esc  = fn($v) => htmlspecialchars(trim((string)($v ?? '')), ENT_QUOTES, 'UTF-8');

$email = trim((string)($data['email'] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Adresse email invalide.');

$nom = $esc($data['nom'] ?? '');
if ($nom === '') fail('Le nom est obligatoire.');

/* ---------- Construction du mail selon le parcours ---------- */
$attachment = null;

if ($type === 'candidature') {
    $filiale = $esc($data['filiale']);
    $dept    = $esc($data['dept']);
    $tel     = $esc($data['tel']);
    if ($filiale === '' || $dept === '' || $tel === '') fail('Champs manquants.');

    $subject = "Candidature — $filiale · $dept — $nom";
    $accent  = '#C8FF47';
    $entete  = 'Nouvelle candidature via Spark';
    $rows = [
        'Filiale visée' => "<b>$filiale</b>",
        'Département'   => "<b>$dept</b>",
        'Candidat'      => $nom,
        'Téléphone'     => $tel,
        'Email'         => '<a href="mailto:' . $esc($email) . '">' . $esc($email) . '</a>',
    ];
    $corps = '';

} elseif ($type === 'business') {
    $profil  = $esc($data['profil']);
    $focus   = $esc($data['focus']);
    $societe = $esc($data['societe']);
    $contact = $esc($data['contact']);
    if ($societe === '' || $contact === '') fail('Champs manquants.');

    $subject = "[PRIORITAIRE] Partenariat — $profil · $focus — $societe";
    $accent  = '#FF7A00';
    $entete  = '⚑ PRIORITAIRE · Partenariat via Spark';
    $rows = [
        'Profil'          => "<b>$profil</b>",
        'Focus'           => "<b>$focus</b>",
        'Entité'          => $societe,
        'Contact & Poste' => $contact,
        'Email pro'       => '<a href="mailto:' . $esc($email) . '">' . $esc($email) . '</a>',
    ];
    $msg   = $esc($data['message'] ?? '');
    $corps = $msg !== '' ? '<div style="margin-top:16px;padding:16px;background:#f7f7f7;border-radius:8px;font-size:14px;color:#333;white-space:pre-wrap;">' . $msg . '</div>' : '';

} elseif ($type === 'contact') {
    $sujet = $esc($data['sujet'] ?? 'Message');
    $msg   = $esc($data['message'] ?? '');
    if ($msg === '') fail('Le message est vide.');

    $subject = "[Contact] $sujet — $nom";
    $accent  = '#C8FF47';
    $entete  = 'Formulaire de contact';
    $rows = [
        'Nom'   => "<b>$nom</b>",
        'Email' => '<a href="mailto:' . $esc($email) . '">' . $esc($email) . '</a>',
        'Sujet' => "<b>$sujet</b>",
    ];
    $corps = '<div style="margin-top:16px;padding:16px;background:#f7f7f7;border-radius:8px;font-size:14px;color:#333;white-space:pre-wrap;">' . $msg . '</div>';

} else {
    fail('Type de demande inconnu.');
}

/* ---------- Pièce jointe (CV / LOI / NDA) ---------- */
if (!empty($data['attachment']['content']) && !empty($data['attachment']['name'])) {
    $b64 = $data['attachment']['content'];
    if (strlen($b64) * 3 / 4 > MAX_ATTACH_MO * 1024 * 1024) fail('Pièce jointe trop lourde.');
    $name = preg_replace('/[^A-Za-z0-9._-]/', '_', basename($data['attachment']['name']));
    if (!preg_match('/\.pdf$/i', $name)) fail('Seuls les PDF sont acceptés.');
    $attachment = [['content' => $b64, 'name' => $name]];
    $rows['Pièce jointe'] = '📎 ' . $esc($name);
}

/* ---------- Rendu HTML ---------- */
$lignes = '';
foreach ($rows as $label => $val) {
    $lignes .= '<tr><td style="padding:8px 0;color:#888;width:150px;">' . $label . '</td>'
             . '<td style="padding:8px 0;">' . $val . '</td></tr>';
}
$htmlContent = '
<div style="font-family:Arial,sans-serif;max-width:560px;margin:auto;border:1px solid #e5e5e5;border-radius:10px;overflow:hidden;">
  <div style="background:#080C08;padding:20px 26px;">
    <span style="color:' . $accent . ';font-size:16px;font-weight:bold;">AliamGroup</span>
    <span style="color:#888;font-size:12px;"> · ' . $entete . '</span>
  </div>
  <div style="padding:24px 26px;">
    <table style="width:100%;border-collapse:collapse;font-size:14px;color:#222;">' . $lignes . '</table>
    ' . $corps . '
    <p style="font-size:12px;color:#999;margin-top:20px;">Email généré automatiquement par Spark depuis le site AliamGroup.</p>
  </div>
</div>';

/* ---------- Appel Brevo ---------- */
$payload = [
    'sender'      => ['name' => SENDER_NAME, 'email' => SENDER_EMAIL],
    'to'          => [['email' => RH_EMAIL, 'name' => 'AliamGroup']],
    'replyTo'     => ['email' => $email, 'name' => strip_tags($nom)],
    'subject'     => html_entity_decode($subject, ENT_QUOTES, 'UTF-8'),
    'htmlContent' => $htmlContent,
];
if ($attachment) $payload['attachment'] = $attachment;

$ch = curl_init('https://api.brevo.com/v3/smtp/email');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_HTTPHEADER     => [
        'accept: application/json',
        'content-type: application/json',
        'api-key: ' . BREVO_API_KEY,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$res  = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$cerr = curl_error($ch);
curl_close($ch);

if ($res === false) fail('Connexion au service mail impossible : ' . $cerr, 502);

if ($code >= 200 && $code < 300) {
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
} else {
    $body = json_decode($res, true);
    fail($body['message'] ?? ('Erreur Brevo ' . $code), 502);
}
