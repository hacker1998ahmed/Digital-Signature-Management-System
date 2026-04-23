import React from 'react';
import { useTranslation } from 'react-i18next';
import { Box, Typography } from '@mui/material';

export default function Settings() {
  const { t } = useTranslation();
  return (
    <Box>
      <Typography variant="h4">{t('Settings')}</Typography>
    </Box>
  );
}
