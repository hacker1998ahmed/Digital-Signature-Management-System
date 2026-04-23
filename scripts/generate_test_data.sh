#!/bin/bash

# Digital Signature Factory - Test Data Generator
# مصنع التوقيعات الرقمية - مولد بيانات الاختبار
# This script generates test certificates and sample data for development/testing

set -e

echo "=========================================="
echo "Digital Signature Factory - Test Data Generator"
echo "مصنع التوقيعات الرقمية - مولد بيانات الاختبار"
echo "=========================================="

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Directories
TEST_DIR="/workspace/storage/test_data"
CERTS_DIR="$TEST_DIR/certificates"
DOCUMENTS_DIR="$TEST_DIR/documents"

# Create directories
echo -e "${YELLOW}Creating test directories...${NC}"
mkdir -p "$CERTS_DIR"
mkdir -p "$DOCUMENTS_DIR"

# Generate Self-Signed Test Certificate
echo -e "${YELLOW}Generating self-signed test certificate...${NC}"

# Create private key
openssl genrsa -out "$CERTS_DIR/test_private.key" 2048 2>/dev/null

# Create certificate signing request
openssl req -new -key "$CERTS_DIR/test_private.key" \
    -out "$CERTS_DIR/test_request.csr" \
    -subj "/C=EG/ST=Cairo/L=Cairo/O=Digital Signature Factory/OU=Test/CN=test.dsf.local" 2>/dev/null

# Create self-signed certificate
openssl x509 -req -days 365 \
    -in "$CERTS_DIR/test_request.csr" \
    -signkey "$CERTS_DIR/test_private.key" \
    -out "$CERTS_DIR/test_certificate.crt" 2>/dev/null

# Create PKCS#12 file
openssl pkcs12 -export \
    -out "$CERTS_DIR/test_certificate.pfx" \
    -inkey "$CERTS_DIR/test_private.key" \
    -in "$CERTS_DIR/test_certificate.crt" \
    -passout pass:test123 2>/dev/null

echo -e "${GREEN}✓ Test certificate generated successfully${NC}"

# Generate Sample XML Document (ETRANZACT Format)
echo -e "${YELLOW}Generating sample ETRANZACT XML invoice...${NC}"
cat > "$DOCUMENTS_DIR/sample_invoice.xml" << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
    <cbc:ID>INV-2024-001</cbc:ID>
    <cbc:IssueDate>2024-01-15</cbc:IssueDate>
    <cbc:InvoiceTypeCode>388</cbc:InvoiceTypeCode>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cbc:Name>Test Company Ltd.</cbc:Name>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>123-456-789</cbc:CompanyID>
            </cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cbc:Name>Customer Corp.</cbc:Name>
        </cac:Party>
    </cac:AccountingCustomerParty>
    <cac:LegalMonetaryTotal>
        <cbc:TaxExclusiveAmount currencyID="EGP">1000.00</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="EGP">1140.00</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="EGP">1140.00</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
</Invoice>
EOF
echo -e "${GREEN}✓ Sample XML invoice generated${NC}"

# Generate Sample ZATCA XML (Saudi Arabia)
echo -e "${YELLOW}Generating sample ZATCA XML invoice...${NC}"
cat > "$DOCUMENTS_DIR/sample_zatca_invoice.xml" << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">
    <cbc:ID>TST-2024-001</cbc:ID>
    <cbc:UUID>123e4567-e89b-12d3-a456-426614174000</cbc:UUID>
    <cbc:IssueDate>2024-01-15</cbc:IssueDate>
    <cbc:IssueTime>10:30:00</cbc:IssueTime>
    <cbc:InvoiceTypeCode>388</cbc:InvoiceTypeCode>
    <cac:AccountingSupplierParty>
        <cac:Party>
            <cbc:Name>Test Saudi Company</cbc:Name>
            <cac:PartyTaxScheme>
                <cbc:CompanyID>300000000000003</cbc:CompanyID>
            </cac:PartyTaxScheme>
        </cac:Party>
    </cac:AccountingSupplierParty>
    <cac:AccountingCustomerParty>
        <cac:Party>
            <cbc:Name>Customer LLC</cbc:Name>
        </cac:Party>
    </cac:AccountingCustomerParty>
    <cac:LegalMonetaryTotal>
        <cbc:TaxExclusiveAmount currencyID="SAR">1000.00</cbc:TaxExclusiveAmount>
        <cbc:TaxInclusiveAmount currencyID="SAR">1150.00</cbc:TaxInclusiveAmount>
        <cbc:PayableAmount currencyID="SAR">1150.00</cbc:PayableAmount>
    </cac:LegalMonetaryTotal>
</Invoice>
EOF
echo -e "${GREEN}✓ Sample ZATCA XML invoice generated${NC}"

# Generate Sample JSON Document
echo -e "${YELLOW}Generating sample JSON document...${NC}"
cat > "$DOCUMENTS_DIR/sample_data.json" << 'EOF'
{
    "documentType": "invoice",
    "documentId": "JSON-2024-001",
    "issueDate": "2024-01-15T10:30:00Z",
    "supplier": {
        "name": "Test Company Ltd.",
        "taxId": "123-456-789",
        "address": "Cairo, Egypt"
    },
    "customer": {
        "name": "Customer Corp.",
        "taxId": "987-654-321"
    },
    "items": [
        {
            "description": "Product A",
            "quantity": 10,
            "unitPrice": 100.00,
            "taxRate": 0.14,
            "totalAmount": 1140.00
        }
    ],
    "totals": {
        "subtotal": 1000.00,
        "tax": 140.00,
        "total": 1140.00,
        "currency": "EGP"
    }
}
EOF
echo -e "${GREEN}✓ Sample JSON document generated${NC}"

# Generate Sample PDF (Simple Text PDF)
echo -e "${YELLOW}Generating sample PDF document...${NC}"
cat > "$DOCUMENTS_DIR/sample_document.txt" << 'EOF'
DIGITAL SIGNATURE FACTORY
Sample Document for Testing

Document ID: PDF-2024-001
Issue Date: January 15, 2024

This is a sample document for testing the digital signature functionality.

Supplier: Test Company Ltd.
Customer: Customer Corp.
Amount: EGP 1,140.00

---
This document requires digital signature for validation.
EOF

# Convert to PDF if pdftk or wkhtmltopdf is available
if command -v wkhtmltopdf &> /dev/null; then
    wkhtmltopdf "$DOCUMENTS_DIR/sample_document.txt" "$DOCUMENTS_DIR/sample_document.pdf" 2>/dev/null
    echo -e "${GREEN}✓ Sample PDF document generated${NC}"
else
    # Create a simple PDF manually
    cat > "$DOCUMENTS_DIR/sample_document.pdf" << 'PDFEOF'
%PDF-1.4
1 0 obj
<< /Type /Catalog /Pages 2 0 R >>
endobj
2 0 obj
<< /Type /Pages /Kids [3 0 R] /Count 1 >>
endobj
3 0 obj
<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>
endobj
4 0 obj
<< /Length 200 >>
stream
BT
/F1 24 Tf
100 700 Td
(Digital Signature Factory) Tj
/F1 12 Tf
0 -30 Td
(Sample Document for Testing) Tj
0 -20 Td
(Document ID: PDF-2024-001) Tj
0 -20 Td
(Date: January 15, 2024) Tj
ET
endstream
endobj
5 0 obj
<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>
endobj
xref
0 6
0000000000 65535 f 
0000000009 00000 n 
0000000058 00000 n 
0000000115 00000 n 
0000000266 00000 n 
0000000517 00000 n 
trailer
<< /Size 6 /Root 1 0 R >>
startxref
594
%%EOF
PDFEOF
    echo -e "${GREEN}✓ Sample PDF document generated (basic)${NC}"
fi

# Generate Test Database Records
echo -e "${YELLOW}Generating test database SQL...${NC}"
cat > "$TEST_DIR/test_database.sql" << 'EOF'
-- Test Users
INSERT INTO users (username, email, password_hash, role, status, created_at) VALUES
('admin', 'admin@dsf.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'active', NOW()),
('manager', 'manager@dsf.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'active', NOW()),
('user', 'user@dsf.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'user', 'active', NOW()),
('auditor', 'auditor@dsf.local', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'auditor', 'active', NOW());

-- Test Tokens
INSERT INTO tokens (token_label, serial_number, provider, status, last_seen, created_at) VALUES
('EgyTrust-Token-001', 'EGY123456789', 'EgyTrust', 'connected', NOW(), NOW()),
('MCB-Token-002', 'MCB987654321', 'MCB', 'connected', NOW(), NOW()),
('DeltaTrust-Token-003', 'DLT456789123', 'DeltaTrust', 'disconnected', DATE_SUB(NOW(), INTERVAL 1 DAY), NOW());

-- Test Certificates
INSERT INTO certificates (token_id, subject_dn, issuer_dn, serial_number, valid_from, valid_to, status, created_at) VALUES
(1, 'CN=Test User,O=Test Company,C=EG', 'CN=EgyTrust CA,O=EgyTrust,C=EG', '123456789ABC', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 'active', NOW()),
(2, 'CN=Manager User,O=Test Company,C=EG', 'CN=MCB CA,O=MCB,C=EG', '987654321DEF', NOW(), DATE_ADD(NOW(), INTERVAL 1 YEAR), 'active', NOW());

-- Test Audit Logs
INSERT INTO audit_logs (user_id, action, description, ip_address, status, created_at) VALUES
(1, 'LOGIN', 'User logged in successfully', '127.0.0.1', 'success', NOW()),
(1, 'TOKEN_SCAN', 'Scanned for USB tokens', '127.0.0.1', 'success', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 'DOCUMENT_SIGN', 'Signed document INV-2024-001', '127.0.0.1', 'success', DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(3, 'CERTIFICATE_VIEW', 'Viewed certificate details', '127.0.0.1', 'success', DATE_SUB(NOW(), INTERVAL 3 HOUR));
EOF
echo -e "${GREEN}✓ Test database SQL generated${NC}"

# Create README for test data
cat > "$TEST_DIR/README.md" << 'EOF'
# Test Data Directory / مجلد بيانات الاختبار

This directory contains test data for development and testing purposes.
يحتوي هذا المجلد على بيانات اختبار لأغراض التطوير والاختبار.

## Contents / المحتويات

- `certificates/` - Test certificates (self-signed)
- `documents/` - Sample documents (XML, JSON, PDF)
- `test_database.sql` - Sample database records

## Usage / الاستخدام

### Import Test Database
```bash
mysql -u root -p dsf_db < test_database.sql
```

### Test Certificates
- `test_certificate.pfx` - PKCS#12 file (password: test123)
- `test_certificate.crt` - X.509 certificate
- `test_private.key` - Private key

### Sample Documents
- `sample_invoice.xml` - ETRANZACT format invoice
- `sample_zatca_invoice.xml` - ZATCA format invoice
- `sample_data.json` - JSON document
- `sample_document.pdf` - PDF document

## Warning / تحذير

These are test certificates and should NOT be used in production.
هذه شهادات اختبار ولا يجب استخدامها في بيئة الإنتاج.
EOF

echo ""
echo -e "${GREEN}=========================================="
echo "Test data generation completed successfully!"
echo "تم إنشاء بيانات الاختبار بنجاح!"
echo "==========================================${NC}"
echo ""
echo "Test data location: $TEST_DIR"
echo ""
echo "To import test database:"
echo "  mysql -u root -p dsf_db < $TEST_DIR/test_database.sql"
echo ""
echo "Test certificate password: test123"
echo ""
