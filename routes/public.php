<?php

/*
| Unauthenticated customer surfaces. Tenant/location is resolved from an
| unguessable public identifier or token in the URL, never from user input.
|
|   /c/{location}      QR / mobile check-in
|   /t/{token}         live ticket status page
|   /book/{location}   online booking
|   /a/{token}         manage appointment (reschedule / cancel)
|   /f/{token}         feedback form
|   /webhooks/sms/*    provider callbacks (signature-verified)
*/
