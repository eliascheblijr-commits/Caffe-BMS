#!/bin/sh
set -e

OUTPUT_FILE="/etc/nginx/conf.d/cloudflare_proxy.conf"

echo "# Hardened Container Real IP Core Configuration" > "$OUTPUT_FILE"
echo "# Evaluated dynamically at boot state" >> "$OUTPUT_FILE"

# Extract the local container IP network block using the default routing interface
# This ensures that no matter what subnet Docker allocates to gym_network, it is matched perfectly.
LOCAL_SUBNET=$(ip -o -4 route show to default | awk '{print $5}' | xargs ip -o -4 addr show dev | awk '{print $4}')

if [ ! -z "$LOCAL_SUBNET" ]; then
    echo "set_real_ip_from $LOCAL_SUBNET;" >> "$OUTPUT_FILE"
    echo "Processing Network: Trusted Docker Subnet verified at $LOCAL_SUBNET"
else
    # Fail-safe security fallback to broad class networks if interface lookup fails
    echo "set_real_ip_from 172.16.0.0/12;" >> "$OUTPUT_FILE"
    echo "set_real_ip_from 192.168.0.0/16;" >> "$OUTPUT_FILE"
    echo "set_real_ip_from 10.0.0.0/8;" >> "$OUTPUT_FILE"
fi

# Explicit trust assignment for Cloudflare Tunnel internal traffic properties
echo "real_ip_header CF-Connecting-IP;" >> "$OUTPUT_FILE"
echo "real_ip_recursive on;" >> "$OUTPUT_FILE"