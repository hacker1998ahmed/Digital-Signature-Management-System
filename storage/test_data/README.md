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
