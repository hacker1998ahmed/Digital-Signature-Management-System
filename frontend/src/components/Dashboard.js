import React, { useState, useEffect } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Box,
  Grid,
  Card,
  CardContent,
  Typography,
  Button,
  CircularProgress,
  Alert,
  Chip
} from '@mui/material';
import {
  Usb as UsbIcon,
  Security as SecurityIcon,
  Description as DocumentIcon,
  CheckCircle as CheckCircleIcon,
  Warning as WarningIcon,
  Refresh as RefreshIcon
} from '@mui/icons-material';
import { tokenAPI, certificateAPI, signingAPI, auditAPI } from '../services/api';

function Dashboard() {
  const { t, i18n } = useTranslation();
  const [loading, setLoading] = useState(true);
  const [stats, setStats] = useState({
    totalCertificates: 0,
    activeTokens: 0,
    documentsSignedToday: 0,
    expiringSoon: 0
  });
  const [tokens, setTokens] = useState([]);
  const [recentActivity, setRecentActivity] = useState([]);
  const [error, setError] = useState(null);

  useEffect(() => {
    loadDashboardData();
  }, []);

  const loadDashboardData = async () => {
    try {
      setLoading(true);
      setError(null);

      // Load tokens
      const tokensResponse = await tokenAPI.scan();
      setTokens(tokensResponse.data.tokens || []);

      // Load certificates
      const certsResponse = await certificateAPI.list();
      const certificates = certsResponse.data.certificates || [];

      // Calculate stats
      const today = new Date().toISOString().split('T')[0];
      const expiringCount = certificates.filter(cert => {
        const daysUntilExpiry = Math.ceil((new Date(cert.valid_to) - new Date()) / (1000 * 60 * 60 * 24));
        return daysUntilExpiry <= 30 && daysUntilExpiry > 0;
      }).length;

      setStats({
        totalCertificates: certificates.length,
        activeTokens: tokens.filter(t => t.is_present).length,
        documentsSignedToday: Math.floor(Math.random() * 50), // Placeholder
        expiringSoon: expiringCount
      });

      // Load recent activity
      const logsResponse = await auditAPI.getLogs({ limit: 5 });
      setRecentActivity(logsResponse.data.logs || []);

    } catch (err) {
      setError(err.message || t('Error loading dashboard data'));
    } finally {
      setLoading(false);
    }
  };

  const handleScanTokens = async () => {
    try {
      const response = await tokenAPI.scan();
      setTokens(response.data.tokens || []);
      
      // Update active tokens count
      setStats(prev => ({
        ...prev,
        activeTokens: (response.data.tokens || []).filter(t => t.is_present).length
      }));
    } catch (err) {
      setError(t('Failed to scan tokens'));
    }
  };

  if (loading) {
    return (
      <Box sx={{ display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '400px' }}>
        <CircularProgress />
      </Box>
    );
  }

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        {t('Dashboard')}
      </Typography>

      {error && (
        <Alert severity="error" sx={{ mb: 3 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}

      {/* Stats Cards */}
      <Grid container spacing={3} sx={{ mb: 4 }}>
        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <Box>
                  <Typography color="textSecondary" variant="body2">
                    {t('Total Certificates')}
                  </Typography>
                  <Typography variant="h4">{stats.totalCertificates}</Typography>
                </Box>
                <SecurityIcon color="primary" sx={{ fontSize: 48, opacity: 0.3 }} />
              </Box>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <Box>
                  <Typography color="textSecondary" variant="body2">
                    {t('Active Tokens')}
                  </Typography>
                  <Typography variant="h4">{stats.activeTokens}</Typography>
                </Box>
                <UsbIcon color="success" sx={{ fontSize: 48, opacity: 0.3 }} />
              </Box>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <Box>
                  <Typography color="textSecondary" variant="body2">
                    {t('Documents Signed Today')}
                  </Typography>
                  <Typography variant="h4">{stats.documentsSignedToday}</Typography>
                </Box>
                <DocumentIcon color="info" sx={{ fontSize: 48, opacity: 0.3 }} />
              </Box>
            </CardContent>
          </Card>
        </Grid>

        <Grid item xs={12} sm={6} md={3}>
          <Card>
            <CardContent>
              <Box sx={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between' }}>
                <Box>
                  <Typography color="textSecondary" variant="body2">
                    {t('Expiring Soon')}
                  </Typography>
                  <Typography variant="h4" color="warning.main">{stats.expiringSoon}</Typography>
                </Box>
                <WarningIcon color="warning" sx={{ fontSize: 48, opacity: 0.3 }} />
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Quick Actions */}
      <Grid container spacing={3} sx={{ mb: 4 }}>
        <Grid item xs={12}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                الإجراءات السريعة
              </Typography>
              <Box sx={{ display: 'flex', gap: 2, flexWrap: 'wrap' }}>
                <Button 
                  variant="contained" 
                  startIcon={<RefreshIcon />}
                  onClick={handleScanTokens}
                >
                  {t('Scan for Tokens')}
                </Button>
                <Button 
                  variant="outlined" 
                  startIcon={<UsbIcon />}
                  href="/tokens"
                >
                  {t('Token Manager')}
                </Button>
                <Button 
                  variant="outlined" 
                  startIcon={<DocumentIcon />}
                  href="/sign"
                >
                  {t('Sign Documents')}
                </Button>
              </Box>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Detected Tokens */}
      <Grid container spacing={3}>
        <Grid item xs={12} md={6}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                {t('Detected Tokens')}
              </Typography>
              
              {tokens.length === 0 ? (
                <Alert severity="info">{t('No tokens detected')}</Alert>
              ) : (
                <Box sx={{ mt: 2 }}>
                  {tokens.map((token, index) => (
                    <Box 
                      key={index}
                      sx={{ 
                        p: 2, 
                        mb: 1, 
                        bgcolor: 'background.default', 
                        borderRadius: 1,
                        display: 'flex',
                        justifyContent: 'space-between',
                        alignItems: 'center'
                      }}
                    >
                      <Box>
                        <Typography variant="body1">
                          {token.label || `${token.provider} Token`}
                        </Typography>
                        <Typography variant="caption" color="textSecondary">
                          {token.provider} - {token.serial || 'N/A'}
                        </Typography>
                      </Box>
                      <Chip 
                        label={token.is_present ? t('Connected') : t('Disconnected')}
                        color={token.is_present ? 'success' : 'default'}
                        size="small"
                      />
                    </Box>
                  ))}
                </Box>
              )}
            </CardContent>
          </Card>
        </Grid>

        {/* Recent Activity */}
        <Grid item xs={12} md={6}>
          <Card>
            <CardContent>
              <Typography variant="h6" gutterBottom>
                النشاط الأخير
              </Typography>
              
              {recentActivity.length === 0 ? (
                <Alert severity="info">لا يوجد نشاط حديث</Alert>
              ) : (
                <Box sx={{ mt: 2 }}>
                  {recentActivity.map((log, index) => (
                    <Box 
                      key={index}
                      sx={{ 
                        p: 2, 
                        mb: 1, 
                        bgcolor: 'background.default', 
                        borderRadius: 1 
                      }}
                    >
                      <Box sx={{ display: 'flex', justifyContent: 'space-between' }}>
                        <Typography variant="body2">
                          {log.event_type}
                        </Typography>
                        <Typography variant="caption" color="textSecondary">
                          {new Date(log.timestamp).toLocaleString()}
                        </Typography>
                      </Box>
                      <Typography variant="caption" color="textSecondary">
                        {log.data?.document_type || log.data?.certificate_serial || ''}
                      </Typography>
                    </Box>
                  ))}
                </Box>
              )}
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}

export default Dashboard;
