#!/bin/bash
# ============================================================
#  ECOM APPLICATION SETUP SCRIPT
#  Installation and initialization for the e-commerce platform
# ============================================================

echo "🚀 E-Commerce Platform Setup"
echo "=============================="
echo ""

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Check if .env file exists
if [ ! -f .env ]; then
    echo -e "${YELLOW}⚠️  .env file not found. Creating from .env.example...${NC}"
    if [ -f .env.example ]; then
        cp .env.example .env
        echo -e "${GREEN}✓ .env created${NC}"
        echo -e "${YELLOW}  Please edit .env with your database credentials and settings${NC}"
    else
        echo -e "${RED}✗ .env.example not found${NC}"
        exit 1
    fi
fi

# Create necessary directories
echo ""
echo "📁 Creating directories..."
mkdir -p logs uploads/products uploads/temp cache config
mkdir -p admin/assets/js admin/assets/css
chmod 755 logs uploads cache

echo -e "${GREEN}✓ Directories created${NC}"

# Check if schema.sql exists
if [ ! -f schema.sql ]; then
    echo -e "${RED}✗ schema.sql not found in project root${NC}"
    exit 1
fi

echo ""
echo -e "${YELLOW}📊 Database Setup${NC}"
echo "Note: You'll need to import schema.sql manually using your database client:"
echo ""
echo "  Option 1 - Using MySQL CLI:"
echo "    mysql -u root -p your_database_name < schema.sql"
echo ""
echo "  Option 2 - Using phpMyAdmin:"
echo "    1. Open phpMyAdmin"
echo "    2. Select your database"
echo "    3. Go to Import tab"
echo "    4. Select schema.sql file"
echo "    5. Click Import"
echo ""

# Check PHP version
echo ""
echo "🔍 Checking PHP version..."
php_version=$(php -r 'echo PHP_VERSION;')
if php -v | grep -q "PHP 7.4\|PHP 8"; then
    echo -e "${GREEN}✓ PHP version: $php_version${NC}"
else
    echo -e "${YELLOW}⚠️  PHP version: $php_version (recommend PHP 7.4+)${NC}"
fi

# Check required PHP extensions
echo ""
echo "🔍 Checking PHP extensions..."
php -m | grep -q pdo_mysql && echo -e "${GREEN}✓ PDO MySQL${NC}" || echo -e "${RED}✗ PDO MySQL missing${NC}"
php -m | grep -q curl && echo -e "${GREEN}✓ cURL${NC}" || echo -e "${RED}✗ cURL missing${NC}"
php -m | grep -q json && echo -e "${GREEN}✓ JSON${NC}" || echo -e "${RED}✗ JSON missing${NC}"
php -m | grep -q mbstring && echo -e "${GREEN}✓ mbstring${NC}" || echo -e "${RED}✗ mbstring missing${NC}"

echo ""
echo -e "${GREEN}========================================${NC}"
echo -e "${GREEN}✓ Setup complete!${NC}"
echo -e "${GREEN}========================================${NC}"
echo ""
echo "Next steps:"
echo "1. Update .env with your database credentials"
echo "2. Import schema.sql into your database"
echo "3. Configure email settings (SMTP)"
echo "4. Configure payment gateway (Razorpay)"
echo "5. Start your web server and visit the site"
echo ""
echo "📚 For more details, see DEPLOYMENT_GUIDE.md"
