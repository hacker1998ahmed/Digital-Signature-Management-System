import React from 'react';
import { useTranslation } from 'react-i18next';
import { Box, Typography } from '@mui/material';

export default function AuditLogs() {
  const { t } = useTranslation();
  return (
    <Box>
      <Typography variant="h4">{t('Audit Logs')}</Typography>
    </Box>
  );
}
