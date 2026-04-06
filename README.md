# Hous-Z App Site

Drupal 11 backend for the Hous-Z internal booking app. The site manages rooms, beds, availability, reservations, and email notifications for a pub-hotel used by company staff.

The mobile app consumes JSON from Drupal. Drupal is the source of truth for:

- room and bed inventory
- calendar availability
- booking creation
- booking approval or cancellation
- booking lookup by requester email
- email notifications to guest and manager

## Business Flow

The intended app flow is:

1. User logs into the app.
2. User selects check-in and check-out dates.
3. App requests available rooms and bed types for that date range.
4. App shows only available rooms/beds.
5. User selects room and bed type.
6. App sends booking request to Drupal.
7. Manager reviews the booking and updates the status to `confirmed` or `cancelled`.
8. Drupal notifies the guest and manager by email.

## Project Structure

- `web/modules/custom/hous_z_api`
  Custom REST API endpoints and booking email logic.
- `web/modules/custom/hous_z_management`
  Admin dashboard, booking management UI, and related Drupal management features.
- `config/sync`
  Drupal configuration export, including REST resources, fields, views, and mail settings.

## Key Dependencies

- Drupal 11
- `drupal/bee_hotel`
- `drupal/rest`
- `drupal/serialization`
- `drupal/message`
- `drupal/message_notify`
- `drupal/mailsystem`
- `drupal/simple_oauth`
- `drush/drush`

## Local Environment

This repository is configured for DDEV.

- Project name: `hous-z-app-site`
- Base URL: `https://hous-z-app-site.ddev.site`
- Docroot: `web`
- PHP: `8.3`
- Database: `MariaDB 10.11`

Useful commands:

```bash
ddev start
ddev composer install
ddev drush updb -y
ddev drush cim -y
ddev drush cr
```

## Custom Modules

### `hous_z_api`

Main responsibilities:

- create reservations
- update reservation status
- return reservations for a given email
- send booking emails

Important files:

- [`web/modules/custom/hous_z_api/src/Service/ReservationService.php`](./web/modules/custom/hous_z_api/src/Service/ReservationService.php)
- [`web/modules/custom/hous_z_api/src/Service/BookingNotifier.php`](./web/modules/custom/hous_z_api/src/Service/BookingNotifier.php)
- [`web/modules/custom/hous_z_api/hous_z_api.module`](./web/modules/custom/hous_z_api/hous_z_api.module)
- [`web/modules/custom/hous_z_api/src/Plugin/rest/resource/ReservationResource.php`](./web/modules/custom/hous_z_api/src/Plugin/rest/resource/ReservationResource.php)
- [`web/modules/custom/hous_z_api/src/Plugin/rest/resource/UserReservationsResource.php`](./web/modules/custom/hous_z_api/src/Plugin/rest/resource/UserReservationsResource.php)
- [`web/modules/custom/hous_z_api/src/Plugin/rest/resource/ReservationStatusResource.php`](./web/modules/custom/hous_z_api/src/Plugin/rest/resource/ReservationStatusResource.php)

### `hous_z_management`

Main responsibilities:

- admin booking management
- dashboard and listing pages
- state creation post-updates like `confirmed` and `cancelled`

## Booking Data Model

The booking flow uses BAT/Bee Hotel entities:

- `bat_unit`
  Room/unit record
- `bat_event`
  Availability/event instance for the requested stay
- `bat_booking`
  Booking record linked to the event

Relevant booking fields:

- `field_requester_email`
- `field_event_state`
- `booking_start_date`
- `booking_end_date`
- `field_booking_details`
- `field_check_in_time`
- `field_check_out_time`

Relevant room fields:

- `field_address`
- `field_manager_email`
- `field_beds`
- `field_cover_image`

## API Endpoints

### Create Reservation

`POST /api/reservation`

Request:

```json
{
  "unitId": 1,
  "bedType": "single_bed",
  "checkInDate": "2025-06-16",
  "checkOutDate": "2025-06-20",
  "email": "usuario@exemplo.com",
  "checkInTime": "14:00",
  "checkOutTime": "11:00",
  "details": "Algumas observacoes"
}
```

Response:

```json
{
  "room": {
    "roomName": "Coder Alley Room",
    "bedType": "single_bed",
    "imageUrls": [],
    "imageCount": 0,
    "address": "The Seed Warehouse",
    "managerEmail": "brandon@zoocha.com"
  },
  "bookingInfo": {
    "email": "usuario@exemplo.com",
    "checkIn": {
      "date": "2025-06-16",
      "time": "14:00"
    },
    "checkOut": {
      "date": "2025-06-20",
      "time": "11:00"
    }
  },
  "details": "Algumas observacoes"
}
```

### Get User Reservations

`POST /api/user/reservations`

Request:

```json
{
  "email": "usuario@exemplo.com"
}
```

Response:

```json
{
  "user": {
    "email": "usuario@exemplo.com",
    "totalReservations": 1
  },
  "reservations": [
    {
      "bookingId": 15,
      "room": {
        "unitId": 1,
        "roomName": "Coder Alley Room",
        "bedType": "single_bed",
        "address": "The Seed Warehouse",
        "managerEmail": "brandon@zoocha.com"
      },
      "dates": {
        "checkInDate": "2025-06-16",
        "checkInTime": "14:00",
        "checkOutDate": "2025-06-20",
        "checkOutTime": "11:00"
      },
      "status": "pending",
      "details": "Algumas observacoes",
      "createdAt": "2025-06-15T10:30:00Z"
    }
  ]
}
```

### Update Reservation Status

`PATCH /api/reservation/status`

Request:

```json
{
  "bookingId": 15,
  "status": "confirmed"
}
```

Allowed status values:

- `confirmed`
- `cancelled`

Response:

```json
{
  "bookingId": 15,
  "status": "confirmed",
  "message": "Booking status updated successfully."
}
```

## Email Flows

The current email implementation is code-driven from `hous_z_api` and sends:

- booking created to manager
- booking created confirmation to guest
- booking confirmed to guest
- booking cancelled to guest
- booking status changed to manager

Emails are triggered by entity hooks on `bat_booking` insert and update.

## Permissions and REST Access

Drupal REST resources require explicit permissions per method and resource.

Examples:

- `restful post reservation_resource`
- `restful post user_reservations_resource`
- `restful patch reservation_status_resource`

If the app consumes these endpoints without a Drupal login session, review authentication carefully. Current config uses `cookie` authentication on the exported REST resources, which is usually not ideal for a mobile app.

## Email Configuration

Current exported mail config points to Drupal's PHP mail/sendmail defaults.

Files to review:

- [`config/sync/system.mail.yml`](./config/sync/system.mail.yml)
- [`config/sync/mailsystem.settings.yml`](./config/sync/mailsystem.settings.yml)

Before relying on notifications in production, validate:

- Drupal can send email in the target environment
- each room has `field_manager_email` filled in
- the app sends a valid requester email

## Testing

Apply updates:

```bash
ddev drush updb -y
ddev drush cim -y
ddev drush cr
```

Create reservation:

```bash
curl --location --request POST 'https://hous-z-app-site.ddev.site/api/reservation' \
  --header 'Content-Type: application/json' \
  --data '{
    "unitId": 1,
    "bedType": "single_bed",
    "checkInDate": "2025-06-16",
    "checkOutDate": "2025-06-20",
    "email": "usuario@exemplo.com",
    "checkInTime": "14:00",
    "checkOutTime": "11:00",
    "details": "Teste de reserva"
  }'
```

Get user reservations:

```bash
curl --location --request POST 'https://hous-z-app-site.ddev.site/api/user/reservations' \
  --header 'Content-Type: application/json' \
  --data '{
    "email": "usuario@exemplo.com"
  }'
```

Confirm reservation:

```bash
curl --location --request PATCH 'https://hous-z-app-site.ddev.site/api/reservation/status' \
  --header 'Content-Type: application/json' \
  --data '{
    "bookingId": 15,
    "status": "confirmed"
  }'
```

Cancel reservation:

```bash
curl --location --request PATCH 'https://hous-z-app-site.ddev.site/api/reservation/status' \
  --header 'Content-Type: application/json' \
  --data '{
    "bookingId": 15,
    "status": "cancelled"
  }'
```

## Known Gaps and Cleanup Items

- The legacy endpoint `/api/my-reservations/{email}` still exists and is still enabled. It should be treated as deprecated to avoid app-side confusion.
- REST authentication is still cookie-based in config export. For a real mobile app integration, OAuth or another explicit API auth flow is preferable.
- Message template config from earlier email experiments still exists in `config/sync`, but the active email flow now runs through `hook_mail()` in `hous_z_api.module`.
- End-to-end email delivery still depends on environment mail transport, not just code correctness.

## Recommended Next Steps

1. Decide which reservation endpoint is canonical and deprecate the old one.
2. Align REST authentication with the mobile app strategy.
3. Test booking creation, confirmation, and cancellation with real email delivery.
4. Review final copy for all outgoing emails in English or Portuguese as needed.
5. Give the app team the final endpoint contract from this README.
