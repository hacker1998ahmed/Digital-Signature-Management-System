/**
 * Digital Signature Factory - Utility Functions
 * مصنع التوقيعات الرقمية - دوال مساعدة
 */

/**
 * Format file size to human readable format
 * تنسيق حجم الملف للقراءة البشرية
 */
export function formatFileSize(bytes) {
  if (bytes === 0) return '0 Bytes';
  
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

/**
 * Format date to local format
 * تنسيق التاريخ المحلي
 */
export function formatDate(dateString, locale = 'ar-EG') {
  const date = new Date(dateString);
  return new Intl.DateTimeFormat(locale, {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  }).format(date);
}

/**
 * Format relative time (e.g., "2 hours ago")
 * تنسيق الوقت النسبي
 */
export function formatRelativeTime(dateString, locale = 'ar') {
  const date = new Date(dateString);
  const now = new Date();
  const diffInSeconds = Math.floor((now - date) / 1000);
  
  const intervals = [
    { label: 'year', seconds: 31536000 },
    { label: 'month', seconds: 2592000 },
    { label: 'day', seconds: 86400 },
    { label: 'hour', seconds: 3600 },
    { label: 'minute', seconds: 60 },
    { label: 'second', seconds: 1 }
  ];
  
  for (const interval of intervals) {
    const count = Math.floor(diffInSeconds / interval.seconds);
    if (count >= 1) {
      if (locale === 'ar') {
        return `${count} ${interval.label}${count > 1 ? 's' : ''} منذ`;
      }
      return `${count} ${interval.label}${count > 1 ? 's' : ''} ago`;
    }
  }
  
  return locale === 'ar' ? 'الآن' : 'just now';
}

/**
 * Validate email format
 * التحقق من صحة البريد الإلكتروني
 */
export function isValidEmail(email) {
  const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return re.test(email);
}

/**
 * Generate random string
 * توليد سلسلة عشوائية
 */
export function generateRandomString(length = 32) {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  let result = '';
  for (let i = 0; i < length; i++) {
    result += chars.charAt(Math.floor(Math.random() * chars.length));
  }
  return result;
}

/**
 * Debounce function
 * تأخير تنفيذ الدالة
 */
export function debounce(func, wait = 300) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

/**
 * Throttle function
 * تحديد معدل تنفيذ الدالة
 */
export function throttle(func, limit = 300) {
  let inThrottle;
  return function(...args) {
    if (!inThrottle) {
      func.apply(this, args);
      inThrottle = true;
      setTimeout(() => inThrottle = false, limit);
    }
  };
}

/**
 * Download file from blob
 * تنزيل ملف من blob
 */
export function downloadFile(blob, filename) {
  const url = window.URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  window.URL.revokeObjectURL(url);
}

/**
 * Copy text to clipboard
 * نسخ النص إلى الحافظة
 */
export async function copyToClipboard(text) {
  try {
    await navigator.clipboard.writeText(text);
    return true;
  } catch (err) {
    // Fallback for older browsers
    const textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    document.body.appendChild(textArea);
    textArea.select();
    try {
      document.execCommand('copy');
      return true;
    } catch (err) {
      return false;
    } finally {
      document.body.removeChild(textArea);
    }
  }
}

/**
 * Parse certificate subject/issuer DN
 * تحليل موضوع/جهة إصدار الشهادة
 */
export function parseDN(dn) {
  const parts = {};
  const pairs = dn.split(',');
  
  for (const pair of pairs) {
    const [key, value] = pair.split('=').map(s => s.trim());
    if (key && value) {
      parts[key] = value;
    }
  }
  
  return parts;
}

/**
 * Get certificate status based on validity
 * الحصول على حالة الشهادة بناءً على الصلاحية
 */
export function getCertificateStatus(notBefore, notAfter) {
  const now = new Date();
  const startDate = new Date(notBefore);
  const endDate = new Date(notAfter);
  
  if (now < startDate) {
    return { status: 'not-yet-valid', color: 'warning' };
  }
  
  if (now > endDate) {
    return { status: 'expired', color: 'error' };
  }
  
  const daysUntilExpiry = Math.floor((endDate - now) / (1000 * 60 * 60 * 24));
  
  if (daysUntilExpiry <= 30) {
    return { status: 'expiring-soon', days: daysUntilExpiry, color: 'warning' };
  }
  
  if (daysUntilExpiry <= 90) {
    return { status: 'expiring-warning', days: daysUntilExpiry, color: 'info' };
  }
  
  return { status: 'valid', days: daysUntilExpiry, color: 'success' };
}

/**
 * Format certificate key usage
 * تنسيق استخدامات مفتاح الشهادة
 */
export function formatKeyUsage(keyUsage) {
  const usageMap = {
    'digitalSignature': 'توقيع رقمي',
    'nonRepudiation': 'عدم الإنكار',
    'keyEncipherment': 'تشفير المفتاح',
    'dataEncipherment': 'تشفير البيانات',
    'keyAgreement': 'اتفاق المفتاح',
    'keyCertSign': 'توقيع الشهادة',
    'cRLSign': 'توقيع CRL',
    'encipherOnly': 'التشفير فقط',
    'decipherOnly': 'فك التشفير فقط'
  };
  
  if (!keyUsage) return [];
  
  return keyUsage
    .split(',')
    .map(usage => usage.trim())
    .map(usage => ({
      code: usage,
      label: usageMap[usage] || usage,
      labelEn: usage
    }));
}

/**
 * Calculate progress percentage
 * حساب نسبة التقدم
 */
export function calculateProgress(current, total) {
  if (total === 0) return 0;
  return Math.round((current / total) * 100);
}

/**
 * Truncate text with ellipsis
 * اختصار النص مع نقاط
 */
export function truncateText(text, maxLength = 50) {
  if (!text || text.length <= maxLength) return text;
  return text.substring(0, maxLength) + '...';
}

/**
 * Get file icon based on extension
 * الحصول على أيقونة الملف بناءً على الامتداد
 */
export function getFileIcon(filename) {
  const ext = filename.split('.').pop().toLowerCase();
  const iconMap = {
    'pfx': '🔐',
    'p12': '🔐',
    'pem': '📜',
    'cer': '📄',
    'crt': '📄',
    'xml': '📋',
    'json': '📝',
    'pdf': '📕',
    'csv': '📊'
  };
  return iconMap[ext] || '📁';
}

/**
 * Validate password strength
 * التحقق من قوة كلمة المرور
 */
export function validatePassword(password) {
  const requirements = [
    { test: /.{8,}/, label: 'At least 8 characters' },
    { test: /[A-Z]/, label: 'One uppercase letter' },
    { test: /[a-z]/, label: 'One lowercase letter' },
    { test: /[0-9]/, label: 'One number' },
    { test: /[^A-Za-z0-9]/, label: 'One special character' }
  ];
  
  const results = requirements.map(req => ({
    met: req.test.test(password),
    label: req.label
  }));
  
  const score = results.filter(r => r.met).length;
  const strength = score <= 2 ? 'weak' : score <= 4 ? 'medium' : 'strong';
  
  return { score, strength, requirements: results };
}

/**
 * Sleep/delay utility
 * أداة التأخير
 */
export function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

/**
 * Deep clone object
 * استنساخ عميق للكائن
 */
export function deepClone(obj) {
  return JSON.parse(JSON.stringify(obj));
}

/**
 * Check if object is empty
 * التحقق مما إذا كان الكائن فارغًا
 */
export function isEmpty(obj) {
  return Object.keys(obj).length === 0;
}

/**
 * Group array by key
 * تجميع المصفوفة حسب المفتاح
 */
export function groupBy(array, key) {
  return array.reduce((result, item) => {
    const group = item[key];
    if (!result[group]) {
      result[group] = [];
    }
    result[group].push(item);
    return result;
  }, {});
}

/**
 * Sort array by key
 * ترتيب المصفوفة حسب المفتاح
 */
export function sortBy(array, key, ascending = true) {
  return [...array].sort((a, b) => {
    const aVal = a[key];
    const bVal = b[key];
    
    if (aVal < bVal) return ascending ? -1 : 1;
    if (aVal > bVal) return ascending ? 1 : -1;
    return 0;
  });
}

/**
 * Unique array values
 * قيم المصفوفة الفريدة
 */
export function unique(array, key) {
  if (!key) return [...new Set(array)];
  const seen = new Map();
  return array.filter(item => {
    const val = item[key];
    if (seen.has(val)) return false;
    seen.set(val, true);
    return true;
  });
}
