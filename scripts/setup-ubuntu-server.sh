#!/bin/bash
# FastPOS Bare-Metal Provisioning Script for Ubuntu 24.04 LTS
# Designed for Idempotent Execution

set -e

echo "[*] Initiating FastPOS Bare-Metal Provisioning on Ubuntu 24.04..."

# 1. Update and Install Prerequisites
echo "[*] Updating System Packages..."
apt-get update -y
apt-get upgrade -y
apt-get install -y curl wget git ufw apt-transport-https ca-certificates software-properties-common jq

# 2. Configure UFW Firewall Strictly (Zero-Trust Edge)
echo "[*] Configuring UFW Firewall..."
ufw default deny incoming
ufw default allow outgoing
ufw allow 22/tcp  # SSH
ufw allow 80/tcp  # HTTP
ufw allow 443/tcp # HTTPS
echo "y" | ufw enable

# 3. Install Docker & Docker Compose (Idempotent)
if ! command -v docker &> /dev/null; then
    echo "[*] Installing Docker Engine..."
    curl -fsSL https://get.docker.com -o get-docker.sh
    sh get-docker.sh
    systemctl enable docker
    systemctl start docker
    # Add current user to docker group if running as non-root
    if [ "$EUID" -ne 0 ]; then
        usermod -aG docker $USER
    fi
    echo "[*] Docker installed successfully."
else
    echo "[*] Docker is already installed."
fi

# 4. Install Nginx (Edge Proxy)
if ! command -v nginx &> /dev/null; then
    echo "[*] Installing Nginx..."
    apt-get install -y nginx
    systemctl enable nginx
    systemctl start nginx
    echo "[*] Nginx installed successfully."
else
    echo "[*] Nginx is already installed."
fi

# 5. Prepare Application Directories
echo "[*] Preparing Application Directories..."
mkdir -p /opt/fastpos
chown -R $USER:$USER /opt/fastpos

echo "[*] Provisioning Complete. Server is secured and ready for FastPOS Zero-Downtime Cloud Deployment."
