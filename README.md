# OpenCaption

OpenCaption is a Persian-first subtitle creation and editing project. The repository currently contains a polished static UI prototype and an early PHP/MySQL application foundation intended to remain compatible with ordinary cPanel shared hosting.

## Current implementation status

### Public UI prototype
- Responsive, RTL, dark charcoal and orange product UI.
- Landing page and interactive visual mockups for dashboard, project creation, editor, templates, fonts, AI settings, authentication, and export.
- Account entry points on the marketing UI now lead to the PHP app.

### PHP application foundation
- PHP 8.1+ with PDO MySQL and server-rendered RTL UI.
- Registration and login using password_hash()/password_verify(), prepared SQL statements, session ID rotation, CSRF tokens, and escaped output.
- Per-user project ownership checks.
- Video uploads with extension and MIME checks, randomized storage names, configured size limit, and a private storage directory.
- Authenticated video streaming with byte-range support for browser seeking.
- Manual subtitle segment creation, editing, and deletion.
- SRT download from saved segments.
- Initial MySQL schema for users, projects, and subtitle segments.

### Not implemented yet
- Automatic speech recognition / AI transcription.
- Background workers, retries, and real processing progress.
- Visual timeline synchronization, drag/drop caption editing, undo/redo, autosave.
- WebM/MP4 rendering or FFmpeg integration.
- TTS, provider API-key management, billing/usage tracking, password reset, email/SMS verification, profile editing, and font/template persistence.
- Full production security review and cross-browser/hosting tests.

The WebM idea (rendering timed captions over a video in a browser) remains a candidate for later evaluation. It is not part of this code yet.

## cPanel setup

1. Upload the repository contents to the intended web directory.
2. Create a MySQL database and database user in cPanel, and grant the user privileges.
3. Import database/schema.sql using phpMyAdmin.
4. Copy config.example.php to config.php; fill in database credentials and verify the upload directory path and limit.
5. Ensure PHP 8.1+, PDO MySQL, Fileinfo, and preferably mbstring are enabled.
6. Ensure storage/uploads is writable by PHP. Keep it non-public; its .htaccess denies direct requests.
7. Set the host's PHP upload limits (upload_max_filesize, post_max_size, and execution/request limits) to values appropriate for your plan.

The public marketing UI remains the default directory index (index.html). Open app.php?page=register to create an account, or click a login/registration CTA on the landing page.

## Configuration and safety

- Never commit config.php, credentials, API keys, or uploaded media.
- Do not enter real API keys until a secure provider-management module exists.
- Shared-hosting limits vary. Large uploads and video transcoding can exceed resource/time limits; actual AI processing and rendering need a compatible worker/service design.
- This foundation is not yet a production-ready release. Configure and test it on a staging host before exposing real user data.
