<?php
// session-init.php — hardens the session cookie before starting the
// session. Use this INSTEAD of a bare session_start() call.
//
// What this changes vs. PHP's defaults:
//   - httponly: JavaScript can't read the session cookie at all, so
//     an XSS bug elsewhere on the site can't be used to steal a
//     logged-in session by reading document.cookie.
//   - samesite=Lax: the cookie isn't sent on cross-site requests
//     (e.g. a form on another website posting to this site), which
//     blocks a chunk of CSRF attack shapes as a second layer on top
//     of the csrf_token()/csrf_verify() checks already in csrf.php.
//   - secure: only sent over HTTPS — auto-detected below, so this
//     stays harmless on local XAMPP (plain http://localhost) and
//     activates automatically once the site is served over HTTPS.
//
// IMPORTANT: for these settings to actually apply to a visitor's
// session cookie, EVERY page that starts a session needs to call
// this file instead of session_start() directly — mixing the two
// means some requests set the hardened cookie and some don't.

session_set_cookie_params([
    'lifetime' => 0,     // expires when the browser closes
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
    'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
]);

session_start();
