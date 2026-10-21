# Mobile PIN login and two-factor authentication

As of 2.2.0 the mobile PIN login has no two-factor step, and administrators can't use it.

The earlier Wordfence 2FA step never worked: it looked for 2FA in user meta (`wf_2fa_totp`) and in classes of the full Wordfence plugin, while Wordfence Login Security keeps 2FA in its own table (`wfls_2fa_secrets`), so a PIN login skipped 2FA; and its second form re-posted the PIN as "verified", which the PIN check refused.

What protects the PIN login now (`TimeGrowPINAuthenticator`):

- Team members only (`TimeGrowMobileAccess::pin_allowed()`). Administrators log in with their password, with Wordfence 2FA, on a phone too.
- PINs are made on the server and stored with WordPress's password hash.
- 5 wrong PINs lock the PIN for 15 minutes; 20 failed logins from one address in 15 minutes pause logins from it.
- The same message for every failure.
- The login lasts 8 hours, is marked as mobile in its WordPress session on the server, and only opens Clock In / Out, Manual Time Entry and Record Expenses (`TimeGrowMobileAccessControl`).
