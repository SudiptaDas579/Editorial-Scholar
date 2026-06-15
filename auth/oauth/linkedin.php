<?php
// auth/oauth/linkedin.php
// Stub route for "Continue with LinkedIn".
// No real OAuth provider is configured yet — this is a placeholder
// that lets the UI buttons resolve to a real endpoint without 404ing.

require_once __DIR__ . '/../../includes/auth_helpers.php';
require_once __DIR__ . '/../../config/app.php';

// TODO: Implement LinkedIn OAuth 2.0 flow.
// 1. Redirect user to LinkedIn's authorization endpoint with client_id,
//    redirect_uri (this file or a dedicated callback), scope, state.
// 2. Handle the callback: exchange the auth code for an access token,
//    fetch the user's LinkedIn profile/email, then find-or-create a row
//    in `users` and call login_user($user).

flash('info', 'Sign in with LinkedIn is coming soon. Please use your email and password for now.');
redirect(BASE_URL . '/auth/signIn.php');