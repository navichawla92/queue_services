## Purpose

Turns any TV or monitor with a web browser into a live lobby screen. It shows who is being served and where, who is waiting, and it clearly calls customers forward. It never needs a manual refresh.

## ADDED Requirements

### Requirement: Display pairing
Admins SHALL be able to register a lobby display for a location. They choose which departments it covers and a layout. The TV browser opens a display URL, which shows a short pairing code. An admin enters that code in the admin console to bind the display. After pairing, the display MUST keep its credential across browser restarts and power cycles without anyone signing in again.

#### Scenario: Pair a new TV
- **WHEN** an admin enters the 6-character code shown on a new TV's display page
- **THEN** the TV switches to the configured lobby layout for that location within 5 seconds

#### Scenario: TV power cycle
- **WHEN** a paired TV is switched off and on again and the browser reopens the display URL
- **THEN** the lobby layout resumes without re-pairing

### Requirement: Now serving panel
The display SHALL show tickets currently Called or In Service, each with its desk/room label and, if the tenant enables it, the employee's display name. By default it SHALL show the customer's ticket number. Showing the customer's first name and last initial is an opt-in tenant setting; full names MUST never be shown.

#### Scenario: Privacy default
- **WHEN** a tenant has not enabled name display
- **THEN** the TV shows "A-012 → Desk 3" without any customer name

#### Scenario: Names enabled
- **WHEN** a tenant has enabled name display
- **THEN** the TV shows "A-012 · John D. → Desk 3"

### Requirement: Waiting list panel
The display SHALL show the upcoming waiting tickets for its departments in queue order, and MAY show an average wait per department. The number of rows shown is configurable. Waiting tickets MUST be identified the same way as on the now-serving panel (number, or number plus short name).

#### Scenario: Long queue truncated
- **WHEN** 40 tickets are waiting and the layout shows 10 rows
- **THEN** the next 10 are listed with a "+30 more waiting" indicator

### Requirement: Call highlighting
When a ticket is called or recalled, the display SHALL prominently highlight it for a configurable duration (default 10 seconds): a large banner or animation showing the ticket number and desk/room, plus an optional chime. If several calls happen close together, each MUST be shown in turn and none may be skipped.

#### Scenario: Customer called
- **WHEN** an employee at Desk 3 calls ticket A-012
- **THEN** within 2 seconds the TV shows a full-width highlight "A-012 — please go to Desk 3" and plays the chime if enabled

#### Scenario: Burst of calls
- **WHEN** three tickets are called within 5 seconds
- **THEN** the display shows each highlight in turn, and all three then appear in the now-serving panel

### Requirement: Live updates without refresh
The display SHALL receive updates in real time. If the real-time connection drops, it MUST reconnect automatically, resync its full state, and fall back to periodic polling while disconnected. The display MUST keep showing its last known state rather than going blank during outages.

#### Scenario: Network blip
- **WHEN** the TV loses network access for 2 minutes
- **THEN** it keeps showing the last known queue with a subtle offline indicator, and on reconnection shows the current state within 10 seconds

### Requirement: Screen layouts
The display SHALL support configurable layouts: queue-only full screen, and split screen with the queue in one zone and digital signage in another (e.g. queue on the left third, signage on the right). An optional header and ticker show the tenant's branding, the clock and scrolling announcements. Layout changes made by an admin MUST be applied to paired displays without anyone touching the TV.

#### Scenario: Remote layout change
- **WHEN** an admin changes a display from queue-only to split screen
- **THEN** the TV switches layout within 30 seconds without manual interaction

### Requirement: TV-friendly rendering
The display SHALL render legibly from typical lobby viewing distances at 720p, 1080p and 4K, in landscape or portrait orientation. It SHALL hide the cursor and prevent screen sleep where the browser allows. Text in the queue panels MUST scale with screen size.

#### Scenario: Portrait monitor
- **WHEN** a display is configured with portrait orientation
- **THEN** the zones stack vertically and remain legible
