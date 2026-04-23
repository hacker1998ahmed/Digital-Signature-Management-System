import React from 'react';
import { useTranslation } from 'react-i18next';
import { Box, Typography } from '@mui/material';

export default function CertificateViewer() {
  const { t } = useTranslation();
  return (
    <Box>
      <Typography variant="h4">{t('Certificates')}</Typography>
    </Box>
  );
}
