<?php
// firebase-config.php — your Firebase project's identifiers.
//
// ONE-TIME SETUP:
//   1. Go to https://console.firebase.google.com and create a project
//      (the free "Spark" plan covers this completely).
//   2. In the left sidebar: Build > Authentication > Get started >
//      Sign-in method tab > add "Google" as a provider > Enable it >
//      Save.
//   3. Go to Project settings (the gear icon, top left) > General,
//      scroll down to "Your apps", and click the </> (Web) icon to
//      register a web app if you haven't already (any nickname is
//      fine, you don't need Firebase Hosting).
//   4. Firebase will show you a firebaseConfig object — copy the
//      three values below from it.
//
// These three values are NOT secret. They're meant to be embedded in
// frontend JavaScript (anyone can see them by viewing your page
// source) — Firebase's actual security comes from the sign-in rules
// you set in the console, not from hiding these.

define('FIREBASE_API_KEY', 'AIzaSyCvcTl4eB7eh0HCVJfEdCRv_eePyVR0jjQ');
define('FIREBASE_AUTH_DOMAIN', 'clear-frame-studio.firebaseapp.com');
define('FIREBASE_PROJECT_ID', 'clear-frame-studio');
