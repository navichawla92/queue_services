# Lobby TV & kiosk setup

## Recommended hardware

- **Lobby TV:** any TV or monitor with a Chromium-based browser. The simplest reliable setups are an HDMI stick or mini-PC running Chrome/Edge (e.g. Chromebox, Android TV box with Chrome, Windows mini-PC). Smart-TV built-in browsers work but often sleep the screen or block sound.
- **Kiosk:** a 10–13" tablet (Android with Chrome, or iPad with Safari/Guided Access) on a lockable stand, powered permanently.
- Wired Ethernet is preferred; Wi-Fi works. Both screens keep showing their last state during short outages.

## Pairing (both device types)

1. On the device, open `https://<your-domain>/display` (TV) or `https://<your-domain>/kiosk` (tablet).
2. A 6-character code appears (valid 15 minutes).
3. In the admin console go to **Admin → Kiosks & lobby displays**, enter the code, pick the location and a name, and press **Pair device**.
4. Within a few seconds the TV switches to the lobby display and the tablet to the check-in screen.
5. On the TV, tap/click once on **Tap to enable sound** (browsers block audio until one interaction) so the call chime can play.

The device stays paired across browser restarts and power cycles (the credential is kept in the browser). To un-pair, use **Revoke** in the admin console — the screen returns to the pairing code within seconds.

## Display settings (Admin → Kiosks & lobby displays → Settings)

| Setting | Effect |
|---|---|
| Layout | *Queue only* (full screen) or *Split* (queue + digital signage zone) |
| Orientation | Landscape, or portrait (zones stack vertically) |
| Departments shown | Limit the TV to some departments; none selected = all |
| Waiting rows | How many upcoming tickets are listed; the rest show as "+N more waiting" |
| Call highlight | Seconds the full-screen "Now calling" banner stays up; several calls are shown one after another |
| Chime, employee name, average wait, header, ticker | Toggle each element |

Changes reach the TV within seconds (and at most 30 s) — nobody needs to touch it.
Customer names are **off** company-wide by default (ticket numbers only). A company admin can opt in to "First name + last initial" on the same page; full names are never shown.

## Browser kiosk mode & auto-start

**Chrome/Edge on Windows mini-PC (TV):** create a startup shortcut

```
"C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk --noerrdialogs --disable-session-crashed-bubble --autoplay-policy=no-user-gesture-required https://<your-domain>/display
```

`--autoplay-policy=no-user-gesture-required` lets the chime play without the one-time tap. Disable Windows sleep / screen-off in Power settings.

**ChromeOS / Android TV:** use the device's kiosk-app or "single app" mode pointed at `/display`; disable screen timeout.

**Android tablet (kiosk):** Chrome → "Add to Home screen" for `/kiosk`, then enable *Screen pinning* (Settings → Security) or a kiosk-launcher app; set display timeout to *Never* while charging.

**iPad (kiosk):** open `/kiosk` in Safari → Share → *Add to Home Screen*, launch it, then enable *Guided Access* (Settings → Accessibility) and triple-click to lock the app.

The kiosk returns to its welcome screen after 60 s of inactivity and 12 s after showing a ticket, and clears anything a customer typed.

## Legibility check

The lobby display scales text with the screen (`vmin`-based sizes) and was checked with headless Chrome screenshots at 1280×720, 1920×1080, 3840×2160 and 1080×1920 (portrait, split layout). Before go-live, check once on the actual TV from the farthest seat in the lobby.

## Troubleshooting

| Symptom | Fix |
|---|---|
| Pairing code keeps changing | It expires after 15 minutes; enter it sooner |
| "Offline — showing last update" | The TV cannot reach the server; check network. It recovers automatically |
| No chime | Tap "Tap to enable sound", check TV volume, or use the autoplay flag above |
| Screen goes dark | Disable the TV/OS sleep timer; the page requests a screen wake-lock where supported |
| Kiosk shows pairing screen again | It was revoked or its browser data was cleared — pair again |
