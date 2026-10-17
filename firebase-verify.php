<?php
// firebase-verify.php — verifies a Firebase Authentication ID token
// without any external library, same dependency-free approach as
// this project's SMTP mailer used before PHPMailer was added.
//
// A Firebase ID token is a signed JWT. Verifying it "by hand" means
// two things:
//   1. Check its claims (who issued it, who it's for, whether it's
//      expired) match THIS project — otherwise a token meant for some
//      other app could be replayed here.
//   2. Check its cryptographic signature against Google's current
//      public signing keys, so we know Google actually issued it and
//      nothing in it was tampered with after the fact.
//
// This is exactly what the usual firebase/php-jwt library does
// internally — written out directly here since Composer isn't set up
// for this project.
//
// Usage:
//   require 'firebase-config.php';
//   require 'firebase-verify.php';
//   $payload = firebase_verify_id_token($idToken, $error);
//   if ($payload === false) {
//       // $error explains why — log it, don't show raw to the user
//   } else {
//       // $payload['email'], $payload['name'], $payload['sub'] (Google's user id), ...
//   }

function firebase_base64url_decode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

/**
 * Fetches (and locally caches for an hour) Google's current public
 * keys used to sign Firebase ID tokens, keyed by "kid".
 *
 * @return array<string,string>|false map of kid => PEM certificate, or false on failure
 */
function firebase_get_google_public_keys()
{
    $cacheFile = sys_get_temp_dir() . '/firebase_google_certs_cache.json';

    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 3600) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (is_array($cached)) {
            return $cached;
        }
    }

    $url = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';
    $context = stream_context_create(['http' => ['timeout' => 10]]);
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        // Google/network unreachable right now — fall back to a
        // stale cache rather than failing outright, if one exists.
        if (is_file($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                return $cached;
            }
        }
        return false;
    }

    $keys = json_decode($response, true);
    if (!is_array($keys)) {
        return false;
    }

    file_put_contents($cacheFile, $response);
    return $keys;
}

/**
 * Verifies a Firebase Authentication ID token end to end.
 *
 * @param string $idToken
 * @param string|null $error set to a human-readable reason on failure (for logging, not display)
 * @return array|false the decoded token payload on success, false on failure
 */
function firebase_verify_id_token(string $idToken, ?string &$error = null)
{
    $parts = explode('.', $idToken);
    if (count($parts) !== 3) {
        $error = 'Malformed token (wrong number of segments).';
        return false;
    }
    [$headerB64, $payloadB64, $sigB64] = $parts;

    $header  = json_decode(firebase_base64url_decode($headerB64), true);
    $payload = json_decode(firebase_base64url_decode($payloadB64), true);
    $signature = firebase_base64url_decode($sigB64);

    if (!is_array($header) || !is_array($payload)) {
        $error = 'Could not decode token.';
        return false;
    }

    if (($header['alg'] ?? '') !== 'RS256') {
        $error = 'Unexpected signing algorithm: ' . ($header['alg'] ?? '(none)');
        return false;
    }

    $keys = firebase_get_google_public_keys();
    if ($keys === false) {
        $error = "Could not fetch Google's verification keys — check the server's internet connection.";
        return false;
    }

    $kid = $header['kid'] ?? '';
    if (!isset($keys[$kid])) {
        $error = 'Token was signed with a key we don\'t recognize (kid not found).';
        return false;
    }

    $publicKey = openssl_pkey_get_public($keys[$kid]);
    if (!$publicKey) {
        $error = "Could not load Google's public key for verification.";
        return false;
    }

    $signedData = $headerB64 . '.' . $payloadB64;
    $verified = openssl_verify($signedData, $signature, $publicKey, OPENSSL_ALGO_SHA256);

    if ($verified !== 1) {
        $error = 'Token signature is invalid — this token was not genuinely issued by Google for this project.';
        return false;
    }

    $expectedIssuer = 'https://securetoken.google.com/' . FIREBASE_PROJECT_ID;
    $now = time();

    if (($payload['iss'] ?? '') !== $expectedIssuer) {
        $error = 'Token issuer does not match this Firebase project.';
        return false;
    }
    if (($payload['aud'] ?? '') !== FIREBASE_PROJECT_ID) {
        $error = 'Token audience does not match this Firebase project.';
        return false;
    }
    if (!isset($payload['exp']) || $payload['exp'] < $now) {
        $error = 'Token has expired.';
        return false;
    }
    // A little tolerance for clock drift between servers.
    if (!isset($payload['iat']) || $payload['iat'] > $now + 60) {
        $error = 'Token issue time is invalid (iat in the future).';
        return false;
    }
    if (empty($payload['sub'])) {
        $error = 'Token is missing its subject (user id).';
        return false;
    }
    if (empty($payload['email']) || empty($payload['email_verified'])) {
        $error = 'Google account email is missing or not verified.';
        return false;
    }

    return $payload;
}
