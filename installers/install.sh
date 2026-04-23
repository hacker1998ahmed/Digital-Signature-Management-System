#!/bin/bash
# Digital Signature Factory - Installation Script for Linux/Mac
# Supports: Ubuntu, Debian, Kali, Fedora, CentOS, Mac OS
# Version: 2.0.0

set -e

echo "========================================"
echo "Digital Signature Factory Installer"
echo "نظام تثبيت مصنع التوقيع الرقمي"
echo "========================================"
echo ""

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Detect OS
detect_os() {
    if [[ "$OSTYPE" == "darwin"* ]]; then
        OS="macos"
    elif [[ -f /etc/os-release ]]; then
        . /etc/os-release
        OS=$ID
    else
        echo -e "${RED}Unsupported operating system${NC}"
        exit 1
    fi
    
    echo "Detected OS: $OS"
}

# Check if running as root (required for some operations)
check_root() {
    if [[ $EUID -ne 0 ]] && [[ "$INSTALL_SYSTEM_WIDE" == "true" ]]; then
        echo -e "${YELLOW}Some operations require root privileges${NC}"
        echo "Run with sudo for system-wide installation"
    fi
}

# Install dependencies for different OS
install_dependencies() {
    echo -e "${GREEN}Installing dependencies...${NC}"
    
    case $OS in
        ubuntu|debian|kali)
            apt-get update
            apt-get install -y \
                php8.2-cli php8.2-mysql php8.2-xml php8.2-curl php8.2-gd php8.2-zip \
                php8.2-intl php8.2-mbstring php8.2-soap php8.2-bcmath \
                openssl libengine-pkcs11-openssl softhsm2 pcscd pcsc-tools \
                libpcsclite-dev libccid libusb-1.0-0-dev \
                mysql-server mysql-client \
                nodejs npm \
                git curl wget unzip \
                opensc libopensc-pkcs11
            ;;
            
        fedora)
            dnf install -y \
                php php-mysqlnd php-xml php-curl php-gd php-zip php-intl \
                openssl softhsm pcsc-lite pcsc-lite-ccid \
                mariadb-server mariadb \
                nodejs npm \
                git curl wget unzip \
                opensc
            ;;
            
        centos|rhel)
            yum install -y epel-release
            yum install -y \
                php php-mysqlnd php-xml php-curl php-gd php-zip php-intl \
                openssl softhsm pcsc-lite pcsc-lite-ccid \
                mariadb-server mariadb \
                nodejs npm \
                git curl wget unzip \
                opensc
            ;;
            
        macos)
            if ! command -v brew &> /dev/null; then
                echo -e "${YELLOW}Homebrew not found. Installing...${NC}"
                /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
            fi
            
            brew install \
                php@8.2 openssl softhsm pcsc-lite \
                mysql node npm \
                git curl wget
            ;;
            
        *)
            echo -e "${RED}Unsupported OS: $OS${NC}"
            exit 1
            ;;
    esac
}

# Setup PHP environment
setup_php() {
    echo -e "${GREEN}Setting up PHP environment...${NC}"
    
    # Enable required PHP extensions
    if [[ -d /etc/php/8.2/mods-available ]]; then
        phpenmod -s cli openssl
        phpenmod -s cli intl
        phpenmod -s cli mbstring
    fi
    
    # Verify PHP version
    php_version=$(php -v | head -n1)
    echo "PHP Version: $php_version"
}

# Setup MySQL database
setup_database() {
    echo -e "${GREEN}Setting up database...${NC}"
    
    DB_NAME="signature_factory"
    DB_USER="dsf_user"
    DB_PASS=$(openssl rand -base64 16)
    
    case $OS in
        ubuntu|debian|kali)
            systemctl start mysql
            systemctl enable mysql
            ;;
        fedora|centos|rhel)
            systemctl start mariadb
            systemctl enable mariadb
            ;;
        macos)
            brew services start mysql
            ;;
    esac
    
    # Create database and user
    mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOF
    
    # Save credentials to config file
    cat > config/db_credentials.txt <<EOF
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASS=$DB_PASS
DB_HOST=localhost
EOF
    
    chmod 600 config/db_credentials.txt
    
    echo -e "${GREEN}Database created successfully${NC}"
    echo "Credentials saved to config/db_credentials.txt"
}

# Install Composer
install_composer() {
    echo -e "${GREEN}Installing Composer...${NC}"
    
    if ! command -v composer &> /dev/null; then
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    else
        echo "Composer already installed"
    fi
}

# Install PHP dependencies
install_php_deps() {
    echo -e "${GREEN}Installing PHP dependencies...${NC}"
    
    composer install --no-dev --optimize-autoloader
}

# Install Node.js dependencies
install_node_deps() {
    echo -e "${GREEN}Installing Node.js dependencies...${NC}"
    
    # Frontend
    cd frontend
    npm install
    npm run build
    cd ..
    
    # Electron (optional)
    if [[ "$INSTALL_ELECTRON" == "true" ]]; then
        cd electron
        npm install
        cd ..
    fi
}

# Configure SoftHSM (for PKCS#11 testing)
configure_softhsm() {
    echo -e "${GREEN}Configuring SoftHSM...${NC}"
    
    mkdir -p /var/lib/softhsm/tokens
    chmod 755 /var/lib/softhsm/tokens
    
    # Initialize SoftHSM configuration
    if [[ -f /etc/softhsm/softhsm2.conf ]]; then
        sed -i 's|directories.tokendir = .*|directories.tokendir = /var/lib/softhsm/tokens|' /etc/softhsm/softhsm2.conf
    fi
    
    # Start pcscd service
    if command -v systemctl &> /dev/null; then
        systemctl start pcscd
        systemctl enable pcscd
    fi
    
    echo "SoftHSM configured successfully"
}

# Setup systemd services (Linux only)
setup_services() {
    if [[ "$OS" != "macos" ]] && command -v systemctl &> /dev/null; then
        echo -e "${GREEN}Setting up systemd services...${NC}"
        
        # API service
        cat > /etc/systemd/system/dsf-api.service <<EOF
[Unit]
Description=Digital Signature Factory API
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=$(pwd)
ExecStart=/usr/bin/php -S localhost:8000 -t public/
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

        # Job scheduler service
        cat > /etc/systemd/system/dsf-scheduler.service <<EOF
[Unit]
Description=Digital Signature Factory Job Scheduler
After=network.target mysql.service dsf-api.service

[Service]
Type=simple
User=www-data
WorkingDirectory=$(pwd)
ExecStart=/usr/bin/php src/jobs/scheduler.php
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF

        # Enable services
        systemctl daemon-reload
        systemctl enable dsf-api
        systemctl enable dsf-scheduler
        
        echo "Systemd services configured"
    fi
}

# Create necessary directories
create_directories() {
    echo -e "${GREEN}Creating directories...${NC}"
    
    mkdir -p storage/certificates
    mkdir -p storage/temp
    mkdir -p storage/logs
    mkdir -p storage/backups
    mkdir -p vendor
    
    chmod -R 755 storage
    chmod -R 755 vendor
}

# Generate configuration files
generate_config() {
    echo -e "${GREEN}Generating configuration...${NC}"
    
    # Copy example config if exists
    if [[ -f config/signature_config.example.php ]]; then
        cp config/signature_config.example.php config/signature_config.php
    fi
    
    # Generate security keys
    JWT_SECRET=$(openssl rand -base64 32)
    
    # Update config with generated values
    if [[ -f config/signature_config.php ]]; then
        sed -i "s/'jwt_secret' => '.*'/'jwt_secret' => '$JWT_SECRET'/" config/signature_config.php
    fi
    
    echo "Configuration generated"
}

# Run database migrations
run_migrations() {
    echo -e "${GREEN}Running database migrations...${NC}"
    
    if [[ -f config/database.sql ]]; then
        # Read DB credentials
        source config/db_credentials.txt
        
        mysql -u $DB_USER -p$DB_PASS $DB_NAME < config/database.sql
        echo "Database migrations completed"
    fi
}

# Main installation function
main() {
    INSTALL_SYSTEM_WIDE=${INSTALL_SYSTEM_WIDE:-false}
    INSTALL_ELECTRON=${INSTALL_ELECTRON:-false}
    
    detect_os
    check_root
    
    echo ""
    echo "Starting installation..."
    echo ""
    
    create_directories
    install_dependencies
    setup_php
    install_composer
    setup_database
    install_php_deps
    install_node_deps
    configure_softhsm
    generate_config
    run_migrations
    setup_services
    
    echo ""
    echo -e "${GREEN}========================================${NC}"
    echo -e "${GREEN}Installation completed successfully!${NC}"
    echo -e "${GREEN}========================================${NC}"
    echo ""
    echo "Next steps:"
    echo "1. Review config/signature_config.php"
    echo "2. Review config/db_credentials.txt (keep secure!)"
    echo "3. Start the API: php -S localhost:8000 -t public/"
    echo "4. Open http://localhost:8000 in your browser"
    echo ""
    echo "For Electron desktop app:"
    echo "   cd electron && npm run dev"
    echo ""
    echo -e "${YELLOW}Important: Keep db_credentials.txt secure!${NC}"
    echo ""
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --system-wide)
            INSTALL_SYSTEM_WIDE=true
            shift
            ;;
        --with-electron)
            INSTALL_ELECTRON=true
            shift
            ;;
        --help)
            echo "Usage: $0 [options]"
            echo ""
            echo "Options:"
            echo "  --system-wide    Install system-wide (requires sudo)"
            echo "  --with-electron  Install Electron desktop app"
            echo "  --help           Show this help message"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Run main installation
main
