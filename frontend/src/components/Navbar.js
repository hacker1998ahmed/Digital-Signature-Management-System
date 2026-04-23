import React, { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  AppBar,
  Toolbar,
  Typography,
  IconButton,
  Drawer,
  List,
  ListItem,
  ListItemIcon,
  ListItemText,
  Box,
  Divider,
  Badge,
  Switch,
  FormControlLabel
} from '@mui/material';
import {
  Menu as MenuIcon,
  Dashboard as DashboardIcon,
  Usb as UsbIcon,
  Security as SecurityIcon,
  Description as DocumentIcon,
  LibraryAdd as BulkIcon,
  Assignment as AssignmentIcon,
  Settings as SettingsIcon,
  Language as LanguageIcon,
  Notifications as NotificationsIcon
} from '@mui/icons-material';

function Navbar() {
  const { t, i18n } = useTranslation();
  const [mobileOpen, setMobileOpen] = useState(false);
  const [isRTL, setIsRTL] = useState(i18n.language === 'ar');

  const handleDrawerToggle = () => {
    setMobileOpen(!mobileOpen);
  };

  const handleLanguageChange = (event) => {
    const newLang = event.target.checked ? 'en' : 'ar';
    i18n.changeLanguage(newLang);
    setIsRTL(newLang === 'ar');
    document.dir = newLang === 'ar' ? 'rtl' : 'ltr';
  };

  const menuItems = [
    { text: 'Dashboard', icon: <DashboardIcon />, path: '/' },
    { text: 'Token Manager', icon: <UsbIcon />, path: '/tokens' },
    { text: 'Certificates', icon: <SecurityIcon />, path: '/certificates' },
    { text: 'Sign Documents', icon: <DocumentIcon />, path: '/sign' },
    { text: 'Bulk Signing', icon: <BulkIcon />, path: '/bulk-signing' },
    { text: 'Audit Logs', icon: <AssignmentIcon />, path: '/audit-logs' },
    { text: 'Settings', icon: <SettingsIcon />, path: '/settings' }
  ];

  const drawer = (
    <Box>
      <Toolbar>
        <Typography variant="h6" noWrap component="div">
          مصنع التوقيع الرقمي
        </Typography>
      </Toolbar>
      <Divider />
      <List>
        {menuItems.map((item) => (
          <ListItem button key={item.text} component="a" href={item.path}>
            <ListItemIcon>{item.icon}</ListItemIcon>
            <ListItemText primary={t(item.text)} />
          </ListItem>
        ))}
      </List>
    </Box>
  );

  return (
    <Box sx={{ display: 'flex' }}>
      <AppBar
        position="fixed"
        sx={{
          width: { sm: `calc(100% - 240px)` },
          ml: { sm: `240px` },
        }}
      >
        <Toolbar>
          <IconButton
            color="inherit"
            edge="start"
            onClick={handleDrawerToggle}
            sx={{ mr: 2, display: { sm: 'none' } }}
          >
            <MenuIcon />
          </IconButton>
          
          <Typography variant="h6" noWrap component="div" sx={{ flexGrow: 1 }}>
            Digital Signature Factory
          </Typography>

          {/* Language Switcher */}
          <FormControlLabel
            control={
              <Switch
                checked={!isRTL}
                onChange={handleLanguageChange}
                color="default"
              />
            }
            label={<LanguageIcon />}
            sx={{ mr: 2 }}
          />

          {/* Notifications */}
          <IconButton color="inherit">
            <Badge badgeContent={3} color="error">
              <NotificationsIcon />
            </Badge>
          </IconButton>
        </Toolbar>
      </AppBar>

      {/* Mobile Drawer */}
      <Box
        component="nav"
        sx={{ width: { sm: 240 }, flexShrink: { sm: 0 } }}
      >
        <Drawer
          variant="temporary"
          open={mobileOpen}
          onClose={handleDrawerToggle}
          ModalProps={{ keepMounted: true }}
          sx={{
            display: { xs: 'block', sm: 'none' },
            '& .MuiDrawer-paper': { boxSizing: 'border-box', width: 240 }
          }}
        >
          {drawer}
        </Drawer>
        
        <Drawer
          variant="permanent"
          sx={{
            display: { xs: 'none', sm: 'block' },
            '& .MuiDrawer-paper': { boxSizing: 'border-box', width: 240 }
          }}
          open
        >
          {drawer}
        </Drawer>
      </Box>
    </Box>
  );
}

export default Navbar;
