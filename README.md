# Hous-Z App Site

Drupal 11 backend for the Hous-Z internal room booking platform at Zoocha.

Employees book rooms via the Flutter mobile app. Managers review and approve bookings via the web portal at `/housz`.

---

## Business Flow

1. Employee opens the app and logs in via OAuth.
2. Employee selects check-in and check-out dates.
3. App fetches available rooms and bed types for that period.
4. Employee selects room and bed type and submits a booking request.
5. Booking is created with status `pending`.
6. Manager receives an email notification and approves or cancels via `/housz/bookings`.
7. Employee receives a confirmation email.

---

## Project Structure

```
web/modules/custom/hous_z_api        REST API endpoints and email notifications
web/modules/custom/hous_z_management Admin portal, dashboard, settings
web/themes/hous_z_theme              Custom Starterkit theme (Zoocha branding)
config/sync                          Drupal configuration export
oauth-keys/                          OAuth RSA keys (not committed to git)
```

---

## Local Environment

Configured for DDEV.

```
Project:  hous-z-app-site
URL:      https://hous-z-app-site.ddev.site
Docroot:  web
PHP:      8.3
Database: MariaDB 10.11
```

```bash
ddev start
ddev composer install
ddev drush updb -y
ddev drush cim -y
ddev drush cr
```

---

## Key Dependencies

- Drupal 11
- `drupal/bat` + `drupal/bee_hotel` — room and booking management
- `drupal/rest` + `drupal/serialization` — REST API
- `drupal/simple_oauth` — OAuth 2.0 authentication
- `drupal/consumers` — OAuth client management
- `drupal/basic_auth` — basic auth (dev/testing only)
- `drupal/message` + `drupal/message_notify` — email notifications
- `drush/drush`

---

## Authentication

The API uses **OAuth 2.0** via `simple_oauth`.

### OAuth client (Flutter app)

```
Client ID:     housz-flutter-app
Redirect URI:  housz://oauth/callback
Scope:         api
Grant types:   Authorization Code + PKCE (production), Client Credentials (testing)
```

Token endpoint: `POST /oauth/token`
Authorize endpoint: `GET /oauth/authorize`

RSA keys are stored in `oauth-keys/` (excluded from git). Regenerate on each environment:

```bash
mkdir -p oauth-keys
openssl genrsa -out oauth-keys/private.key 2048
openssl rsa -in oauth-keys/private.key -pubout -out oauth-keys/public.key
chmod 600 oauth-keys/private.key
```

Then update the key paths at `/admin/config/services/consumer`.

### REST resource authentication

All REST resources accept `cookie` and `oauth2`. Anonymous access is disabled.

### Permissions

| Role | Access |
|---|---|
| `anonymous` | None |
| `authenticated` | GET rooms/availability, POST reservation, GET own reservations |
| `housz_admin` | All of the above + PATCH status, DELETE reservation, GET all bookings |

---

## API Endpoints

All requests require:
```
Authorization: Bearer {access_token}
Accept: application/json
```

| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/rooms` | Available rooms for a date range |
| GET | `/api/availability/{unitId}/{bedType}/{start}/{end}` | Daily availability calendar |
| GET | `/api/full-occupancy` | Fully booked dates across all rooms |
| POST | `/api/reservation` | Create a reservation |
| POST | `/api/user/reservations` | Reservations by requester email |
| PATCH | `/api/reservation/status` | Update status (manager only) |
| DELETE | `/api/reservation` | Delete a reservation (manager only) |

Query parameter `_format=json` required on all endpoints.

bedType values: `single_bed`, `double_bed`

### Create reservation — body

```json
{
  "unitId": "1",
  "bedType": "single_bed",
  "checkInDate": "2026-05-01",
  "checkOutDate": "2026-05-05",
  "email": "employee@zoocha.com",
  "notes": "optional"
}
```

### Update status — body

```json
{
  "booking_id": 42,
  "status": "confirmed"
}
```

Status values: `confirmed`, `cancelled`

### Booking status flow

```
pending → confirmed (manager approves)
pending → cancelled (manager cancels)
```

---

## Availability Logic

A booking occupies nights from check-in (inclusive) to check-out (exclusive).

- A booking for **01/05 → 03/05** occupies nights **01/05 and 02/05**.
- **03/05** is free and can be used as check-in for the next booking (back-to-back bookings allowed).

A bed type is unavailable on a day when the number of overlapping bookings equals `field_bed_quantity` on the unit.

---

## Data Model

### BAT entities

- `bat_unit` — room record
- `bat_event` — availability event for the stay
- `bat_booking` — booking record linked to the event

### Room fields

- `field_beds` (paragraph: `field_bed_type`, `field_bed_quantity`)
- `field_address`
- `field_manager_email`
- `field_cover_image`

### Booking fields

- `field_requester_email`
- `field_event_state`
- `booking_start_date` / `booking_end_date`
- `field_booking_details`

---

## Management Portal

Web portal for managers at `/housz` (role `housz_admin` required).

| Page | URL |
|---|---|
| Dashboard | `/housz` |
| Bookings | `/housz/bookings` |
| Rooms | `/housz/units` |
| Settings | `/housz/settings` |

### Notification settings (`/housz/settings`)

- **Notify role** — all users with `housz_admin` receive booking emails automatically
- **Additional emails** — extra recipients (no Drupal account required)

Both lists are cumulative. Fallback is `field_manager_email` on the room.

---

## Email Notifications

Triggered via `hook_mail()` in `hous_z_api.module` on booking insert and update.

| Event | Recipients |
|---|---|
| Booking created | Manager(s) + requester |
| Booking confirmed | Requester |
| Booking cancelled | Requester |
| Status changed | Manager(s) |

Template: `web/modules/custom/hous_z_api/templates/email/hous-z-api-email.html.twig`

---

## Configuration

### Minimum stay

```bash
ddev drush cset hous_z_api.settings min_stay 2
ddev drush cr
```

Default is `2` nights. Returned in `GET /api/rooms` as `calendarData.minStay`.

### Notification recipients

```bash
ddev drush cset hous_z_management.settings notify_role housz_admin
ddev drush cr
```

---

## Testing

The Postman collection `hous-z-postman-collection.json` covers all endpoints.

1. Import into Postman
2. Run **Auth → Get Token** — token is saved automatically to `{{access_token}}`
3. Run any other request

See `FLUTTER_INTEGRATION.md` for the full Flutter integration guide and OAuth setup.

---

## Changelog

### 2026-04

- OAuth 2.0 configured with `simple_oauth` — Authorization Code + PKCE for production, Client Credentials for testing
- Anonymous access removed from all REST resources
- Basic auth removed from production REST resources
- RSA keys added to `.gitignore`
- `grant simple_oauth codes` permission granted to authenticated and `housz_admin` roles
- `simple_oauth` upgraded from 6.0.0 to 6.1.0
- Postman collection updated with OAuth token flow
- `FLUTTER_INTEGRATION.md` created for the Flutter team
- Management portal built at `/housz` (dashboard, bookings, rooms, settings)
- Role `housz_admin` created with scoped permissions
- Login page redesigned with split-screen layout (Zoocha branding)
- Booking email notifications configurable by role or manual email list
- Fixed availability date boundary — back-to-back bookings now allowed
- Fixed `AvailabilityResource` stdClass array error

---

## Recommended Next Steps

1. Regenerate OAuth RSA keys on the production server
2. Update `client_secret` for production and store securely (not in git)
3. Test full Authorization Code + PKCE flow with the Flutter app using `flutter_appauth`
4. Validate email delivery with a real SMTP provider before go-live
5. Remove legacy endpoint `GET /api/my-reservations/{email}` once app migrates to `POST /api/user/reservations`
