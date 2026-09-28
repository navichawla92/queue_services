## Purpose

Lets customers join the queue from a lobby kiosk, a QR code, their own phone or through a receptionist. Walk-ins and appointment arrivals are handled the same way: both get a queue ticket and a wait estimate.

## ADDED Requirements

### Requirement: Check-in channels
The system SHALL support check-in through:
- (a) a paired lobby kiosk/tablet in full-screen mode;
- (b) a per-location QR code that opens mobile check-in;
- (c) a per-location public mobile web link;
- (d) a receptionist creating a ticket on the customer's behalf.

Each ticket MUST record the channel used.

#### Scenario: QR check-in
- **WHEN** a customer scans the location's QR code
- **THEN** a mobile check-in page for that location opens without requiring an account

#### Scenario: Receptionist check-in
- **WHEN** a receptionist enters a customer's details and service in the staff console
- **THEN** a ticket is created with channel "receptionist"

### Requirement: Check-in data capture
Check-in SHALL collect the customer's name (required) and mobile phone number, and the service they need. Admins can configure whether the phone number is required or optional and whether the customer picks a department or is routed automatically. Phone numbers MUST be validated and stored in E.164 format. If SMS notices are enabled, the customer MUST agree to receive SMS before any SMS is sent.

#### Scenario: Invalid phone number
- **WHEN** a customer enters "12345" as a phone number
- **THEN** the form shows a validation error and no ticket is created

#### Scenario: SMS consent declined
- **WHEN** a customer checks in with a phone number but does not agree to SMS
- **THEN** a ticket is created and no SMS is sent to that number for this visit

### Requirement: Returning customer recognition
The system SHALL match check-ins to an existing customer record in the same tenant by phone number. It SHALL link the new visit to that record without showing the customer's stored details on self-service surfaces.

#### Scenario: Returning customer
- **WHEN** a customer checks in with a phone number already on file
- **THEN** the visit is linked to the existing customer and staff can see prior visit history

### Requirement: Ticket issuance
On successful check-in, the system SHALL create a ticket in Waiting status with a queue number. The number is made of the department prefix and a sequence that resets daily per location and department (e.g. "A-012"). The customer MUST then see their queue number, service, department, position and estimated wait.

#### Scenario: Ticket confirmation on kiosk
- **WHEN** a kiosk check-in completes
- **THEN** the kiosk shows the ticket number, position and estimated wait for at least 10 seconds before returning to the start screen

#### Scenario: Daily sequence reset
- **WHEN** the first ticket of a new local day is issued for department "A"
- **THEN** its number is "A-001"

### Requirement: Live ticket status page
Mobile and QR check-ins SHALL land on a ticket status page that updates live with position, estimated wait, and current status (Waiting, Called with desk/room, In Service, Completed). The link is also sent by SMS. The page MUST let the customer cancel (leave the queue).

#### Scenario: Customer called while on status page
- **WHEN** an employee calls the customer's ticket
- **THEN** the status page updates within 3 seconds to show "It's your turn — please go to Desk 3"

#### Scenario: Customer leaves queue
- **WHEN** the customer taps "Leave queue" and confirms
- **THEN** the ticket is marked Cancelled by customer and removed from the waiting list

### Requirement: Appointment check-in
Customers with an appointment SHALL be able to check in by entering their phone number or a confirmation code, or through a check-in link in their reminder SMS. The system MUST find today's appointment at that location and create a ticket linked to it, keeping the appointment's service and assigned employee.

#### Scenario: Appointment found
- **WHEN** a customer with a 2:00 PM appointment checks in at 1:50 PM using their phone number
- **THEN** a ticket linked to the appointment is created with the appointment's service and employee, and the appointment is marked Arrived

#### Scenario: No appointment found
- **WHEN** a customer chooses "I have an appointment" but no appointment matches today at this location
- **THEN** the customer is offered walk-in check-in instead

### Requirement: Duplicate check-in prevention
The system SHALL prevent the same phone number from holding more than one active ticket at the same location at the same time. Instead of creating a new ticket, it shows the existing ticket.

#### Scenario: Customer checks in twice
- **WHEN** a customer with an active waiting ticket checks in again at the same location
- **THEN** the system shows their existing ticket instead of issuing a new one

### Requirement: Kiosk accessibility and resilience
The kiosk SHALL provide large touch targets, adjustable text size, high-contrast mode and multiple languages, as configured by the tenant. It MUST reset to the start screen after 60 seconds of inactivity and MUST NOT expose browser navigation or other customers' data.

#### Scenario: Abandoned kiosk session
- **WHEN** a customer walks away mid-check-in for 60 seconds
- **THEN** the kiosk clears the entered data and returns to the welcome screen
