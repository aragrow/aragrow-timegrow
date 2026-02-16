# Flush Rewrite Rules

The `/mobile-login` URL needs WordPress rewrite rules to be flushed.

## Quick Fix

Visit this URL in your browser (while logged in as admin):

```
http://localhost:10003/wp-admin/options-permalink.php
```

Then click the **"Save Changes"** button at the bottom (you don't need to change anything).

This will flush the rewrite rules and register the `/mobile-login` endpoint.

## Verify It Works

After flushing, visit:

```
http://localhost:10003/mobile-login
```

You should see the mobile login page.

---

**Alternative:** Deactivate and reactivate the TimeGrow plugin via Plugins menu.
