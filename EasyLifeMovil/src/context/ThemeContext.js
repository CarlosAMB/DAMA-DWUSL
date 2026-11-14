import React, { createContext, useContext, useEffect, useMemo, useState } from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';

export const lightColors = {
  background: '#F1F5F9',
  surface: '#FFFFFF',
  card: '#FFFFFF',
  primary: '#4F46E5',
  primarySoft: '#EEF2FF',
  primaryText: '#FFFFFF',
  text: '#0F172A',
  textSecondary: '#64748B',
  border: '#E2E8F0',
  input: '#FFFFFF',
  danger: '#EF4444',
  success: '#10B981',
  warning: '#F59E0B',
  info: '#0EA5E9',
};

export const darkColors = {
  background: '#0B1120',
  surface: '#111827',
  card: '#1E293B',
  primary: '#818CF8',
  primarySoft: '#1E1B4B',
  primaryText: '#0B1120',
  text: '#F1F5F9',
  textSecondary: '#94A3B8',
  border: '#334155',
  input: '#1E293B',
  danger: '#F87171',
  success: '#34D399',
  warning: '#FBBF24',
  info: '#38BDF8',
};

export const PRIORITY_COLORS = {
  baja: '#94A3B8',
  media: '#0EA5E9',
  alta: '#F59E0B',
  urgente: '#EF4444',
};

export const ThemeContext = createContext();

export const ThemeProvider = ({ children }) => {
  const [darkMode, setDarkMode] = useState(false);

  useEffect(() => {
    AsyncStorage.getItem('darkMode').then((v) => v !== null && setDarkMode(v === 'true'));
  }, []);

  const setTheme = async (dark) => {
    setDarkMode(dark);
    await AsyncStorage.setItem('darkMode', String(dark));
  };

  const value = useMemo(
    () => ({ darkMode, colors: darkMode ? darkColors : lightColors, setTheme, toggleTheme: () => setTheme(!darkMode) }),
    [darkMode]
  );

  return <ThemeContext.Provider value={value}>{children}</ThemeContext.Provider>;
};

export const useTheme = () => useContext(ThemeContext);
