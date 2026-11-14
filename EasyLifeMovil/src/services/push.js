import { Platform } from 'react-native';
import * as Notifications from 'expo-notifications';
import * as SecureStore from 'expo-secure-store';
import Constants, { ExecutionEnvironment } from 'expo-constants';
import api from './api';

/**
 * Notificaciones push con Firebase Cloud Messaging.
 *  - El telefono obtiene su token FCM y lo registra en Laravel (POST /devices).
 *  - El administrador envia avisos desde el panel web y Laravel los manda por FCM.
 *
 * Solo funciona en el APK (EAS Build con google-services.json). En Expo Go no hay push:
 * ahi se siguen usando las notificaciones locales (recordatorios y resumen diario).
 */
const PUSH_KEY = 'easylife_push_token';

export const pushSupported = () => Platform.OS === 'android' && Constants.executionEnvironment !== ExecutionEnvironment.StoreClient;

export async function registerForPush() {
  if (!pushSupported()) return null;
  try {
    let { status } = await Notifications.getPermissionsAsync();
    if (status !== 'granted') ({ status } = await Notifications.requestPermissionsAsync());
    if (status !== 'granted') return null;

    const { data: token } = await Notifications.getDevicePushTokenAsync();
    await api.post('/devices', { token, platform: Platform.OS });
    await SecureStore.setItemAsync(PUSH_KEY, token);
    return token;
  } catch (e) {
    console.warn('[push] no se pudo registrar:', e.message);
    return null;
  }
}

/** Al cerrar sesion: el telefono deja de recibir push de esa cuenta. */
export async function unregisterPush() {
  const token = await SecureStore.getItemAsync(PUSH_KEY);
  if (!token) return;
  try {
    await api.delete('/devices', { data: { token } });
  } catch {
    /* sin conexion: el servidor lo limpiara cuando FCM indique que no es valido */
  }
  await SecureStore.deleteItemAsync(PUSH_KEY);
}
