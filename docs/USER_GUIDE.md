# Digital Signature Factory - User Guide
# مصنع التوقيعات الرقمية - دليل المستخدم

## 📋 Table of Contents / فهرس المحتويات

1. [Introduction / مقدمة](#introduction)
2. [Getting Started / البدء](#getting-started)
3. [Dashboard Overview / نظرة عامة على لوحة التحكم](#dashboard-overview)
4. [Token Management / إدارة البوابات](#token-management)
5. [Certificate Operations / عمليات الشهادات](#certificate-operations)
6. [Document Signing / توقيع المستندات](#document-signing)
7. [Bulk Signing / التوقيع الجماعي](#bulk-signing)
8. [Audit Logs / سجلات التدقيق](#audit-logs)
9. [Settings / الإعدادات](#settings)
10. [Troubleshooting / استكشاف الأخطاء](#troubleshooting)

---

## Introduction / مقدمة {#introduction}

### English
**Digital Signature Factory** is a comprehensive system for managing, generating, modifying, analyzing, and signing all types of digital certificates and electronic files for Egyptian and Gulf government portals.

**Key Features:**
- Support for all Egyptian signature companies (EgyTrust, MCB, DeltaTrust, C3)
- USB Token detection and management
- Certificate viewing and editing
- XML/JSON/PDF signing (ETRANZACT, ZATCA compliant)
- Bulk signing capabilities
- Complete audit trail
- Multi-language support (Arabic/English)

### العربية
**مصنع التوقيعات الرقمية** هو نظام شامل لإدارة وتوليد وتعديل وتحليل وتوقيع جميع أنواع الشهادات الرقمية والملفات الإلكترونية للبوابات الحكومية المصرية والخليجية.

**المميزات الرئيسية:**
- دعم جميع شركات التوقيع المصرية (إيجي تراست، مصر المقاصة، دلتا تراست، سيجي)
- اكتشاف وإدارة بطاقات USB Token
- عرض وتعديل الشهادات
- توقيع XML/JSON/PDF (متوافق مع ETRANZACT و ZATCA)
- إمكانيات التوقيع الجماعي
- سجل تدقيق كامل
- دعم متعدد اللغات (عربي/إنجليزي)

---

## Getting Started / البدء {#getting-started}

### Prerequisites / المتطلبات

**English:**
- PHP 8.2 or higher
- OpenSSL extension enabled
- PKCS#11 module (for token support)
- MySQL 8.0 or PostgreSQL 14+
- Node.js 18+ (for frontend)
- Modern web browser (Chrome, Firefox, Edge)

**العربية:**
- PHP 8.2 أو أعلى
- تفعيل إضافة OpenSSL
- وحدة PKCS#11 (لدعم البطاقات)
- MySQL 8.0 أو PostgreSQL 14+
- Node.js 18+ (للواجهة الأمامية)
- متصفح ويب حديث (Chrome, Firefox, Edge)

### Installation / التثبيت

#### Quick Start with Docker

```bash
# Clone the repository
cd /workspace/docker

# Start all services
docker-compose up -d

# Access the application
# Web Interface: http://localhost:3000
# API: http://localhost:8000
```

#### Manual Installation

```bash
# Install system dependencies
sudo ./installers/install.sh

# Configure environment
cp .env.example .env
nano .env

# Install PHP dependencies
composer install

# Install frontend dependencies
cd frontend && npm install && npm run build

# Import database
mysql -u root -p < config/database.sql

# Start the server
php -S localhost:8000 -t public/
```

---

## Dashboard Overview / نظرة عامة على لوحة التحكم {#dashboard-overview}

### English
The dashboard provides a comprehensive overview of your digital signature operations:

**Main Sections:**
1. **Statistics Panel** - View total certificates, tokens, signed documents, and recent activities
2. **Quick Actions** - Fast access to common operations (Sign Document, Scan Tokens, View Certificates)
3. **Recent Activities** - Latest signing operations and system events
4. **Token Status** - Real-time status of connected tokens

### العربية
توفر لوحة التحكم نظرة شاملة على عمليات التوقيع الرقمي الخاصة بك:

**الأقسام الرئيسية:**
1. **لوحة الإحصائيات** - عرض إجمالي الشهادات والبطاقات والمستندات الموقعة والأنشطة الحديثة
2. **الإجراءات السريعة** - وصول سريع للعمليات الشائعة (توقيع مستند، فحص البطاقات، عرض الشهادات)
3. **الأنشطة الحديثة** - أحدث عمليات التوقيع وأحداث النظام
4. **حالة البطاقات** - حالة البطاقات المتصلة في الوقت الفعلي

---

## Token Management / إدارة البطاقات {#token-management}

### English

#### Scanning for Tokens
1. Navigate to **Token Manager** from the main menu
2. Click **Scan for Tokens** button
3. The system will automatically detect all connected USB tokens
4. Detected tokens will be displayed with their information:
   - Token Label
   - Serial Number
   - Provider (EgyTrust, MCB, etc.)
   - Status (Connected/Disconnected)

#### Viewing Token Information
- Click on any detected token to view detailed information
- Information includes:
  - Manufacturer details
  - Firmware version
  - Available storage
  - List of certificates stored on the token

#### Connecting to a Token
1. Select the token from the list
2. Enter the PIN when prompted
3. Click **Connect**
4. Once connected, you can:
   - View certificates
   - Sign documents
   - Export certificate information (without private key)

### العربية

#### البحث عن البطاقات
1. انتقل إلى **مدير البطاقات** من القائمة الرئيسية
2. انقر على زر **فحص البطاقات**
3. سيكتشف النظام تلقائياً جميع بطاقات USB المتصلة
4. سيتم عرض البطاقات المكتشفة مع معلوماتها:
   - اسم البطاقة
   - الرقم التسلسلي
   - المزود (إيجي تراست، مصر المقاصة، إلخ)
   - الحالة (متصل/غير متصل)

#### عرض معلومات البطاقة
- انقر على أي بطاقة مكتشفة لعرض المعلومات التفصيلية
- تشمل المعلومات:
  - تفاصيل الشركة المصنعة
  - إصدار البرنامج الثابت
  - مساحة التخزين المتاحة
  - قائمة الشهادات المخزنة على البطاقة

#### الاتصال ببطاقة
1. حدد البطاقة من القائمة
2. أدخل رقم التعريف الشخصي (PIN) عند الطلب
3. انقر على **اتصال**
4. بمجرد الاتصال، يمكنك:
   - عرض الشهادات
   - توقيع المستندات
   - تصدير معلومات الشهادة (بدون المفتاح الخاص)

---

## Certificate Operations / عمليات الشهادات {#certificate-operations}

### English

#### Viewing Certificates
1. Go to **Certificate Viewer** from the main menu
2. Select a certificate from the list or upload a new one
3. View detailed certificate information:
   - Subject DN (Distinguished Name)
   - Issuer DN
   - Validity Period (Not Before/Not After)
   - Serial Number
   - Thumbprint
   - Key Usage and Extended Key Usage
   - CRL Distribution Points
   - OCSP URLs
   - Provider Information

#### Certificate Validation
- Click **Validate** to check certificate validity
- The system will:
  - Check expiration date
  - Verify certificate chain
  - Check CRL (Certificate Revocation List)
  - Query OCSP responder
  - Display validation result with details

#### Exporting Certificates
1. Select the certificate to export
2. Click **Export**
3. Choose export format:
   - PEM (.pem)
   - DER (.cer, .crt)
   - PKCS#12 (.pfx, .p12) - requires new passphrase
4. Download the exported file

### العربية

#### عرض الشهادات
1. انتقل إلى **عارض الشهادات** من القائمة الرئيسية
2. حدد شهادة من القائمة أو قم بتحميل شهادة جديدة
3. اعرض معلومات الشهادة التفصيلية:
   - اسم الموضوع (Subject DN)
   - اسم الجهة المصدرة (Issuer DN)
   - فترة الصلاحية (من/إلى)
   - الرقم التسلسلي
   - البصمة الرقمية
   - استخدامات المفتاح والاستخدامات الموسعة
   - نقاط توزيع قائمة الإلغاء (CRL)
   - عناوين OCSP
   - معلومات المزود

#### التحقق من الشهادة
- انقر على **تحقق** للتحقق من صحة الشهادة
- سيقوم النظام بـ:
  - التحقق من تاريخ الانتهاء
  - التحقق من سلسلة الشهادات
  - التحقق من قائمة الإلغاء (CRL)
  - الاستعلام من محقق OCSP
  - عرض نتيجة التحقق مع التفاصيل

#### تصدير الشهادات
1. حدد الشهادة المراد تصديرها
2. انقر على **تصدير**
3. اختر صيغة التصدير:
   - PEM (.pem)
   - DER (.cer, .crt)
   - PKCS#12 (.pfx, .p12) - يتطلب كلمة مرور جديدة
4. قم بتنزيل الملف المصدر

---

## Document Signing / توقيع المستندات {#document-signing}

### English

#### Signing XML Documents (ETRANZACT/ZATCA)
1. Go to **Document Signer** from the main menu
2. Upload your XML file
3. Select the certificate to use for signing
4. Choose signing policy:
   - ETRANZACT (Egyptian e-Invoicing)
   - ZATCA (Saudi e-Invoicing)
   - Custom Policy
5. Click **Sign Document**
6. Download the signed XML file

#### Signing PDF Documents (PAdES)
1. Go to **Document Signer**
2. Upload your PDF file
3. Select the certificate
4. Configure signature appearance:
   - Position on page
   - Signature reason
   - Location
   - Contact information
5. Click **Sign PDF**
6. Download the signed PDF

#### Signing JSON Documents
1. Go to **Document Signer**
2. Upload or paste your JSON data
3. Select the certificate
4. Choose signature format (JWS/JWE)
5. Click **Sign JSON**
6. Download the signed JSON

### العربية

#### توقيع مستندات XML (ETRANZACT/ZATCA)
1. انتقل إلى **موقع المستندات** من القائمة الرئيسية
2. قم بتحميل ملف XML
3. حدد الشهادة المراد استخدامها للتوقيع
4. اختر سياسة التوقيع:
   - ETRANZACT (الفواتير الإلكترونية المصرية)
   - ZATCA (الفواتير الإلكترونية السعودية)
   - سياسة مخصصة
5. انقر على **توقيع المستند**
6. قم بتنزيل ملف XML الموقع

#### توقيع مستندات PDF (PAdES)
1. انتقل إلى **موقع المستندات**
2. قم بتحميل ملف PDF
3. حدد الشهادة
4. تكوين مظهر التوقيع:
   - الموضع على الصفحة
   - سبب التوقيع
   - الموقع
   - معلومات الاتصال
5. انقر على **توقيع PDF**
6. قم بتنزيل ملف PDF الموقع

#### توقيع مستندات JSON
1. انتقل إلى **موقع المستندات**
2. قم بتحميل أو لصق بيانات JSON
3. حدد الشهادة
4. اختر صيغة التوقيع (JWS/JWE)
5. انقر على **توقيع JSON**
6. قم بتنزيل ملف JSON الموقع

---

## Bulk Signing / التوقيع الجماعي {#bulk-signing}

### English

#### Scheduling Bulk Signing Operations
1. Go to **Bulk Signing** from the main menu
2. Upload multiple files (XML, PDF, JSON)
3. Select the certificate to use
4. Configure signing options:
   - Output directory
   - File naming pattern
   - Error handling strategy
5. Schedule the operation:
   - Run immediately
   - Schedule for later
   - Recurring schedule
6. Monitor progress in the jobs queue

#### Viewing Job Status
- Navigate to **Jobs Queue** tab
- View status of all scheduled jobs:
  - Pending
  - Running
  - Completed
  - Failed
- Click on any job to view detailed logs

### العربية

#### جدولة عمليات التوقيع الجماعي
1. انتقل إلى **التوقيع الجماعي** من القائمة الرئيسية
2. قم بتحميل ملفات متعددة (XML, PDF, JSON)
3. حدد الشهادة المراد استخدامها
4. تكوين خيارات التوقيع:
   - مجلد الإخراج
   - نمط تسمية الملفات
   - استراتيجية معالجة الأخطاء
5. جدولة العملية:
   - التشغيل فوراً
   - الجدولة لوقت لاحق
   - جدول متكرر
6. راقب التقدم في قائمة المهام

#### عرض حالة المهام
- انتقل إلى علامة التبويب **قائمة المهام**
- عرض حالة جميع المهام المجدولة:
  - قيد الانتظار
  - قيد التشغيل
  - مكتمل
  - فشل
- انقر على أي مهمة لعرض السجلات التفصيلية

---

## Audit Logs / سجلات التدقيق {#audit-logs}

### English

#### Viewing Audit Logs
1. Go to **Audit Logs** from the main menu
2. Filter logs by:
   - Date range
   - User
   - Action type
   - Status (Success/Failure)
3. View log details:
   - Timestamp
   - User who performed the action
   - Action description
   - IP address
   - Result status
   - Additional metadata

#### Exporting Audit Logs
- Click **Export** to download logs in CSV or PDF format
- Useful for compliance reporting and security audits

### العربية

#### عرض سجلات التدقيق
1. انتقل إلى **سجلات التدقيق** من القائمة الرئيسية
2. تصفية السجلات حسب:
   - نطاق التاريخ
   - المستخدم
   - نوع الإجراء
   - الحالة (نجاح/فشل)
3. عرض تفاصيل السجل:
   - الطابع الزمني
   - المستخدم الذي نفذ الإجراء
   - وصف الإجراء
   - عنوان IP
   - حالة النتيجة
   - بيانات وصفية إضافية

#### تصدير سجلات التدقيق
- انقر على **تصدير** لتنزيل السجلات بصيغة CSV أو PDF
- مفيد لتقارير الامتثال ومراجعات الأمان

---

## Settings / الإعدادات {#settings}

### English

#### General Settings
- Application language (Arabic/English)
- Timezone configuration
- Default certificate provider
- Signature policies

#### Security Settings
- Password policy configuration
- Session timeout
- Two-factor authentication (2FA)
- IP whitelist/blacklist

#### Token Settings
- Auto-scan interval
- Default PIN retry count
- Token connection timeout

#### Email Notifications
- Configure SMTP settings
- Enable/disable email notifications
- Notification templates

### العربية

#### الإعدادات العامة
- لغة التطبيق (عربي/إنجليزي)
- إعداد المنطقة الزمنية
- مزود الشهادة الافتراضي
- سياسات التوقيع

#### إعدادات الأمان
- تكوين سياسة كلمات المرور
- مهلة الجلسة
- المصادقة الثنائية (2FA)
- قائمة بيضاء/سوداء لعناوين IP

#### إعدادات البطاقات
- فاصل المسح التلقائي
- عدد محاولات إعادة إدخال PIN الافتراضي
- مهلة اتصال البطاقة

#### إشعارات البريد الإلكتروني
- تكوين إعدادات SMTP
- تمكين/تعطيل إشعارات البريد الإلكتروني
- قوالب الإشعارات

---

## Troubleshooting / استكشاف الأخطاء {#troubleshooting}

### Common Issues / المشاكل الشائعة

#### English

**Issue: Token not detected**
- Solution:
  1. Ensure token is properly connected
  2. Check if PC/SC service is running
  3. Verify PKCS#11 module path in settings
  4. Try restarting the application

**Issue: Certificate validation fails**
- Solution:
  1. Check internet connection (for OCSP/CRL)
  2. Verify certificate is not expired
  3. Ensure certificate chain is complete
  4. Check if certificate is revoked

**Issue: Signing operation fails**
- Solution:
  1. Verify certificate has correct key usage
  2. Check if PIN is correct
  3. Ensure document format is supported
  4. Review error logs for details

#### العربية

**المشكلة: عدم اكتشاف البطاقة**
- الحل:
  1. تأكد من توصيل البطاقة بشكل صحيح
  2. تحقق من تشغيل خدمة PC/SC
  3. تحقق من مسار وحدة PKCS#11 في الإعدادات
  4. حاول إعادة تشغيل التطبيق

**المشكلة: فشل التحقق من الشهادة**
- الحل:
  1. تحقق من اتصال الإنترنت (لـ OCSP/CRL)
  2. تأكد من أن الشهادة لم تنته صلاحيتها
  3. تأكد من اكتمال سلسلة الشهادات
  4. تحقق مما إذا كانت الشهادة ملغاة

**المشكلة: فشل عملية التوقيع**
- الحل:
  1. تحقق من أن الشهادة لها استخدام المفتاح الصحيح
  2. تأكد من صحة رقم التعريف الشخصي (PIN)
  3. تأكد من دعم تنسيق المستند
  4. راجع سجلات الأخطاء للحصول على التفاصيل

---

## Support / الدعم

### English
For technical support, please contact:
- Email: support@dsf.example.com
- Documentation: https://docs.dsf.example.com
- GitHub Issues: https://github.com/dsf/issues

### العربية
للحصول على الدعم الفني، يرجى الاتصال بـ:
- البريد الإلكتروني: support@dsf.example.com
- التوثيق: https://docs.dsf.example.com
- مشكلات GitHub: https://github.com/dsf/issues

---

## License / الترخيص

Digital Signature Factory is licensed under the MIT License.
See LICENSE file for details.

مصنع التوقيعات الرقمية مرخص بموجب ترخيص MIT.
راجع ملف LICENSE للتفاصيل.
