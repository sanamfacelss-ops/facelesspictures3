# YouTube API Setup Guide - Simple Step-by-Step

## Overview
You need 5 things from Google Cloud Console to enable YouTube uploads:
1. **API Key** - For reading video stats
2. **Client ID** - For OAuth login
3. **Client Secret** - For OAuth login
4. **Refresh Token** - For uploading videos (never expires after setup)
5. **Channel ID** - Your YouTube channel identifier

---

## Part 1: Enable YouTube API

### Step 1: Create/Select Project
1. Go to: https://console.cloud.google.com/
2. Click **"Select a project"** at the top
3. Click **"NEW PROJECT"**
4. Project name: `FacelessPictures` (or any name)
5. Click **"CREATE"**
6. Wait 30 seconds, then select your new project

### Step 2: Enable YouTube Data API
1. Click hamburger menu (☰) → **"APIs & Services"** → **"Library"**
2. Search: `YouTube Data API v3`
3. Click on it
4. Click **"ENABLE"**
5. Wait for it to enable

---

## Part 2: Get API Key

### Step 3: Create API Key
1. Go to: **"APIs & Services"** → **"Credentials"**
2. Click **"+ CREATE CREDENTIALS"**
3. Select **"API key"**
4. Copy the key that appears
5. Click **"RESTRICT KEY"** (recommended)
6. Under "API restrictions":
   - Select **"Restrict key"**
   - Check only: ✅ **YouTube Data API v3**
7. Click **"SAVE"**

**⬇️ COPY THIS TO YOUR ADMIN PANEL:**
- **Field: "YouTube API Key"** or **"YOUTUBE_API_KEY"**
- **Value:** `AIzaSy...` (the key you copied)

---

## Part 3: Get OAuth Credentials (Client ID & Secret)

### Step 4: Configure OAuth Consent Screen
1. Go to: **"APIs & Services"** → **"OAuth consent screen"**
2. Select **"External"** → Click **"CREATE"**
3. Fill in:
   - **App name:** `Faceless Pictures`
   - **User support email:** Your email
   - **Developer contact:** Your email
4. Click **"SAVE AND CONTINUE"**

### Step 5: Add Scopes
1. Click **"ADD OR REMOVE SCOPES"**
2. Filter/search for: `YouTube Data API v3`
3. Check these boxes:
   - ✅ `.../auth/youtube.upload`
   - ✅ `.../auth/youtube.force-ssl`
4. Click **"UPDATE"** → **"SAVE AND CONTINUE"**

### Step 6: Publish App (Important!)
1. Review summary page
2. Click **"BACK TO DASHBOARD"**
3. Click **"PUBLISH APP"** button
4. Confirm **"PUBLISH"**
   
**Why this matters:** Without publishing, your refresh token expires after 7 days!

### Step 7: Create OAuth Client
1. Go to: **"Credentials"** tab
2. Click **"+ CREATE CREDENTIALS"** → **"OAuth client ID"**
3. Application type: **"Web application"**
4. Name: `Faceless Pictures Web Client`
5. **Authorized redirect URIs:** Click **"+ ADD URI"**
   - Add: `https://yourdomain.com/api/auth/google/callback`
   - Add: `http://localhost/api/auth/google/callback` (for testing)
6. Click **"CREATE"**
7. A popup shows **Client ID** and **Client Secret**

**⬇️ COPY THESE TO YOUR ADMIN PANEL:**
- **Field: "YouTube Client ID"** or **"YOUTUBE_CLIENT_ID"**
  - **Value:** `xxxxx.apps.googleusercontent.com`
  
- **Field: "YouTube Client Secret"** or **"YOUTUBE_CLIENT_SECRET"**
  - **Value:** `GOCSPX-xxxxx` (random string)

---

## Part 4: Get Refresh Token (Most Important!)

### Step 8: Generate Authorization URL

Copy this URL and **replace `YOUR_CLIENT_ID`** with the Client ID from Step 7:

```
https://accounts.google.com/o/oauth2/v2/auth?client_id=YOUR_CLIENT_ID&redirect_uri=http://localhost&response_type=code&scope=https://www.googleapis.com/auth/youtube.upload%20https://www.googleapis.com/auth/youtube.force-ssl&access_type=offline&prompt=consent
```

**Example:**
```
https://accounts.google.com/o/oauth2/v2/auth?client_id=123456789.apps.googleusercontent.com&redirect_uri=http://localhost&response_type=code&scope=https://www.googleapis.com/auth/youtube.upload%20https://www.googleapis.com/auth/youtube.force-ssl&access_type=offline&prompt=consent
```

### Step 9: Authorize Your App

1. **Paste the URL** from Step 8 into your browser
2. **Sign in** with your Google account (the one that owns the YouTube channel)
3. You'll see: ⚠️ **"This app isn't verified"**
   - Click **"Advanced"**
   - Click **"Go to Faceless Pictures (unsafe)"** - It's YOUR app, it's safe!
4. Click **"Allow"** (check all permissions)
5. You'll be redirected to: `http://localhost/?code=4/xxxxx-yyyyy-zzzzz`
6. **Copy the code value** after `code=` (everything between `code=` and `&` or end of URL)

**Example URL after redirect:**
```
http://localhost/?code=4/0AcvDMrD8s7example123&scope=https://www.googleapis.com/auth/youtube
```
**Copy this part:** `4/0AcvDMrD8s7example123`

### Step 10: Exchange Code for Refresh Token

**Option A: Use Online Tool (Easiest)**

1. Go to: https://www.oauth.com/playground/
2. **Or** use Google's: https://developers.google.com/oauthplayground/

**Option B: Use Curl Command**

Open terminal and run (replace values):

```bash
curl -X POST https://oauth2.googleapis.com/token \
  -d "code=YOUR_CODE_FROM_STEP_9" \
  -d "client_id=YOUR_CLIENT_ID" \
  -d "client_secret=YOUR_CLIENT_SECRET" \
  -d "redirect_uri=http://localhost" \
  -d "grant_type=authorization_code"
```

**Example:**
```bash
curl -X POST https://oauth2.googleapis.com/token \
  -d "code=4/0AcvDMrD8s7example123" \
  -d "client_id=123456789.apps.googleusercontent.com" \
  -d "client_secret=GOCSPX-abc123def456" \
  -d "redirect_uri=http://localhost" \
  -d "grant_type=authorization_code"
```

**Response will be JSON:**
```json
{
  "access_token": "ya29.xxx...",
  "expires_in": 3599,
  "refresh_token": "1//0gxxx-yyy-zzz",
  "scope": "https://www.googleapis.com/auth/youtube.upload",
  "token_type": "Bearer"
}
```

**⬇️ COPY THIS TO YOUR ADMIN PANEL:**
- **Field: "YouTube Refresh Token"** or **"YOUTUBE_REFRESH_TOKEN"**
- **Value:** `1//0gxxx-yyy-zzz` (the refresh_token from JSON)

**Important:** This token **never expires** (since you published the app in Step 6)!

---

## Part 5: Get Your Channel ID

### Step 11: Find Your YouTube Channel ID

**Method 1: From YouTube Studio**
1. Go to: https://studio.youtube.com/
2. Click your profile picture → **"Settings"**
3. Click **"Channel"** → **"Advanced settings"**
4. Copy **"Channel ID"**: `UCxxxxxxxxxxxxxxxx`

**Method 2: From Your Channel URL**
1. Go to your YouTube channel
2. Look at the URL: `youtube.com/channel/UCxxxxxxxxxxxxxxxx`
3. Copy the part after `/channel/`: `UCxxxxxxxxxxxxxxxx`

**Method 3: Using API (if logged in)**
1. Go to: https://www.googleapis.com/youtube/v3/channels?part=id&mine=true&key=YOUR_API_KEY
2. Replace `YOUR_API_KEY` with your API key from Step 3
3. Add `&access_token=YOUR_ACCESS_TOKEN` (from Step 10 response)

**⬇️ COPY THIS TO YOUR ADMIN PANEL:**
- **Field: "YouTube Channel ID"** or **"YOUTUBE_CHANNEL_ID"**
- **Value:** `UCxxxxxxxxxxxxxxxx`

---

## Part 6: Add All Values to Admin Panel

Go to your Faceless Pictures admin panel → API Keys section:

| Admin Panel Field | Value You Copied | From Step |
|-------------------|------------------|-----------|
| **YouTube API Key** | `AIzaSy...` | Step 3 |
| **YouTube Client ID** | `xxxxx.apps.googleusercontent.com` | Step 7 |
| **YouTube Client Secret** | `GOCSPX-xxxxx` | Step 7 |
| **YouTube Refresh Token** | `1//0gxxx...` | Step 10 |
| **YouTube Channel ID** | `UCxxxxxxxxxxxxxxxx` | Step 11 |

Click **"Save"** or **"Update"**

---

## Part 7: Test It!

In your admin panel:
1. Click **"Test YouTube"** or **"Test API Providers"**
2. You should see: ✅ **YOUTUBE: OK** (green)

If you see errors:
- ❌ **HTTP 401**: Wrong credentials (recheck Client ID, Secret, or Refresh Token)
- ❌ **HTTP 403**: API not enabled or wrong Channel ID
- ❌ **Bad Request**: Refresh token expired (you forgot to publish app in Step 6)

---

## Troubleshooting

### "Refresh token expires after 7 days"
- ❌ You forgot Step 6 (Publish App)
- **Fix:** Go back to Step 6, publish app, then redo Steps 8-10 to get new token

### "Access denied" or "Invalid client"
- ❌ Wrong Client ID or Client Secret
- **Fix:** Double-check values from Step 7

### "Channel not found"
- ❌ Wrong Channel ID or API key doesn't have permission
- **Fix:** Verify Channel ID from Step 11

### "This app isn't verified" warning scary
- ✅ This is NORMAL for unverified apps
- ✅ Just click "Advanced" → "Go to [app name]"
- ✅ It's YOUR app, it's safe to use

---

## Summary Checklist

- [ ] Step 1-2: Created project and enabled YouTube Data API v3
- [ ] Step 3: Created and copied API Key
- [ ] Step 4-5: Configured OAuth consent screen with scopes
- [ ] Step 6: ✅ **PUBLISHED APP** (most important!)
- [ ] Step 7: Created OAuth Client and copied Client ID + Secret
- [ ] Step 8-10: Generated Refresh Token
- [ ] Step 11: Found Channel ID
- [ ] Added all 5 values to admin panel
- [ ] Tested and got ✅ green status

**Done!** Your YouTube integration is now set up and the refresh token will never expire! 🎉
