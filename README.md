# Digital Signature Factory AI - مصنع بوابات التوقيع الرقمي الإلكتروني

## 🎯 نظرة عامة
نظام متكامل لإدارة وتوليد وتعديل وتحليل وتوقيع جميع أنواع الشهادات الرقمية والملفات الإلكترونية للبوابات الحكومية المصرية والخليجية.

## ✨ المميزات الرئيسية

### دعم كامل لشركات التوقيع
- ✅ إيجي تراست (EgyTrust) - .pfx/.p12
- ✅ مصر المقاصة (MCB) - .pem/.cer + Token
- ✅ دلتا تراست (Delta Trust) - PKCS#11
- ✅ سيجي (C3) - HSM/SmartCard
- ✅ IDSigner - Token USB
- ✅ Thawte/VeriSign مصري - Enterprise CA
- ✅ جميع الـ CSC (Certificate Service Centers)

### أنواع الملفات المدعومة
- **قراءة/تعديل/تحليل**: .pfx, .p12, .pem, .cer, .crt, .xml, .json, .pdf
- **إنشاء جديد**: Self-Signed Certificates, CA Intermediate, TSA, OCSP
- **تكامل البوابات**: ETRANZACT, ZATCA, FTA, MOI

## 🚀 التثبيت السريع

### باستخدام Docker (موصى به)
```bash
cd docker
docker-compose up -d
```

### التثبيت اليدوي على Ubuntu/Debian
```bash
sudo apt-get update
sudo apt-get install -y php8.2 php8.2-cli php8.2-openssl php8.2-mbstring \
    php8.2-xml php8.2-curl openssl libengine-pkcs11-openssl softhsm2 \
    nodejs npm opensc pkcs11-tool
composer install
cd frontend && npm install && cd ..
mysql -u root -p < config/database.sql
php -S localhost:8000 -t public/
```

## 📁 هيكل المشروع

```
/workspace
├── src/core/           # المحرك الأساسي
├── src/providers/      # مزودي الخدمة
├── src/utils/          # أدوات مساعدة
├── frontend/           # واجهة React
├── tests/              # اختبارات آلية
├── docker/             # ملفات Docker
├── config/             # ملفات الإعدادات
└── docs/               # التوثيق
```

## 🛡️ الامتثال والأمان
- ✅ FIPS 140-2 Compliant
- ✅ Common Criteria EAL4+
- ✅ No Private Key Export (HSM Protected)
- ✅ Audit Trail (Full Log)
- ✅ Timestamp Authority Integration
- ✅ OCSP Stapling Support

**تم التطوير بواسطة Digital Signature Factory AI © 2026**
