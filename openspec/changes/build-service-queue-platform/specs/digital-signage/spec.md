## Purpose

Lets administrators remotely manage marketing and informational content shown on lobby displays while customers wait. The content plays alongside the live queue.

## ADDED Requirements

### Requirement: Content library
Admins SHALL be able to create and manage content items of these types: text announcement, image slide, video, QR code slide (generated from a URL with a caption), service information slide (generated from the service catalog) and rich-text slide. Each item has a title, a display duration (except video, which plays to its end), and optional start and end dates. Uploaded media MUST be validated for type and size. Accepted types are images (JPG, PNG, WebP) and video (MP4/H.264); limits are configurable per plan.

#### Scenario: Upload an image slide
- **WHEN** an admin uploads a PNG and sets a 12-second duration
- **THEN** the image is stored as a content item available for playlists

#### Scenario: Unsupported file
- **WHEN** an admin uploads a .exe file
- **THEN** the upload is rejected with a validation error

### Requirement: Playlists
Admins SHALL be able to build ordered playlists of content items and assign one or more playlists to displays or to all displays in a location. Playlists loop continuously. Items outside their start/end dates MUST be skipped automatically.

#### Scenario: Expired item skipped
- **WHEN** a playlist contains a "Summer promo" slide whose end date has passed
- **THEN** the display skips it without an admin removing it

### Requirement: Scheduling
Admins SHALL be able to schedule playlists by date range, days of week and time of day, and set a default playlist for times not covered by a schedule. If more than one schedule covers the same time, the most specific one takes precedence.

#### Scenario: Seasonal schedule
- **WHEN** a "Holiday hours" playlist is scheduled for December 20–31 and a default playlist exists
- **THEN** displays show "Holiday hours" in that range and the default playlist otherwise

### Requirement: Remote publishing
Changes to content, playlists or schedules SHALL be pushed to affected displays without anyone touching the TV. Displays MUST pick up changes within 60 seconds. Media MUST be cached locally in the browser so playback continues during short network outages.

#### Scenario: Publish new announcement
- **WHEN** an admin adds an announcement to the playlist of all displays at location X
- **THEN** every display at X begins showing it in rotation within 60 seconds

### Requirement: Queue priority over signage
When a customer is called, the call highlight SHALL take visual priority over signage, and any playing video MUST be muted if a chime is played. Signage resumes after the highlight ends.

#### Scenario: Call during video
- **WHEN** a video with sound is playing and a ticket is called with the chime enabled
- **THEN** the video is muted during the chime and the call highlight overlays the screen

### Requirement: Scrolling ticker
Admins SHALL be able to configure short scrolling ticker messages per location. The messages appear on displays whose layout includes a ticker.

#### Scenario: Ticker message
- **WHEN** an admin sets the ticker text "Ask about our new savings rates"
- **THEN** displays with a ticker scroll that message continuously
