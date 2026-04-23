import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

const resources = {
  ar: {
    translation: {
      // القوائم الرئيسية
      "Dashboard": "لوحة التحكم",
      "Token Manager": "مدير الـ Tokens",
      "Certificates": "الشهادات الرقمية",
      "Sign Documents": "توقيع المستندات",
      "Bulk Signing": "التوقيع الجماعي",
      "Settings": "الإعدادات",
      "Audit Logs": "سجل التدقيق",
      
      // رسائل الترحيب
      "Welcome to Digital Signature Factory": "مرحباً بكم في مصنع التوقيع الرقمي",
      "Manage all your digital certificates and tokens": "إدارة جميع الشهادات والـ Tokens الرقمية",
      
      // Token Scanner
      "Scan for Tokens": "فحص الـ Tokens",
      "Detected Tokens": "الـ Tokens المكتشفة",
      "No tokens detected": "لم يتم اكتشاف أي Tokens",
      "Token Type": "نوع الـ Token",
      "Provider": "مزود الخدمة",
      "Serial Number": "الرقم التسلسلي",
      "Status": "الحالة",
      "Connected": "متصل",
      "Disconnected": "غير متصل",
      
      // Certificate Info
      "Certificate Details": "تفاصيل الشهادة",
      "Subject": "الموضوع",
      "Issuer": "الجهة المصدرة",
      "Valid From": "صالحة من",
      "Valid To": "صالحة حتى",
      "Key Usage": "استخدامات المفتاح",
      "Serial": "المسلسل",
      "Thumbprint": "البصمة",
      "Days Until Expiry": "أيام حتى الانتهاء",
      
      // Actions
      "Sign": "توقيع",
      "Export": "تصدير",
      "Import": "استيراد",
      "Delete": "حذف",
      "Refresh": "تحديث",
      "Upload Document": "رفع مستند",
      "Select Certificate": "اختر شهادة",
      "Enter PIN": "أدخل رمز PIN",
      "Submit": "إرسال",
      "Cancel": "إلغاء",
      
      // Document Types
      "Document Type": "نوع المستند",
      "PDF Document": "مستند PDF",
      "XML Document": "مستند XML",
      "JSON Document": "مستند JSON",
      "E-Invoice": "فاتورة إلكترونية",
      
      // Signing Options
      "Signing Policy": "سياسة التوقيع",
      "Include Timestamp": "تضمين طابع زمني",
      "Include Certificate Chain": "تضمين سلسلة الشهادات",
      "Signature Level": "مستوى التوقيع",
      "Basic Signature": "توقيع أساسي",
      "Advanced Signature (PAdES)": "توقيع متقدم (PAdES)",
      "Qualified Signature": "توقيع مؤهل",
      
      // Providers
      "EgyTrust": "إيجي تراست",
      "MCB": "مصر المقاصة",
      "DeltaTrust": "دلتا تراست",
      "C3": "سيجي",
      "All Providers": "جميع المزودين",
      
      // Messages
      "Signing successful": "تم التوقيع بنجاح",
      "Signing failed": "فشل التوقيع",
      "Certificate expired": "الشهادة منتهية الصلاحية",
      "Invalid PIN": "رمز PIN غير صحيح",
      "Token not found": "لم يتم العثور على Token",
      "Document uploaded successfully": "تم رفع المستند بنجاح",
      "Please select a certificate": "يرجى اختيار شهادة",
      
      // Stats
      "Total Certificates": "إجمالي الشهادات",
      "Active Tokens": "الـ Tokens النشطة",
      "Documents Signed Today": "المستندات الموقعة اليوم",
      "Expiring Soon": "تنتهي قريباً",
      
      // Navigation
      "Home": "الرئيسية",
      "Back": "رجوع",
      "Next": "التالي",
      "Finish": "إنهاء",
      
      // Errors
      "Error": "خطأ",
      "Success": "نجاح",
      "Warning": "تحذير",
      "Info": "معلومات"
    }
  },
  en: {
    translation: {
      // Main Menu
      "Dashboard": "Dashboard",
      "Token Manager": "Token Manager",
      "Certificates": "Certificates",
      "Sign Documents": "Sign Documents",
      "Bulk Signing": "Bulk Signing",
      "Settings": "Settings",
      "Audit Logs": "Audit Logs",
      
      // Welcome
      "Welcome to Digital Signature Factory": "Welcome to Digital Signature Factory",
      "Manage all your digital certificates and tokens": "Manage all your digital certificates and tokens",
      
      // Token Scanner
      "Scan for Tokens": "Scan for Tokens",
      "Detected Tokens": "Detected Tokens",
      "No tokens detected": "No tokens detected",
      "Token Type": "Token Type",
      "Provider": "Provider",
      "Serial Number": "Serial Number",
      "Status": "Status",
      "Connected": "Connected",
      "Disconnected": "Disconnected",
      
      // Certificate Info
      "Certificate Details": "Certificate Details",
      "Subject": "Subject",
      "Issuer": "Issuer",
      "Valid From": "Valid From",
      "Valid To": "Valid To",
      "Key Usage": "Key Usage",
      "Serial": "Serial",
      "Thumbprint": "Thumbprint",
      "Days Until Expiry": "Days Until Expiry",
      
      // Actions
      "Sign": "Sign",
      "Export": "Export",
      "Import": "Import",
      "Delete": "Delete",
      "Refresh": "Refresh",
      "Upload Document": "Upload Document",
      "Select Certificate": "Select Certificate",
      "Enter PIN": "Enter PIN",
      "Submit": "Submit",
      "Cancel": "Cancel",
      
      // Document Types
      "Document Type": "Document Type",
      "PDF Document": "PDF Document",
      "XML Document": "XML Document",
      "JSON Document": "JSON Document",
      "E-Invoice": "E-Invoice",
      
      // Signing Options
      "Signing Policy": "Signing Policy",
      "Include Timestamp": "Include Timestamp",
      "Include Certificate Chain": "Include Certificate Chain",
      "Signature Level": "Signature Level",
      "Basic Signature": "Basic Signature",
      "Advanced Signature (PAdES)": "Advanced Signature (PAdES)",
      "Qualified Signature": "Qualified Signature",
      
      // Providers
      "EgyTrust": "EgyTrust",
      "MCB": "MCB",
      "DeltaTrust": "DeltaTrust",
      "C3": "C3",
      "All Providers": "All Providers",
      
      // Messages
      "Signing successful": "Signing successful",
      "Signing failed": "Signing failed",
      "Certificate expired": "Certificate expired",
      "Invalid PIN": "Invalid PIN",
      "Token not found": "Token not found",
      "Document uploaded successfully": "Document uploaded successfully",
      "Please select a certificate": "Please select a certificate",
      
      // Stats
      "Total Certificates": "Total Certificates",
      "Active Tokens": "Active Tokens",
      "Documents Signed Today": "Documents Signed Today",
      "Expiring Soon": "Expiring Soon",
      
      // Navigation
      "Home": "Home",
      "Back": "Back",
      "Next": "Next",
      "Finish": "Finish",
      
      // Errors
      "Error": "Error",
      "Success": "Success",
      "Warning": "Warning",
      "Info": "Info"
    }
  }
};

i18n
  .use(initReactI18next)
  .init({
    resources,
    lng: 'ar',
    fallbackLng: 'en',
    interpolation: {
      escapeValue: false
    }
  });

export default i18n;
