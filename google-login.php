<?php
require 'session-init.php';
require 'db.php';
require 'csrf.php';
require 'firebase-config.php';
require 'firebase-verify.php';

// ============================================================
// GOOGLE SIGN-IN (via Firebase Authentication)
// ============================================================
// The browser side (login.php) opens a Google popup through the
// Firebase JS SDK, gets back a signed ID token, and POSTs it here.
// This endpoint is where all the actual trust decisions happen:
//   1. Verify the token is genuine and current (firebase-verify.php).
//   2. Find or create the matching local `users` row.
//   3. Log the browser in with a normal PHP session, same as
//      login.php's password path (session_regenerate_id included).
//
// Every failure path below redirects back to login.php with a short,
// generic error message — the real reason goes to error_log() for
// debugging, never to the visitor. This matters here even more than
// on the password login: a verbose error could leak which stage of
// the Google/Firebase verification failed, which is more useful to
// an attacker probing the integration than to a real user.
// ============================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit;
}

csrf_verify();

$idToken = $_POST['id_token'] ?? '';

if ($idToken === '') {
    header('Location: login.php?google_error=1');
    exit;
}

$verifyError = null;
$payload = firebase_verify_id_token($idToken, $verifyError);

if ($payload === false) {
    error_log('[google-login] token verification failed: ' . $verifyError);
    header('Location: login.php?google_error=1');
    exit;
}

$googleId = $payload['sub'];
$email    = $payload['email'];
$name     = $payload['name'] ?? explode('@', $email)[0];

try {
    // 1. Already linked to this Google account?
    $stmt = $pdo->prepare('SELECT * FROM users WHERE google_id = ? LIMIT 1');
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();

    if (!$user) {
        // 2. An existing password-based account with this email,
        //    not yet linked to Google? Link it — Google has already
        //    confirmed this person owns the email address.
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $pdo->prepare('UPDATE users SET google_id = ? WHERE id = ?')->execute([$googleId, $user['id']]);
        } else {
            // 3. Brand new customer — create the account. The password
            //    is a long random string nobody will ever know (this
            //    account only ever signs in via Google); it still
            //    satisfies the NOT NULL password_hash column without
            //    adding a special case to login.php's password path.
            $unusablePassword = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
            $pdo->prepare(
                'INSERT INTO users (full_name, email, password_hash, google_id) VALUES (?, ?, ?, ?)'
            )->execute([$name, $email, $unusablePassword, $googleId]);

            $userId = $pdo->lastInsertId();
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
        }
    }
} catch (PDOException $e) {
    error_log('[google-login] database error: ' . $e->getMessage());
    header('Location: login.php?google_error=1');
    exit;
}

// Same session-fixation protection as the password login path.
session_regenerate_id(true);

$_SESSION['is_user']   = true;
$_SESSION['user_id']   = $user['id'];
$_SESSION['user_name'] = $user['full_name'];

header('Location: index.php');
exit;
