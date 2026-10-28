# OpenCaption — UI Prototype

A responsive, Persian-first UI prototype for OpenCaption, an open-source subtitle creation and editing studio.

## Current scope

This is **UI only**. It has no backend, database, authentication, file upload processing, AI calls, persistence, or real export pipeline. Project cards, counters, progress indicators, provider states, and media previews are illustrative sample data.

### Included screens

- Product landing page
- Overview dashboard and recent projects
- Project library with search and status filters
- New-project flow, file picker UI, language selection, and transcription options
- Subtitle editor UI with preview, player controls, timeline tracks, segment list, and style properties
- Subtitle templates
- Font library and font upload UI
- AI provider settings for STT and TTS
- Account, preferences, security, and privacy settings
- Export UI for SRT, WebVTT, and WebM
- Login and registration screens
- Responsive sidebar, modal dialogs, dropdowns, and basic UI navigation

## Run locally

No build tools or package installation are required.

1. Download or clone this repository.
2. Open `index.html` in a modern browser.

The prototype uses Google Fonts for Vazirmatn and Estedad when an internet connection is available. System fallbacks are defined.

## cPanel shared hosting

Upload `index.html` to the desired directory in cPanel File Manager (for example, `public_html`) to preview the UI. This static prototype does not require PHP or MySQL. Backend integration is a later phase.

## Notes

- Buttons and navigation are wired only where needed to preview screens, menus, dialogs, and UI states. Actions that imply real work show a demo notice and do not process data.
- Do not enter real API keys or sensitive information.
- File pickers are visual demonstrations; selected files are not uploaded or processed.
- WebM/MP4 rendering, STT/TTS, authentication, database persistence, and secure API-key storage are not implemented.
- The design uses a dark charcoal and orange palette, dimensional controls, restrained hover states, and responsive layouts.
