<?php
// csrf.php - shared CSRF protection helpers.
//
// Include this AFTER session_start() and BEFORE handling any POST, on
// every page that has a form or accepts a POST request.
//
// Usage in a form-bearing page:
//   require 'csrf.php';
//   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
//       csrf_verify();   // dies with 403 if the token is missing/wrong
//   }
//   Then inside your <form method="post"> tag, print csrf_field()
//   using a PHP echo, the same way you print other form values.

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $submitted)) {
        http_response_code(403);
        die('Your session expired or this request could not be verified. Please go back and try again.');
    }
}
