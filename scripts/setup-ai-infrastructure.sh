#!/bin/bash
# CoreHealth AI Infrastructure Setup Script (Non-Docker)
# This script sets up Qdrant Vector Database and Ollama for local LLM inference.

set -e

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}======================================================${NC}"
echo -e "${GREEN} CoreHealth AI Infrastructure Setup (Ubuntu/Linux Mint) ${NC}"
echo -e "${GREEN}======================================================${NC}"

if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}Please run this script as root (use sudo).${NC}"
  exit 1
fi

echo -e "\n${YELLOW}Step 1: Installing Qdrant (Non-Docker)...${NC}"

QDRANT_VERSION="v1.13.0"
QDRANT_BIN_URL="https://github.com/qdrant/qdrant/releases/download/${QDRANT_VERSION}/qdrant-x86_64-unknown-linux-gnu.tar.gz"

if systemctl is-active --quiet qdrant; then
    echo -e "${GREEN}Qdrant is already installed and running.${NC}"
else
    # Create directories
    mkdir -p /opt/qdrant/storage
    mkdir -p /opt/qdrant/snapshots
    
    # Download and extract binary
    echo "Downloading Qdrant ${QDRANT_VERSION}..."
    wget -qO /tmp/qdrant.tar.gz "$QDRANT_BIN_URL"
    tar -xzf /tmp/qdrant.tar.gz -C /tmp/
    mv /tmp/qdrant /opt/qdrant/qdrant
    chmod +x /opt/qdrant/qdrant
    
    # Create basic config
    cat <<EOF > /opt/qdrant/config.yaml
storage:
  storage_path: /opt/qdrant/storage
  snapshots_path: /opt/qdrant/snapshots
service:
  host: 127.0.0.1
  http_port: 6333
  grpc_port: 6334
EOF

    # Setup Systemd Service
    cat <<EOF > /etc/systemd/system/qdrant.service
[Unit]
Description=Qdrant Vector Database
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=/opt/qdrant
ExecStart=/opt/qdrant/qdrant --config-path /opt/qdrant/config.yaml
Restart=on-failure
LimitNOFILE=65536

[Install]
WantedBy=multi-user.target
EOF

    systemctl daemon-reload
    systemctl enable qdrant
    systemctl start qdrant
    echo -e "${GREEN}Qdrant installed and started successfully on port 6333.${NC}"
fi


echo -e "\n${YELLOW}Step 2: Installing Ollama (Local LLM Inference)...${NC}"
if command -v ollama &> /dev/null; then
    echo -e "${GREEN}Ollama is already installed.${NC}"
else
    curl -fsSL https://ollama.com/install.sh | sh
    systemctl enable ollama
    systemctl start ollama
    echo -e "${GREEN}Ollama installed successfully.${NC}"
fi

echo -e "\n${YELLOW}Step 3: Updating Hospital Configuration (Database)...${NC}"
# Assuming this script is run from the Laravel root or we know where it is
LARAVEL_DIR=$(pwd)
if [ ! -f "$LARAVEL_DIR/artisan" ]; then
    # Try one level up if in a script dir
    LARAVEL_DIR=$(dirname "$LARAVEL_DIR")
fi

if [ -f "$LARAVEL_DIR/artisan" ]; then
    echo "Running artisan commands to initialize LLM config in application_status..."
    sudo -u www-data php "$LARAVEL_DIR/artisan" migrate --force
    # The migration we created already seeds the default config.
    # We can also clear cache just in case.
    sudo -u www-data php "$LARAVEL_DIR/artisan" cache:clear
    echo -e "${GREEN}Hospital configuration populated.${NC}"
else
    echo -e "${YELLOW}Could not find Laravel artisan file. Please run 'php artisan migrate' manually.${NC}"
fi

echo -e "\n${GREEN}======================================================${NC}"
echo -e "${GREEN} Setup Complete! ${NC}"
echo -e "You can now configure API keys in the CoreHealth Admin Dashboard."
echo -e "To use Ollama models, run: 'ollama pull llama3' or similar."
echo -e "${GREEN}======================================================${NC}"
