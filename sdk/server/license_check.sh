#!/bin/bash
# ============================================================
# License Client SDK for Server (Debian/Ubuntu)
# ============================================================
# Usage:
#   export LICENSE_SERVER="https://license-server.com"
#   export LICENSE_API_KEY="YOUR_API_KEY"
#   ./license_check.sh activate "SP-XXXX-XXXX-XXXX"
#   ./license_check.sh verify "SP-XXXX-XXXX-XXXX"
#   ./license_check.sh deactivate "SP-XXXX-XXXX-XXXX"
#   ./license_check.sh status "SP-XXXX-XXXX-XXXX"
#   ./license_check.sh ping
# ============================================================

set -e

LICENSE_SERVER="${LICENSE_SERVER:-https://localhost}"
LICENSE_API_KEY="${LICENSE_API_KEY:-}"
LICENSE_KEY="${LICENSE_KEY:-}"

# ============================================================
# Generate server fingerprint
# ============================================================
generate_fingerprint() {
    local ip=$(hostname -I 2>/dev/null | awk '{print $1}')
    local hostname=$(hostname)
    local mac=$(cat /sys/class/net/$(ip route show default | awk '/default/ {print $5; exit}')/address 2>/dev/null || echo "")
    local disk_serial=$(lsblk -no SERIAL /dev/sda 2>/dev/null || echo "")
    local os_info=$(cat /etc/os-release 2>/dev/null | grep PRETTY_NAME | cut -d'"' -f2 || echo "unknown")

    echo "$(echo -n "${ip}|${hostname}|${mac}|${disk_serial}|${os_info}" | sha256sum | awk '{print $1}')"
}

# ============================================================
# Get server IP address
# ============================================================
get_ip() {
    hostname -I 2>/dev/null | awk '{print $1}'
}

# ============================================================
# Make API request
# ============================================================
api_request() {
    local method="$1"
    local endpoint="$2"
    local data="$3"

    if [ "$method" = "GET" ]; then
        curl -sS -X GET \
            -H "X-API-Key: ${LICENSE_API_KEY}" \
            -H "Accept: application/json" \
            "${LICENSE_SERVER}${endpoint}?${data}"
    else
        curl -sS -X POST \
            -H "X-API-Key: ${LICENSE_API_KEY}" \
            -H "Content-Type: application/json" \
            -H "Accept: application/json" \
            -d "${data}" \
            "${LICENSE_SERVER}${endpoint}"
    fi
}

# ============================================================
# Commands
# ============================================================
cmd_activate() {
    local license_key="$1"
    local fingerprint=$(generate_fingerprint)
    local ip=$(get_ip)

    echo "Activating license: ${license_key}" >&2
    echo "Fingerprint: ${fingerprint}" >&2

    api_request "POST" "/api/v1/activate" "{
        \"license_key\": \"${license_key}\",
        \"fingerprint\": \"${fingerprint}\",
        \"platform\": \"server\",
        \"ip_address\": \"${ip}\",
        \"device_info\": {
            \"hostname\": \"$(hostname)\",
            \"os_info\": \"$(cat /etc/os-release 2>/dev/null | grep PRETTY_NAME | cut -d'\"' -f2)\"
        }
    }"
}

cmd_verify() {
    local license_key="$1"
    local fingerprint=$(generate_fingerprint)
    local ip=$(get_ip)

    echo "Verifying license: ${license_key}" >&2

    api_request "POST" "/api/v1/verify" "{
        \"license_key\": \"${license_key}\",
        \"fingerprint\": \"${fingerprint}\",
        \"platform\": \"server\",
        \"ip_address\": \"${ip}\",
        \"device_info\": {
            \"hostname\": \"$(hostname)\"
        }
    }"
}

cmd_deactivate() {
    local license_key="$1"
    local fingerprint=$(generate_fingerprint)

    echo "Deactivating license: ${license_key}" >&2

    api_request "POST" "/api/v1/deactivate" "{
        \"license_key\": \"${license_key}\",
        \"fingerprint\": \"${fingerprint}\",
        \"platform\": \"server\"
    }"
}

cmd_status() {
    local license_key="$1"
    api_request "GET" "/api/v1/license/${license_key}" ""
}

cmd_ping() {
    api_request "POST" "/api/v1/ping" "{}"
}

# ============================================================
# Main
# ============================================================
case "$1" in
    activate)
        cmd_activate "$2"
        ;;
    verify)
        cmd_verify "$2"
        ;;
    deactivate)
        cmd_deactivate "$2"
        ;;
    status)
        cmd_status "$2"
        ;;
    ping)
        cmd_ping
        ;;
    *)
        echo "Usage: $0 {activate|verify|deactivate|status|ping} [license_key]"
        exit 1
        ;;
esac

echo ""