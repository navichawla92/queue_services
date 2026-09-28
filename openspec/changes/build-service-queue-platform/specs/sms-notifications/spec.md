## Purpose

Keeps customers informed by text message at each step of their visit and appointment. Each company decides which messages are sent and what they say, and opt-out and delivery status are tracked for compliance.

## ADDED Requirements

### Requirement: Notification events
The system SHALL support SMS notifications for these events:
- check-in confirmation (with ticket number, estimated wait and status link)
- estimated wait update when the estimate changes significantly
- queue position update at a configured position (e.g. "you are 3rd")
- "you're next"
- "representative ready" on call (with desk/room)
- desk/room assignment or change
- transfer notice
- appointment confirmation, reminder, reschedule and cancellation
- feedback request

Each event SHALL be individually enabled or disabled per tenant, with per-location overrides.

#### Scenario: Event disabled
- **WHEN** a location has disabled "queue position update"
- **THEN** no position update SMS is sent for tickets at that location

#### Scenario: Representative ready
- **WHEN** a ticket belonging to a consenting customer is called to Desk 3
- **THEN** the customer receives an SMS such as "It's your turn! Please go to Desk 3."

### Requirement: Editable templates
Each event SHALL have a default message template that tenants can edit. Templates may use placeholders: customer first name, ticket number, service, department, location name, desk/room, estimated wait, position, appointment date/time, status link, reschedule/cancel link and feedback link. The editor MUST preview the rendered message with its character and segment count, and MUST reject unknown placeholders.

#### Scenario: Template with unknown placeholder
- **WHEN** an admin saves a template containing {{favourite_color}}
- **THEN** the save is rejected with a message listing the valid placeholders

### Requirement: Consent and opt-out
The system SHALL only send SMS to numbers that consented for the visit or booking. If a customer replies STOP (or an equivalent keyword), the system MUST record the opt-out for that tenant's sending number and suppress all further SMS to that number from that tenant until the customer replies START. HELP replies SHALL receive a configured help message.

#### Scenario: Customer replies STOP
- **WHEN** a customer replies STOP to any message
- **THEN** the number is marked opted-out for that tenant and later notifications to it are suppressed and logged as "suppressed: opted out"

### Requirement: Asynchronous, reliable delivery
SMS SHALL be sent asynchronously so that staff actions and check-in are never blocked by the provider. Failed sends due to transient provider errors SHALL be retried with backoff up to a limit. Time-sensitive messages ("you're next", "representative ready") MUST NOT be sent if they have become stale, for example because the ticket was already completed.

#### Scenario: Provider outage
- **WHEN** the SMS provider returns a transient error
- **THEN** the message is retried with backoff and the queue operation that triggered it succeeds regardless

#### Scenario: Stale message discarded
- **WHEN** a "you're next" message is still queued but the ticket has already been completed
- **THEN** the message is discarded and logged as "skipped: stale"

### Requirement: Delivery tracking
The system SHALL log every SMS with its tenant, location, recipient, event, rendered body, provider message ID, status (queued, sent, delivered, failed, undelivered, suppressed, skipped) and cost if the provider reports it. Status SHALL be updated from provider delivery callbacks, and callbacks MUST be verified as authentic before they are processed.

#### Scenario: Delivery receipt
- **WHEN** the provider posts a "delivered" status callback with a valid signature
- **THEN** the message log entry is updated to Delivered

#### Scenario: Forged callback
- **WHEN** a status callback arrives with an invalid signature
- **THEN** it is rejected and no log entry changes

### Requirement: Swappable provider and sender configuration
SMS SHALL be sent through a provider abstraction. The initial provider is Twilio, and a development driver logs messages instead of sending them. Each tenant SHALL configure its provider credentials and sender (phone number or messaging service), with optional per-location senders. Credentials MUST be stored encrypted.

#### Scenario: Development environment
- **WHEN** the application runs with the log driver
- **THEN** notifications are written to the message log and application log and no external request is made

### Requirement: Quiet hours for non-urgent messages
Appointment reminders and feedback requests SHALL NOT be sent during configurable quiet hours in the location's time zone (default 9 PM–8 AM). They SHALL be deferred to the end of quiet hours or skipped if no longer relevant.

#### Scenario: Feedback request at night
- **WHEN** a service completes at 8:55 PM with a 10-minute feedback delay and quiet hours start at 9 PM
- **THEN** the feedback SMS is deferred to 8 AM the next day
