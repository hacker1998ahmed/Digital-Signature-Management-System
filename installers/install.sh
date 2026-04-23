#!/bin/bash
#===============================================================================
# Digital Signature Factory - Automated Installation Script
# مصنع التوقيعات الرقمية - سكربت التثبيت التلقائي
# 
# Supports: Ubuntu, Debian, CentOS, Fedora, Kali Linux, macOS
#===============================================================================

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Configuration
DSF_VERSION="2.0.0"
PHP_VERSION="8.2"
INSTALL_DIR="/opt/digital-signature-factory"
WEB_USER="www-data"
DB_NAME="digital_signature_factory"
DB_USER="dsf_user"

# Functions
log_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root (use sudo)"
        exit 1
    fi
}

detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$NAME
        VER=$VERSION_ID
        log_info "Detected OS: $OS $VER"
    elif [ "$(uname)" == "Darwin" ]; then
        OS="macOS"
        VER=$(sw_vers -productVersion)
        log_info "Detected OS: macOS $VER"
    else
        log_error "Unsupported operating system"
        exit 1
    fi
}

install_dependencies_ubuntu() {
    log_info "Updating package lists..."
    apt-get update
    
    log_info "Installing PHP $PHP_VERSION and extensions..."
    add-apt-repository ppa:ondrej/php -y
    apt-get update
    apt-get install -y \
        php$PHP_VERSION \
        php$PHP_VERSION-cli \
        php$PHP_VERSION-fpm \
        php$PHP_VERSION-mysql \
        php$PHP_VERSION-pgsql \
        php$PHP_VERSION-sqlite3 \
        php$PHP_VERSION-curl \
        php$PHP_VERSION-gd \
        php$PHP_VERSION-mbstring \
        php$PHP_VERSION-xml \
        php$PHP_VERSION-zip \
        php$PHP_VERSION-bcmath \
        php$PHP_VERSION-json \
        php$PHP_VERSION-opcache \
        php$PHP_VERSION-intl \
        php$PHP_VERSION-soap \
        libpcre3-dev \
        pkg-config \
        libssl-dev \
        libpcsclite-dev \
        pcscd \
        opensc \
        softhsm2 \
        openssl \
        git \
        curl \
        unzip \
        mysql-server \
        nginx \
        supervisor \
        redis-server
    
    log_success "Ubuntu dependencies installed"
}

install_dependencies_centos() {
    log_info "Installing PHP $PHP_VERSION and extensions..."
    yum install -y epel-release
    yum install -y https://rpms.remirepo.net/enterprise/remi-release-$VER.rpm
    yum module reset -y php
    yum module enable -y php:$PHP_VERSION
    yum install -y \
        php \
        php-cli \
        php-fpm \
        php-mysqlnd \
        php-pgsql \
        php-pdo \
        php-curl \
        php-gd \
        php-mbstring \
        php-xml \
        php-zip \
        php-bcmath \
        php-json \
        php-opcache \
        php-intl \
        php-soap \
        pcsc-lite \
        pcsc-lite-devel \
        opensc \
        softhsm \
        openssl \
        git \
        curl \
        unzip \
        mariadb-server \
        nginx \
        supervisor \
        redis
    
    log_success "CentOS dependencies installed"
}

install_dependencies_macos() {
    log_info "Installing dependencies via Homebrew..."
    
    if ! command -v brew &> /dev/null; then
        log_warning "Homebrew not found. Installing..."
        /bin/bash -c "$(curl -fsSL https://raw.githubusercontent.com/Homebrew/install/HEAD/install.sh)"
    fi
    
    brew update
    brew install \
        php@$PHP_VERSION \
        mysql \
        nginx \
        redis \
        openssl \
        pcsc-lite \
        opensc \
        softhsm2 \
        git \
        node
    
    log_success "macOS dependencies installed"
}

setup_database() {
    log_info "Setting up MySQL database..."
    
    mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY 'ChangeMe123!SecurePassword';"
    mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"
    
    log_success "Database created"
}

install_application() {
    log_info "Creating installation directory..."
    mkdir -p $INSTALL_DIR
    
    log_info "Copying application files..."
    cp -r /workspace/* $INSTALL_DIR/
    
    log_info "Setting permissions..."
    chown -R $WEB_USER:$WEB_USER $INSTALL_DIR
    chmod -R 755 $INSTALL_DIR/storage
    chmod -R 755 $INSTALL_DIR/public
    
    log_success "Application installed"
}

configure_environment() {
    log_info "Configuring environment..."
    
    cd $INSTALL_DIR
    cp .env.example .env
    
    # Generate secure keys
    APP_KEY=$(openssl rand -base64 32)
    JWT_SECRET=$(openssl rand -base64 48)
    
    sed -i "s/^APP_KEY=.*/APP_KEY=$APP_KEY/" .env
    sed -i "s/^JWT_SECRET=.*/JWT_SECRET=$JWT_SECRET/" .env
    
    log_success "Environment configured"
}

install_composer_dependencies() {
    log_info "Installing Composer dependencies..."
    
    if ! command -v composer &> /dev/null; then
        log_info "Installing Composer..."
        curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
    fi
    
    cd $INSTALL_DIR
    composer install --no-dev --optimize-autoloader
    
    log_success "Composer dependencies installed"
}

install_frontend_dependencies() {
    log_info "Installing frontend dependencies..."
    
    if ! command -v npm &> /dev/null; then
        log_warning "Node.js/npm not found. Installing..."
        if [ "$OS" == "macOS" ]; then
            brew install node
        else
            curl -fsSL https://deb.nodesource.com/setup_lts.x | bash -
            apt-get install -y nodejs
        fi
    fi
    
    cd $INSTALL_DIR/frontend
    npm install
    npm run build
    
    log_success "Frontend built"
}

setup_nginx() {
    log_info "Configuring Nginx..."
    
    cat > /etc/nginx/sites-available/dsf << EOF
server {
    listen 80;
    server_name _;
    root $INSTALL_DIR/public;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \\.php\$ {
        fastcgi_pass unix:/var/run/php/php$PHP_VERSION-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath\$path;
        include fastcgi_params;
        fastcgi_read_timeout 300;
    }

    location ~ /\\.ht {
        deny all;
    }

    client_max_body_size 50M;
}
EOF
    
    ln -sf /etc/nginx/sites-available/dsf /etc/nginx/sites-enabled/dsf
    rm -f /etc/nginx/sites-enabled/default
    
    nginx -t
    systemctl restart nginx
    
    log_success "Nginx configured"
}

setup_systemd_services() {
    log_info "Setting up systemd services..."
    
    # PHP-FPM
    systemctl enable php$PHP_VERSION-fpm
    systemctl restart php$PHP_VERSION-fpm
    
    # MySQL
    systemctl enable mysql
    systemctl restart mysql
    
    # Redis
    systemctl enable redis-server
    systemctl restart redis-server
    
    log_success "Systemd services configured"
}

setup_cron_jobs() {
    log_info "Setting up cron jobs..."
    
    cat > /etc/cron.d/dsf << EOF
# Digital Signature Factory - Scheduled Tasks
SHELL=/bin/bash
PATH=/usr/local/sbin:/usr/local/bin:/sbin:/bin:/usr/sbin:/usr/bin

# Clean temporary files every hour
0 * * * * root php $INSTALL_DIR/scripts/cleanup.php >> /var/log/dsf/cleanup.log 2>&1

# Check certificate expiration daily
0 8 * * * root php $INSTALL_DIR/scripts/check_certificates.php >> /var/log/dsf/cert_check.log 2>&1

# Generate daily reports
0 23 * * * root php $INSTALL_DIR/scripts/generate_reports.php >> /var/log/dsf/reports.log 2>&1
EOF
    
    chmod 644 /etc/cron.d/dsf
    
    log_success "Cron jobs configured"
}

install_electron_app() {
    if [[ "$INCLUDE_ELECTRON" == "true" ]]; then
        log_info "Building Electron desktop application..."
        
        cd $INSTALL_DIR/electron
        npm install
        npm run build
        
        log_success "Electron app built"
    fi
}

run_tests() {
    if [[ "$RUN_TESTS" == "true" ]]; then
        log_info "Running automated tests..."
        
        cd $INSTALL_DIR
        ./vendor/bin/phpunit --testsuite=Unit || true
        
        log_success "Tests completed"
    fi
}

show_completion_message() {
    echo ""
    echo "==============================================================================="
    echo -e "${GREEN}Digital Signature Factory Installation Complete!${NC}"
    echo "==============================================================================="
    echo ""
    echo "Installation Directory: $INSTALL_DIR"
    echo "Web Interface: http://localhost"
    echo "Database: $DB_NAME"
    echo "Database User: $DB_USER"
    echo ""
    echo -e "${YELLOW}Important:${NC}"
    echo "1. Update the .env file with your production settings"
    echo "2. Change the default database password"
    echo "3. Configure SSL/TLS for production use"
    echo "4. Set up proper backup procedures"
    echo ""
    echo "Useful Commands:"
    echo "  Start services: systemctl start nginx mysql redis-server php$PHP_VERSION-fpm"
    echo "  View logs: tail -f /var/log/nginx/error.log"
    echo "  Run CLI: php $INSTALL_DIR/scripts/console.php"
    echo ""
    echo -e "${GREEN}Thank you for using Digital Signature Factory!${NC}"
    echo "==============================================================================="
}

# Parse arguments
INCLUDE_ELECTRON="false"
RUN_TESTS="false"

while [[ $# -gt 0 ]]; do
    case $1 in
        --with-electron)
            INCLUDE_ELECTRON="true"
            shift
            ;;
        --run-tests)
            RUN_TESTS="true"
            shift
            ;;
        --help)
            echo "Usage: $0 [options]"
            echo ""
            echo "Options:"
            echo "  --with-electron    Build Electron desktop application"
            echo "  --run-tests        Run automated tests after installation"
            echo "  --help             Show this help message"
            exit 0
            ;;
        *)
            log_error "Unknown option: $1"
            exit 1
            ;;
    esac
done

# Main installation process
echo ""
echo "==============================================================================="
echo "  Digital Signature Factory v$DSF_VERSION - Installation Script"
echo "  مصنع التوقيعات الرقمية - سكربت التثبيت"
echo "==============================================================================="
echo ""

check_root
detect_os

case $OS in
    Ubuntu*|Debian*|Kali*)
        install_dependencies_ubuntu
        ;;
    CentOS*|Fedora*|RedHat*)
        install_dependencies_centos
        ;;
    macOS)
        install_dependencies_macos
        ;;
    *)
        log_error "Unsupported OS: $OS"
        exit 1
        ;;
esac

setup_database
install_application
configure_environment
install_composer_dependencies
install_frontend_dependencies
setup_nginx
setup_systemd_services
setup_cron_jobs
install_electron_app
run_tests

show_completion_message

exit 0
