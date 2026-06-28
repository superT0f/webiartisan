#!/usr/bin/env bash
set -euo pipefail

BASE_URL="http://localhost:8080/api"
ENDPOINTS=(
  "/"
  "/cities/livry"
  "/artisans?city=livry"
  "/cities/livry/pois"
  "/cities/livry/schedules"
)

FAILED=0

for endpoint in "${ENDPOINTS[@]}"; do
  url="${BASE_URL}${endpoint}"
  http_code=$(curl -s -o /dev/null -w "%{http_code}" "${url}" || true)

  if [[ "${http_code}" =~ ^2[0-9][0-9]$ ]]; then
    echo "✅ ${url} -> ${http_code}"
  else
    echo "❌ ${url} -> ${http_code}"
    FAILED=1
  fi
done

if [[ "${FAILED}" -ne 0 ]]; then
  exit 1
fi

echo "All API smoke tests passed."
