Hello {{ $user->name ?? 'there' }},

You recently requested to change the primary email address for your SmartPOS account to {{ $pendingEmail }}.

Please confirm this update by visiting the following link:
{{ $verificationUrl }}

Note: This confirmation link will expire in {{ $expiresInMinutes }} minutes.

If you did not initiate this email change request, please sign in to your SmartPOS account immediately and change your password.

---
SmartPOS Ecosystem
