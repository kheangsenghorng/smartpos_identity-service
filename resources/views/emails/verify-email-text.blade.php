SmartPOS — Verify Your Email Address

Hello {{ $user->name ?? 'there' }},

Thank you for registering with SmartPOS. Please confirm your email address by opening the following link in your browser:

{{ $verificationUrl }}

Notice: This verification link will expire in {{ $expiresInMinutes }} minutes.

If you did not create an account or request this verification, no further action is required.

© {{ date('Y') }} SmartPOS Ecosystem. All rights reserved.
