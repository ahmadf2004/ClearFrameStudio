<?php
// ------------------------------------------------------------------
// Email sending configuration (Gmail SMTP).
//
// ONE-TIME SETUP:
//   1. Turn on 2-Step Verification on the Gmail account that will
//      send these emails:
//      https://myaccount.google.com/security
//   2. Create an App Password (this only appears once you have
//      2-Step Verification turned on):
//      https://myaccount.google.com/apppasswords
//      Choose "Mail" as the app, name it something like
//      "Clear Frame Studio", and Google will show you a 16-character
//      password. Copy it.
//   3. Fill in the two constants below:
//        - SMTP_USERNAME     = that Gmail address
//        - SMTP_APP_PASSWORD = the 16-character App Password
//      This is NOT your normal Gmail login password — your normal
//      password will be rejected by Google here.
//
// SECURITY:
//   - This file holds a real credential. Don't push it to a public
//     GitHub repo, don't zip it into something you hand out publicly,
//     and don't rename it to .txt (PHP files aren't served as source
//     by Apache, but a .txt version would be downloadable as plain
//     text by anyone who guesses the URL).
//   - If you ever suspect this password leaked, revoke it instantly
//     at https://myaccount.google.com/apppasswords and generate a
//     new one — App Passwords can be revoked individually without
//     touching your main Google password.
// ------------------------------------------------------------------

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 465); // SSL. (Gmail's other option is 587 + STARTTLS, not used here.)

define('SMTP_USERNAME', 'youraddress@gmail.com');    // <-- the Gmail address that sends these emails
define('SMTP_APP_PASSWORD', 'xxxx xxxx xxxx xxxx');   // <-- the 16-character App Password from Google (spaces are fine, they're stripped automatically)

define('SMTP_FROM_NAME', 'Clear Frame Studio');
