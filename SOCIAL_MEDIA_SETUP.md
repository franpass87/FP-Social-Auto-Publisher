# Social Media Setup Guide

## Overview

This guide explains how to set up social media connections for the Social Auto Publisher plugin. You need to create applications on each social media platform's developer console and configure OAuth credentials.

## 🚀 Quick Start

1. Navigate to **Social Auto Publisher > Social Connections** in your WordPress admin
2. Configure app credentials for each platform you want to use
3. Click "Connect Account" to link your social media accounts
4. Use the Client Wizard to create clients and assign social media accounts

## 📘 Facebook Setup

### Step 1: Create Facebook App
1. Visit [Facebook Developers](https://developers.facebook.com/apps/)
2. Click "Create App" → "Business" → "Consumer"
3. Enter app name and contact email
4. Add "Facebook Login" product

### Step 2: Configure OAuth Settings
1. Go to Facebook Login → Settings
2. Add redirect URI: `{your-site}/wp-admin/admin-post.php?action=tts_oauth_facebook`
3. Enable "Use Strict Mode for Redirect URIs"

### Step 3: Get Credentials
1. Go to Settings → Basic
2. Copy **App ID** and **App Secret**
3. Enter these in the plugin's Social Connections page

### Required Permissions
- `pages_manage_posts` - Post to Facebook pages
- `pages_read_engagement` - Read page insights
- `pages_show_list` - List user's pages

## 📷 Instagram Setup

### Step 1: Use Facebook App
Instagram uses the same Facebook app with additional products.

### Step 2: Add Instagram Basic Display
1. In your Facebook app, add "Instagram Basic Display" product
2. Configure redirect URI: `{your-site}/wp-admin/admin-post.php?action=tts_oauth_instagram`

### Step 3: Configure Instagram Access
1. Go to Instagram Basic Display → Settings
2. Add your Instagram account as a test user
3. Use the same App ID and App Secret from Facebook

### Required Permissions
- `user_profile` - Access basic profile info
- `user_media` - Access user's media

## 🎥 YouTube Setup

### Step 1: Create Google Project
1. Visit [Google Developers Console](https://console.developers.google.com/)
2. Create a new project or select existing one
3. Enable "YouTube Data API v3"

### Step 2: Create OAuth Credentials
1. Go to Credentials → Create Credentials → OAuth 2.0 Client IDs
2. Choose "Web application"
3. Add redirect URI: `{your-site}/wp-admin/admin-post.php?action=tts_oauth_youtube`

### Step 3: Get Credentials
1. Copy **Client ID** and **Client Secret**
2. Enter these in the plugin's Social Connections page

### Required Scopes
- `https://www.googleapis.com/auth/youtube.upload` - Upload videos

## 🎵 TikTok Setup

### Step 1: Apply for TikTok Developer Account
1. Visit [TikTok Developers](https://developers.tiktok.com/)
2. Apply for developer access (requires approval)
3. Create a new app in the developer portal

### Step 2: Configure App Settings
1. Set app type to "Web"
2. Add redirect URI: `{your-site}/wp-admin/admin-post.php?action=tts_oauth_tiktok`
3. Request necessary permissions

### Step 3: Get Credentials
1. Copy **Client Key** and **Client Secret**
2. Enter these in the plugin's Social Connections page

### Required Permissions
- `user.info.basic` - Access basic user information
- `video.upload` - Upload videos to TikTok

## 🔧 Plugin Configuration

### After Setting Up Apps

1. **Configure App Credentials**
   - Go to Social Auto Publisher → Social Connections
   - Enter the credentials for each platform
   - Save the settings

2. **Connect Your Accounts**
   - Click "Connect Account" for each configured platform
   - Authorize the app when redirected to the social platform
   - You'll be redirected back to WordPress

3. **Create Clients**
   - Use the Client Wizard to create new clients
   - Assign connected social media accounts to clients
   - Configure Trello board mappings

### Verification

✅ **App Configured**: Credentials entered and saved
🟡 **Ready to Connect**: App configured but no accounts connected
🟢 **Connected**: Accounts successfully connected

## 🛠️ Troubleshooting

### Common Issues

**"OAuth verification failed"**
- Check that redirect URIs match exactly
- Ensure app credentials are correct
- Verify app is in development/live mode as needed

**"Failed to obtain access token"**
- Check app secret is correct
- Ensure proper permissions are granted
- Verify the app has the required products enabled

**"App credentials not configured"**
- Enter Client ID/App ID and Client Secret/App Secret
- Save settings before attempting to connect

### Platform-Specific Issues

**Facebook/Instagram**
- App must be approved for production use for public access
- Test with accounts added as app testers during development

**YouTube**
- Ensure YouTube Data API v3 is enabled in Google Console
- Check quota limits haven't been exceeded

**TikTok**
- Developer account approval can take several days
- Some regions may have restrictions

## 📞 Support

If you encounter issues:

1. Check the plugin's Health page for diagnostic information
2. Review WordPress error logs
3. Verify all redirect URIs are correctly configured
4. Ensure apps have proper permissions and are approved where necessary

## 🔄 Token Refresh

Access tokens may expire and need refresh:
- Facebook: Tokens last 60 days
- Instagram: Tokens last 60 days  
- YouTube: Refresh tokens provided for offline access
- TikTok: Tokens last according to platform policy

The plugin includes automatic token refresh functionality where supported by the platform.