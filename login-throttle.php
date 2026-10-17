<?php
// login-throttle.php — simple brute-force protection for login forms,
// backed by a login_attempts table. Include this alongside db.php.
//
// Requires this table:
//
//   CREATE TABLE login_attempts (
//       id INT AUTO_INCREMENT PRIMARY KEY,
//       scope VARCHAR(20) NOT NULL,        -- 'customer' or 'admin'
//       identifier VARCHAR(255) NOT NULL,  -- the email/username typed in
//       ip_address VARCHAR(45) NOT NULL,
//       attempted_at DATETIME NOT NULL,
//       INDEX (scope, identifier, attempted_at)
//   );
//
// Usage in a login page, right after csrf_verify() and before
// checking the password:
//
//   $lock = login_throttle_check($pdo, 'customer', $values['identifier']);
//   if ($lock) {
//       $errors[] = "Too many failed attempts. Try again in {$lock} minute(s).";
//   } else {
//       // ... look up the user and password_verify() as normal ...
//       if ($ok) {
//           login_throttle_clear($pdo, 'customer', $values['identifier']);
//       } else {
//           login_throttle_record_failure($pdo, 'customer', $values['identifier']);
//       }
//   }

const LOGIN_THROTTLE_MAX_ATTEMPTS   = 5;  // failed attempts allowed...
const LOGIN_THROTTLE_WINDOW_MINUTES = 15; // ...within this many minutes

/**
 * Returns null if the identifier is NOT currently locked out, or an
 * integer (minutes remaining, at least 1) if it IS locked out.
 */
function login_throttle_check(PDO $pdo, string $scope, string $identifier): ?int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS attempts, MIN(attempted_at) AS first_attempt
         FROM login_attempts
         WHERE scope = ? AND identifier = ?
           AND attempted_at > (NOW() - INTERVAL ? MINUTE)'
    );
    $stmt->execute([$scope, $identifier, LOGIN_THROTTLE_WINDOW_MINUTES]);
    $row = $stmt->fetch();

    if ((int) $row['attempts'] < LOGIN_THROTTLE_MAX_ATTEMPTS) {
        return null;
    }

    $unlocksAt = strtotime($row['first_attempt']) + LOGIN_THROTTLE_WINDOW_MINUTES * 60;
    $minutesLeft = (int) ceil(($unlocksAt - time()) / 60);
    return max(1, $minutesLeft);
}

function login_throttle_record_failure(PDO $pdo, string $scope, string $identifier): void
{
    $pdo->prepare(
        'INSERT INTO login_attempts (scope, identifier, ip_address, attempted_at) VALUES (?, ?, ?, NOW())'
    )->execute([$scope, $identifier, $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
}

function login_throttle_clear(PDO $pdo, string $scope, string $identifier): void
{
    $pdo->prepare('DELETE FROM login_attempts WHERE scope = ? AND identifier = ?')
        ->execute([$scope, $identifier]);
}
