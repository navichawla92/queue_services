## Purpose

Gives staff a live, simple dashboard of the queue and the actions to move customers through it from waiting to served. Every action is reflected immediately on all connected screens.

## ADDED Requirements

### Requirement: Real-time queue dashboard
The system SHALL give staff a dashboard for their current location. It lists active tickets with: queue number, customer name, requested service, department, check-in time, current waiting time (live-ticking), appointment or walk-in indicator, assigned employee, status and an internal notes indicator. The dashboard MUST reflect changes made by any user or customer within 2 seconds, without a manual refresh.

#### Scenario: New check-in appears
- **WHEN** a customer checks in at the kiosk
- **THEN** the ticket appears on every open staff dashboard for that location within 2 seconds

#### Scenario: Connection lost and restored
- **WHEN** a staff dashboard loses its real-time connection and then reconnects
- **THEN** it shows a "reconnecting" indicator while offline and reloads the full current queue state on reconnection

### Requirement: Dashboard filtering and views
Staff SHALL be able to filter the dashboard by department, service, status, assigned employee and customer type. Each employee SHALL also have a "My queue" view showing tickets assigned to them or eligible for them.

#### Scenario: Filter by department
- **WHEN** a receptionist filters by department "Loans"
- **THEN** only tickets in the Loans department are shown, and live updates respect the filter

### Requirement: Ticket lifecycle
Tickets SHALL follow these statuses: Waiting, Called, In Service, On Hold, Completed, No-Show, Cancelled and Transferred (transitional). Only these transitions are allowed:
- Waiting → Called, On Hold, Cancelled, No-Show
- Called → In Service, Waiting (recall/requeue), No-Show
- In Service → Completed, On Hold, Waiting (via transfer)
- On Hold → Waiting

Every transition MUST record the actor and a timestamp.

#### Scenario: Invalid transition rejected
- **WHEN** a user attempts to complete a ticket that is still Waiting
- **THEN** the action is rejected with an explanatory error

### Requirement: Call next customer
An Available employee SHALL be able to "call next". This atomically claims the highest-ordered ticket they are eligible for, sets it to Called with the employee and their desk/room, and triggers the lobby display and "representative ready" notification. Two employees calling at the same moment MUST NOT receive the same ticket.

#### Scenario: Concurrent call next
- **WHEN** two employees press "call next" simultaneously and only one eligible ticket is waiting
- **THEN** exactly one employee receives the ticket and the other is told the queue is empty

#### Scenario: Recall
- **WHEN** a called customer has not come forward and the employee presses "recall"
- **THEN** the lobby display highlights the ticket again and a reminder SMS is sent if enabled

### Requirement: Call a specific ticket
Staff SHALL be able to call a specific waiting ticket out of order, if they are eligible for it or have queue-management permission.

#### Scenario: Receptionist calls out of order
- **WHEN** a receptionist selects ticket B-004 and clicks "call" for employee Maria
- **THEN** B-004 becomes Called with Maria and her desk

### Requirement: Assign and transfer
Staff with the right permission SHALL be able to assign a waiting ticket to a specific employee. They SHALL also be able to transfer a ticket to another employee, another department, or both, optionally with a note. A transferred ticket returns to Waiting in the target queue and keeps its original check-in time for reporting. Its position in the new queue MUST follow the tenant's transfer policy: keep original queue time, or join at the back.

#### Scenario: Transfer to another department
- **WHEN** an employee serving ticket A-012 transfers it to "Loans" with the note "needs loan officer"
- **THEN** the ticket leaves the employee, appears Waiting in Loans with the note, and the customer is notified if enabled

### Requirement: Hold and release
Staff SHALL be able to place a ticket On Hold with an optional reason, for example while the customer fetches documents, and later release it back to Waiting. Held time MUST be tracked separately from waiting time.

#### Scenario: Customer on hold
- **WHEN** an employee places ticket A-007 on hold with reason "getting ID"
- **THEN** A-007 is excluded from call next and shown in an On Hold section until released

### Requirement: Start and complete service
The employee SHALL be able to start service on a Called ticket and complete an In Service ticket. Completion MAY capture an outcome and notes. Completion MUST set the ticket's service end time and, if feedback is enabled, trigger the feedback request.

#### Scenario: Complete service
- **WHEN** an employee completes ticket A-012
- **THEN** its status becomes Completed, its service duration is recorded, and it leaves the active queue

### Requirement: Mark no-show
Staff SHALL be able to mark a Waiting or Called ticket as No-Show. Tenants MAY enable auto-no-show: a Called ticket not started within N minutes is marked No-Show. A no-show on an appointment ticket MUST also mark the appointment as No-Show.

#### Scenario: Auto no-show
- **WHEN** auto-no-show is 5 minutes and a Called ticket has not been started after 5 minutes
- **THEN** the ticket is marked No-Show and the employee is freed for the next customer

### Requirement: Internal notes
Staff SHALL be able to add timestamped internal notes to a ticket and to the customer record. Notes MUST never be shown on customer-facing surfaces or sent by SMS.

#### Scenario: Note visible to next employee
- **WHEN** an employee adds a note and transfers the ticket
- **THEN** the receiving employee sees the note with author and time

### Requirement: End-of-day handling
At the location's closing time plus a configurable buffer, the system SHALL close out any tickets still Waiting or On Hold as "Closed unserved". These count as abandoned in reports and are removed from the live queue.

#### Scenario: Leftover tickets at close
- **WHEN** the closing buffer elapses with 3 tickets still waiting
- **THEN** those tickets are marked Closed unserved and do not appear on the next day's queue
