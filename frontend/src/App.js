import React, { useState, useEffect } from 'react';
import { ThemeProvider, createTheme, CssBaseline, Box } from '@mui/material';
import { BrowserRouter as Router, Routes, Route } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

// Components
import Dashboard from './components/Dashboard';
import TokenManager from './components/TokenManager';
import CertificateViewer from './components/CertificateViewer';
import DocumentSigner from './components/DocumentSigner';
import BulkSigning from './components/BulkSigning';
import AuditLogs from './components/AuditLogs';
import Settings from './components/Settings';
import Navbar from './components/Navbar';

// API Service
import api from './services/api';

const theme = createTheme({
  palette: {
    mode: 'light',
    primary: {
      main: '#1976d2',
    },
    secondary: {
      main: '#dc004e',
    },
    success: {
      main: '#2e7d32',
    },
    warning: {
      main: '#ed6c02',
    },
    error: {
      main: '#d32f2f',
    },
  },
  typography: {
    fontFamily: '"Cairo", "Roboto", "Helvetica", "Arial", sans-serif',
  },
  direction: 'rtl',
});

function App() {
  const { i18n } = useTranslation();
  const [direction, setDirection] = useState('rtl');

  useEffect(() => {
    // تحديث الاتجاه عند تغيير اللغة
    const currentLang = i18n.language;
    setDirection(currentLang === 'ar' ? 'rtl' : 'ltr');
    document.dir = direction;
  }, [i18n.language]);

  return (
    <ThemeProvider theme={{ ...theme, direction }}>
      <CssBaseline />
      <Router>
        <Box sx={{ display: 'flex', flexDirection: 'column', minHeight: '100vh' }}>
          <Navbar />
          <Box component="main" sx={{ flexGrow: 1, p: 3 }}>
            <Routes>
              <Route path="/" element={<Dashboard />} />
              <Route path="/tokens" element={<TokenManager />} />
              <Route path="/certificates" element={<CertificateViewer />} />
              <Route path="/sign" element={<DocumentSigner />} />
              <Route path="/bulk-signing" element={<BulkSigning />} />
              <Route path="/audit-logs" element={<AuditLogs />} />
              <Route path="/settings" element={<Settings />} />
            </Routes>
          </Box>
        </Box>
      </Router>
    </ThemeProvider>
  );
}

export default App;
