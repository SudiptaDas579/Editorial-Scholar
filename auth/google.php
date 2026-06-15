<?php
// auth/oauth/google.php
// Stub route for "Continue with Google".
// No real OAuth provider is configured yet — this is a placeholder
// that lets the UI buttons resolve to a real endpoint without 404ing.

require_once __DIR__ . '/../../includes/auth_helpers.php';

// TODO: Implement Google OAuth flow.
// 1. Redirect user to Google's OAuth consent screen with client_id,
//    redirect_uri (this file or a dedicated callback), scope, state.
// 2. Handle the callback: exchange the auth code for tokens,
//    fetch the user's Google profile, then find-or-create a row
//    in `users` and call login_user($user).

flash('info', 'Sign in with Google is coming soon. Please use your email and password for now.');
redirect('/auth/signIn.php');
