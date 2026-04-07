#!/bin/bash
# =============================================================================
# Hous-Z API — Test script
# Base URL: https://hous-z-app-site.ddev.site
# Usage: bash test-api.sh
# =============================================================================

BASE="https://hous-z-app-site.ddev.site"
EMAIL="test@zoocha.com"
CHECK_IN="2026-08-01"
CHECK_OUT="2026-08-05"
UNIT_ID=1
BED_TYPE="single_bed"

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo ""
echo -e "${BLUE}=============================================${NC}"
echo -e "${BLUE}  Hous-Z API Test Suite${NC}"
echo -e "${BLUE}=============================================${NC}"

# ── 1. List rooms ─────────────────────────────────────────────────────────────
echo ""
echo -e "${YELLOW}[1] GET /api/rooms — List all rooms${NC}"
curl -s -X GET \
  "${BASE}/api/rooms?checkInDate=${CHECK_IN}&checkOutDate=${CHECK_OUT}" \
  -H "Accept: application/json" \
  | python3 -m json.tool 2>/dev/null || echo "(raw response above)"

# ── 2. Check availability ─────────────────────────────────────────────────────
echo ""
echo -e "${YELLOW}[2] GET /api/availability/{unitId}/{bedType}/{start}/{end}${NC}"
echo -e "    Unit: ${UNIT_ID} | Bed: ${BED_TYPE} | ${CHECK_IN} → ${CHECK_OUT}"
curl -s -X GET \
  "${BASE}/api/availability/${UNIT_ID}/${BED_TYPE}/${CHECK_IN}/${CHECK_OUT}" \
  -H "Accept: application/json" \
  | python3 -m json.tool 2>/dev/null || echo "(raw response above)"

# ── 3. Full occupancy calendar ────────────────────────────────────────────────
echo ""
echo -e "${YELLOW}[3] GET /api/full-occupancy — Fully booked dates (next 12 months)${NC}"
curl -s -X GET \
  "${BASE}/api/full-occupancy" \
  -H "Accept: application/json" \
  | python3 -m json.tool 2>/dev/null || echo "(raw response above)"

# ── 4. Create reservation ─────────────────────────────────────────────────────
echo ""
echo -e "${YELLOW}[4] POST /api/reservation — Create a booking${NC}"
BOOKING_RESPONSE=$(curl -s -X POST \
  "${BASE}/api/reservation?_format=json" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"unitId\": ${UNIT_ID},
    \"bedType\": \"${BED_TYPE}\",
    \"checkInDate\": \"${CHECK_IN}\",
    \"checkOutDate\": \"${CHECK_OUT}\",
    \"email\": \"${EMAIL}\",
    \"checkInTime\": \"14:00\",
    \"checkOutTime\": \"11:00\",
    \"details\": \"API test booking\"
  }")
echo $BOOKING_RESPONSE | python3 -m json.tool 2>/dev/null || echo $BOOKING_RESPONSE

# Extract booking ID if available
BOOKING_ID=$(echo $BOOKING_RESPONSE | python3 -c "import sys,json; d=json.load(sys.stdin); print(d.get('bookingInfo',{}).get('bookingId',''))" 2>/dev/null)
echo -e "${GREEN}    → Booking ID: ${BOOKING_ID}${NC}"

# ── 5. Get user reservations ──────────────────────────────────────────────────
echo ""
echo -e "${YELLOW}[5] POST /api/user/reservations — Reservations for ${EMAIL}${NC}"
curl -s -X POST \
  "${BASE}/api/user/reservations?_format=json" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{\"email\": \"${EMAIL}\"}" \
  | python3 -m json.tool 2>/dev/null || echo "(raw response above)"

# ── 6. Get reservations by email (legacy) ─────────────────────────────────────
echo ""
echo -e "${YELLOW}[6] GET /api/my-reservations/{email} — Legacy endpoint${NC}"
curl -s -X GET \
  "${BASE}/api/my-reservations/${EMAIL}?_format=json" \
  -H "Accept: application/json" \
  | python3 -m json.tool 2>/dev/null || echo "(raw response above)"

# ── 7. Update booking status (confirm) ───────────────────────────────────────
echo ""
echo -e "${YELLOW}[7] PATCH /api/reservation/status — Confirm booking ID ${BOOKING_ID:-<set manually>}${NC}"
echo -e "    (Edit BOOKING_ID in this script or replace below)"
PATCH_ID="${BOOKING_ID:-71}"
curl -s -X PATCH \
  "${BASE}/api/reservation/status?_format=json" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"bookingId\": ${PATCH_ID},
    \"status\": \"confirmed\"
  }" \
  | python3 -m json.tool 2>/dev/null || echo "(raw response above)"

# ── 8. Update booking status (cancel) ────────────────────────────────────────
echo ""
echo -e "${YELLOW}[8] PATCH /api/reservation/status — Cancel booking ID ${PATCH_ID}${NC}"
curl -s -X PATCH \
  "${BASE}/api/reservation/status?_format=json" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d "{
    \"bookingId\": ${PATCH_ID},
    \"status\": \"cancelled\"
  }" \
  | python3 -m json.tool 2>/dev/null || echo "(raw response above)"

# ── 9. Delete reservation ─────────────────────────────────────────────────────
echo ""
echo -e "${YELLOW}[9] DELETE /api/reservation/{id} — Delete booking ID ${PATCH_ID}${NC}"
curl -s -X DELETE \
  "${BASE}/api/reservation/${PATCH_ID}?_format=json" \
  -H "Accept: application/json" \
  | python3 -m json.tool 2>/dev/null || echo "(raw response above)"

echo ""
echo -e "${BLUE}=============================================${NC}"
echo -e "${GREEN}  Done.${NC}"
echo -e "${BLUE}=============================================${NC}"
echo ""
