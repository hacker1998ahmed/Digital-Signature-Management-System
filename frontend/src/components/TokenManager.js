import React from 'react';
import { useTranslation } from 'react-i18next';
import {
  Box,
  Typography,
  Card,
  CardContent,
  Grid
} from '@mui/material';

function TokenManager() {
  const { t } = useTranslation();

  return (
    <Box>
      <Typography variant="h4" gutterBottom>
        {t('Token Manager')}
      </Typography>

      <Grid container spacing={3}>
        <Grid item xs={12}>
          <Card>
            <CardContent>
              <Typography variant="h6">
                ماسح الـ Tokens
              </Typography>
              <Typography variant="body2" color="textSecondary" sx={{ mt: 1 }}>
                اضغط على زر الفحص لاكتشاف جميع الـ Tokens المتصلة
              </Typography>
            </CardContent>
          </Card>
        </Grid>
      </Grid>
    </Box>
  );
}

export default TokenManager;
