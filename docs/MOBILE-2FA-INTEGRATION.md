# Mobile 2FA Integration with Wordfence

## Overview

TimeGrow mobile PIN login now automatically integrates with Wordfence 2FA. If a user has Wordfence 2FA enabled on their account, they will be required to enter their authenticator code after entering their PIN when logging in via mobile.

## How It Works

### 1. **Automatic Detection**
- When a user logs in with their PIN, the system checks if they have Wordfence 2FA enabled
- If Wordfence 2FA is active, a second step is automatically added to the login flow
- No configuration needed - it just works!

### 2. **Two-Step Login Process**

**Step 1: PIN Entry**
- User enters their username and 6-character PIN
- System verifies the PIN

**Step 2: 2FA Verification** (only if Wordfence 2FA is enabled)
- User is prompted to enter their 6-digit authenticator code
- Accepts codes from Google Authenticator, Authy, Microsoft Authenticator, etc.
- Also accepts Wordfence recovery codes

### 3. **Setup Process**

#### For Administrators:
1. User must first set up Wordfence 2FA via desktop WordPress admin
2. Go to **Wordfence → Login Security → Two-Factor Authentication**
3. Scan QR code with authenticator app
4. Save recovery codes
5. Once enabled, 2FA will automatically be required for mobile PIN logins

#### For Users:
1. Set up Wordfence 2FA in desktop WordPress (one time)
2. When logging in via mobile:
   - Enter username and PIN
   - If 2FA is enabled, enter authenticator code
   - Access granted!

## User Experience

### Login Without 2FA:
```
1. Enter username → 2. Enter PIN → 3. Logged in ✓
```

### Login With 2FA:
```
1. Enter username → 2. Enter PIN → 3. Enter 2FA code → 4. Logged in ✓
```

## Security Features

- **Leverages Existing Wordfence Security**: No separate 2FA system to manage
- **Recovery Codes**: Wordfence recovery codes work for mobile login too
- **Time-Based Codes**: Uses TOTP (Time-based One-Time Password) standard
- **30-Second Window**: Accepts codes from 30 seconds before/after for clock drift
- **Auto-Verify**: Automatically submits when 6 digits are entered

## Technical Details

### Files Modified:
- `TimeGrowMobile2FA.php` - Wordfence integration class
- `TimeGrowMobileLoginView.php` - Added 2FA step to login flow
- `aragrow-timegrow-mobile.php` - Loads 2FA module

### Wordfence Integration:
- Checks user meta: `wf_2fa_totp` for TOTP secret
- Checks user meta: `wf_2fa_recovery` for backup codes
- Uses Wordfence's `wfTOTP` class if available
- Falls back to manual TOTP verification

### Session Management:
- Uses PHP sessions to track 2FA state during login
- Session cleared after successful verification
- "Back to PIN login" link clears session and restarts

## Testing Checklist

- [ ] User without Wordfence 2FA can login with just PIN
- [ ] User with Wordfence 2FA is prompted for code after PIN
- [ ] Valid 2FA code grants access
- [ ] Invalid 2FA code shows error
- [ ] Wordfence recovery codes work
- [ ] Auto-submit works after 6 digits entered
- [ ] "Back to PIN login" link returns to PIN screen
- [ ] Session expires if user closes browser

## Troubleshooting

### Issue: 2FA not being requested
- **Check**: User has Wordfence 2FA enabled in desktop admin
- **Verify**: Wordfence plugin is active
- **Test**: Try logging into desktop WordPress - should prompt for 2FA

### Issue: "Invalid 2FA code" error
- **Check**: Time is synchronized on phone (TOTP relies on accurate time)
- **Try**: Use a recovery code instead
- **Verify**: Code hasn't been used already (each code works once per 30-second window)

### Issue: Wordfence not detected
- **Check**: Wordfence plugin is installed and activated
- **Verify**: Wordfence version is up to date

## Benefits

✅ **No Duplicate Setup**: Users only configure 2FA once in Wordfence
✅ **Consistent Security**: Same 2FA system for desktop and mobile
✅ **Recovery Options**: Wordfence recovery codes work everywhere
✅ **Easy Management**: Admins manage 2FA in one place (Wordfence settings)
✅ **Industry Standard**: Uses TOTP protocol (RFC 6238)

## Future Enhancements

- Support for other 2FA plugins (Duo, WP 2FA, etc.)
- Biometric authentication (fingerprint, Face ID)
- SMS backup codes
- Remember device for 30 days option
