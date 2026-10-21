# Mobile PIN login and two-factor authentication

The mobile PIN login (team members only) uses the site's own two-factor authentication: Wordfence Login Security, as the plugin or the module inside Wordfence. There is nothing to set up in TimeGrow.

| The site | What the PIN login does |
|---|---|
| No Wordfence Login Security | Username and PIN |
| Wordfence Login Security, the person has 2FA on | Username and PIN, then the code from their authenticator app (or a recovery code), the same as the normal login |
| Wordfence Login Security requires 2FA for their role and they haven't set it up (grace period over) | Refused until they set it up with the normal login |

Wordfence decides who may use 2FA (its "2FA roles" setting): a team member can only have 2FA on when their role is allowed to.

How it works (`TimeGrowMobile2FA`, `TimeGrowPINAuthenticator`, `TimeGrowMobileLoginView`):

- A right PIN for someone with 2FA does not log in yet. It starts a 5-minute step identified by a random token; the PIN is never put in the page.
- The code is checked by Wordfence (`Controller_TOTP::validate_2fa`): a code works once, recovery codes too.
- Wrong codes count like wrong PINs: 5 lock the PIN for 15 minutes, and starting over does not give more tries. Failures are only cleared when the login is finished.

Before 2.2.0 this module had its own 2FA check, which looked in the wrong place (user meta and the full Wordfence plugin's classes), so PIN logins skipped 2FA, and its code form re-posted the PIN as "verified", which the PIN check refused. Text messages (Twilio) were removed in 2.2.0; their settings and saved credentials are deleted on upgrade.
