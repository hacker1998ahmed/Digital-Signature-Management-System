/**
 * Digital Signature Factory - Custom React Hooks
 * مصنع التوقيعات الرقمية - هوكس React مخصصة
 */

import { useState, useEffect, useCallback } from 'react';
import api from '../services/api';

/**
 * Hook for authentication state management
 * هوك لإدارة حالة المصادقة
 */
export function useAuth() {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (token) {
      api.getCurrentUser()
        .then(setUser)
        .catch(() => {
          localStorage.removeItem('token');
          setUser(null);
        })
        .finally(() => setLoading(false));
    } else {
      setLoading(false);
    }
  }, []);

  const login = async (username, password) => {
    try {
      const data = await api.login(username, password);
      localStorage.setItem('token', data.token);
      setUser(data.user);
      setError(null);
      return data;
    } catch (err) {
      setError(err.message);
      throw err;
    }
  };

  const logout = () => {
    localStorage.removeItem('token');
    setUser(null);
    setError(null);
  };

  return { user, loading, error, login, logout };
}

/**
 * Hook for certificate management
 * هوك لإدارة الشهادات
 */
export function useCertificates() {
  const [certificates, setCertificates] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const fetchCertificates = useCallback(async () => {
    setLoading(true);
    try {
      const data = await api.getCertificates();
      setCertificates(data);
      setError(null);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchCertificates();
  }, [fetchCertificates]);

  const uploadCertificate = async (file, formData) => {
    try {
      const cert = await api.uploadCertificate(file, formData);
      setCertificates(prev => [...prev, cert]);
      return cert;
    } catch (err) {
      setError(err.message);
      throw err;
    }
  };

  const deleteCertificate = async (id) => {
    try {
      await api.deleteCertificate(id);
      setCertificates(prev => prev.filter(c => c.id !== id));
    } catch (err) {
      setError(err.message);
      throw err;
    }
  };

  return { certificates, loading, error, refresh: fetchCertificates, uploadCertificate, deleteCertificate };
}

/**
 * Hook for token management
 * هوك لإدارة Tokens
 */
export function useTokens() {
  const [tokens, setTokens] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const scanTokens = useCallback(async () => {
    setLoading(true);
    try {
      const data = await api.scanTokens();
      setTokens(data);
      setError(null);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    scanTokens();
    // Auto-scan every 30 seconds
    const interval = setInterval(scanTokens, 30000);
    return () => clearInterval(interval);
  }, [scanTokens]);

  const getTokenInfo = async (tokenId) => {
    try {
      return await api.getTokenInfo(tokenId);
    } catch (err) {
      setError(err.message);
      throw err;
    }
  };

  return { tokens, loading, error, refresh: scanTokens, getTokenInfo };
}

/**
 * Hook for document signing
 * هوك لتوقيع المستندات
 */
export function useDocumentSigner() {
  const [signing, setSigning] = useState(false);
  const [error, setError] = useState(null);
  const [result, setResult] = useState(null);

  const signDocument = useCallback(async (document, certId, options = {}) => {
    setSigning(true);
    setError(null);
    
    try {
      const signedDoc = await api.signDocument(document, certId, options);
      setResult(signedDoc);
      return signedDoc;
    } catch (err) {
      setError(err.message);
      throw err;
    } finally {
      setSigning(false);
    }
  }, []);

  const signXML = async (xmlContent, certId, policy = 'etransact') => {
    return signDocument(xmlContent, certId, { type: 'xml', policy });
  };

  const signPDF = async (pdfFile, certId, options = {}) => {
    return signDocument(pdfFile, certId, { ...options, type: 'pdf' });
  };

  const signJSON = async (jsonData, certId) => {
    return signDocument(jsonData, certId, { type: 'json' });
  };

  const reset = () => {
    setSigning(false);
    setError(null);
    setResult(null);
  };

  return { signing, error, result, signDocument, signXML, signPDF, signJSON, reset };
}

/**
 * Hook for audit logs
 * هوك لسجلات التدقيق
 */
export function useAuditLogs(initialFilters = {}) {
  const [logs, setLogs] = useState([]);
  const [loading, setLoading] = useState(false);
  const [total, setTotal] = useState(0);
  const [filters, setFilters] = useState(initialFilters);

  const fetchLogs = useCallback(async (page = 1, limit = 50) => {
    setLoading(true);
    try {
      const data = await api.getAuditLogs({ ...filters, page, limit });
      setLogs(data.logs);
      setTotal(data.total);
    } catch (err) {
      console.error('Failed to fetch audit logs:', err);
    } finally {
      setLoading(false);
    }
  }, [filters]);

  useEffect(() => {
    fetchLogs();
  }, [fetchLogs]);

  const updateFilters = (newFilters) => {
    setFilters(prev => ({ ...prev, ...newFilters }));
  };

  const exportLogs = async (format = 'csv') => {
    try {
      return await api.exportAuditLogs(format);
    } catch (err) {
      console.error('Failed to export logs:', err);
      throw err;
    }
  };

  return { logs, loading, total, filters, refresh: fetchLogs, updateFilters, exportLogs };
}

/**
 * Hook for bulk operations
 * هوك للعمليات الجماعية
 */
export function useBulkOperations() {
  const [processing, setProcessing] = useState(false);
  const [progress, setProgress] = useState(0);
  const [results, setResults] = useState([]);
  const [error, setError] = useState(null);

  const bulkSign = useCallback(async (files, certId, options = {}) => {
    setProcessing(true);
    setProgress(0);
    setError(null);
    setResults([]);

    try {
      const result = await api.bulkSign(files, certId, options, (progressEvent) => {
        const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total);
        setProgress(percentCompleted);
      });

      setResults(result.results);
      return result;
    } catch (err) {
      setError(err.message);
      throw err;
    } finally {
      setProcessing(false);
    }
  }, []);

  const cancelOperation = useCallback(() => {
    api.cancelBulkOperation();
    setProcessing(false);
    setProgress(0);
  }, []);

  return { processing, progress, results, error, bulkSign, cancelOperation };
}

/**
 * Hook for settings management
 * هوك لإدارة الإعدادات
 */
export function useSettings() {
  const [settings, setSettings] = useState({});
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);

  const fetchSettings = useCallback(async () => {
    setLoading(true);
    try {
      const data = await api.getSettings();
      setSettings(data);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    fetchSettings();
  }, [fetchSettings]);

  const updateSetting = async (key, value) => {
    try {
      await api.updateSetting(key, value);
      setSettings(prev => ({ ...prev, [key]: value }));
    } catch (err) {
      setError(err.message);
      throw err;
    }
  };

  const updateSettings = async (newSettings) => {
    try {
      await api.updateSettings(newSettings);
      setSettings(prev => ({ ...prev, ...newSettings }));
    } catch (err) {
      setError(err.message);
      throw err;
    }
  };

  return { settings, loading, error, refresh: fetchSettings, updateSetting, updateSettings };
}

/**
 * Hook for language switching
 * هوك لتبديل اللغة
 */
export function useLanguage() {
  const [language, setLanguageState] = useState(localStorage.getItem('language') || 'ar');

  useEffect(() => {
    document.documentElement.dir = language === 'ar' ? 'rtl' : 'ltr';
    document.documentElement.lang = language;
    localStorage.setItem('language', language);
  }, [language]);

  const setLanguage = useCallback((lang) => {
    setLanguageState(lang);
  }, []);

  const toggleLanguage = useCallback(() => {
    setLanguageState(prev => prev === 'ar' ? 'en' : 'ar');
  }, []);

  return { language, setLanguage, toggleLanguage };
}

/**
 * Hook for notifications
 * هوك للإشعارات
 */
export function useNotifications() {
  const [notifications, setNotifications] = useState([]);

  const addNotification = useCallback((message, type = 'info', duration = 5000) => {
    const id = Date.now();
    setNotifications(prev => [...prev, { id, message, type }]);

    if (duration > 0) {
      setTimeout(() => {
        setNotifications(prev => prev.filter(n => n.id !== id));
      }, duration);
    }

    return id;
  }, []);

  const removeNotification = useCallback((id) => {
    setNotifications(prev => prev.filter(n => n.id !== id));
  }, []);

  const success = useCallback((message, duration) => {
    return addNotification(message, 'success', duration);
  }, [addNotification]);

  const error = useCallback((message, duration) => {
    return addNotification(message, 'error', duration);
  }, [addNotification]);

  const warning = useCallback((message, duration) => {
    return addNotification(message, 'warning', duration);
  }, [addNotification]);

  const info = useCallback((message, duration) => {
    return addNotification(message, 'info', duration);
  }, [addNotification]);

  return { notifications, addNotification, removeNotification, success, error, warning, info };
}
