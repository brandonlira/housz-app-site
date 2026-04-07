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

## Configuration

### Minimum Stay

The minimum number of nights required per booking is controlled via Drupal config:

```bash
ddev drush cset hous_z_api.settings min_stay 2
ddev drush cr
```

Default is `2`. The value is read by `GET /api/rooms` and returned in each room's `calendarData.minStay` field.

---

## Availability Logic

A booking occupies nights from check-in (inclusive) to check-out (exclusive).

- A booking for **01/05 → 03/05** occupies the nights of **01/05 and 02/05**.
- **03/05** is free and can be used as check-in for the next booking (back-to-back).

A bed type is considered unavailable on a given day when the number of overlapping bookings for that type equals or exceeds `field_bed_quantity` on the unit.

---

## API Endpoints

### List Rooms

`GET /api/rooms`

Optional query parameters: `checkInDate`, `checkOutDate`.

Response:

```json
{
  "rooms": [
    {
      "room": {
        "roomName": "Coder Alley Room",
        "description": "...",
        "imageUrl": "https://...",
        "tags": ["ensuite"],
        "availableBeds": [
          { "type": "single_bed", "quantity": 2 },
          { "type": "double_bed", "quantity": 1 }
        ]
      },
      "calendarData": {
        "checkInDate": "2025-06-16",
        "checkOutDate": "2025-06-20",
        "minStay": 2
      }
    }
  ]
}
```

---

### Check Availability (per bed type)

`GET /api/availability/{unitId}/{bedType}/{start}/{end}`

- `bedType`: `single_bed` or `double_bed`
- `start` / `end`: `YYYY-MM-DD`

Returns a calendar tree with per-day availability for the given unit and bed type.

---

### Full Occupancy Calendar

`GET /api/full-occupancy`

Returns a calendar of dates where **all** bed types across **all** units are fully booked. Used by the app to block dates on the main calendar before the user selects a room.

---

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

### Delete Reservation

`DELETE /api/reservation/{id}`

Only the booking owner or a user with the `manage housz bookings` permission can delete a booking. Returns `Access denied` otherwise.

---

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

- The legacy endpoint `GET /api/my-reservations/{email}` still exists alongside `POST /api/user/reservations`. Both return reservation history. Treat the legacy one as deprecated and remove once the app migrates.
- REST authentication is still cookie-based in config export. For a mobile app, OAuth (`simple_oauth`) or token-based auth is preferable.
- Message template config from earlier email experiments still exists in `config/sync`, but the active email flow runs through `hook_mail()` in `hous_z_api.module`. The old config can be removed.
- End-to-end email delivery depends on environment mail transport. Validate with a real SMTP provider before going live.

## Management Portal

The web portal at `/housz` is for **managers only** (role `housz_admin`). Staff book via the mobile app.

| Page | URL | Access |
|---|---|---|
| Dashboard | `/housz` | `housz_admin` |
| Bookings list | `/housz/bookings` | `housz_admin` |
| Rooms list | `/housz/units` | `housz_admin` |
| Settings | `/housz/settings` | `housz_admin` |
| Login | `/user/login` | public |

### Notification settings

At `/housz/settings` a manager can configure:

- **Notify role** — all active users with `housz_admin` receive booking emails automatically
- **Additional emails** — extra recipients with no Drupal account required

Both are cumulative. The fallback is `field_manager_email` on the room if nothing is configured.

### Roles

| Role | Purpose |
|---|---|
| `administrator` | Full Drupal access |
| `housz_admin` | Manager portal only — bookings, rooms, settings, email notifications |

---

## Changelog

### 2026-04

- Fixed availability date boundary: check-out day is now correctly excluded from occupancy checks, allowing back-to-back bookings.
- Fixed overlap query to use strict inequality (`<` / `>`), consistent with the date boundary rule.
- Added ownership check to `DELETE /api/reservation/{id}`: only the booking owner or an admin can delete.
- Moved `minStay` from hardcoded value to `hous_z_api.settings` config (`min_stay` key).
- Added `hous_z_api` as declared dependency of `hous_z_management` to prevent container errors.
- Created custom `hous_z_theme` (Starterkit-based) with Zoocha branding (`#3a11c8` purple, `#F04E37` coral).
- Built management portal at `/housz` with dashboard, bookings list, rooms list, and settings pages.
- Role `housz_admin` created with scoped permissions (no Drupal admin access).
- Booking email notifications now driven by role or manually listed emails via `/housz/settings`.
- Email templates migrated to Drupal Twig (`templates/email/hous-z-api-email.html.twig`).
- Login page redesigned with split-screen layout (form left, brand panel right).

## Recommended Next Steps

1. Decide which reservation history endpoint is canonical (`/api/my-reservations` vs `/api/user/reservations`) and remove the other.
2. Align REST authentication with the mobile app strategy (OAuth recommended).
3. Test booking creation, confirmation, and cancellation with real email delivery.
4. Review final copy for all outgoing emails in English or Portuguese as needed.
5. Give the app team the final endpoint contract from this README.
