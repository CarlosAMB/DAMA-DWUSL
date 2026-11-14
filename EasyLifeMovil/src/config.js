import { Platform } from 'react-native';

/**
 * URL de la API Laravel.
 *  - Emulador Android: 10.0.2.2 apunta al "localhost" de tu PC.
 *  - Celular fisico: usa la IP local de tu PC (ej. http://192.168.1.10:8000/api/v1)
 *    y levanta Laravel con: php artisan serve --host=0.0.0.0
 */
//  - Servidor en linea (Render) o tunel: definir EXPO_PUBLIC_API_URL en EasyLifeMovil/.env
//    ej. EXPO_PUBLIC_API_URL=https://easylife.onrender.com
const LOCAL_HOST = Platform.OS === 'android' ? 'http://10.0.2.2:8000' : 'http://127.0.0.1:8000';
const HOST = (process.env.EXPO_PUBLIC_API_URL || LOCAL_HOST).replace(/\/+$/, '');

/**
 * Proyecto de Firebase (Authentication, Firestore y Cloud Messaging).
 * Valores de: Consola de Firebase > Configuracion del proyecto > Tus apps > App web.
 * No son secretos (la seguridad la dan las reglas de Firestore), pero se dejan en .env para no fijarlos en el codigo.
 */
export const FIREBASE_CONFIG = {
  apiKey: process.env.EXPO_PUBLIC_FIREBASE_API_KEY,
  authDomain: process.env.EXPO_PUBLIC_FIREBASE_AUTH_DOMAIN,
  projectId: process.env.EXPO_PUBLIC_FIREBASE_PROJECT_ID,
  storageBucket: process.env.EXPO_PUBLIC_FIREBASE_STORAGE_BUCKET,
  messagingSenderId: process.env.EXPO_PUBLIC_FIREBASE_MESSAGING_SENDER_ID,
  appId: process.env.EXPO_PUBLIC_FIREBASE_APP_ID,
};

/** Sin configuracion de Firebase la app usa el inicio de sesion directo con Laravel. */
export const FIREBASE_ENABLED = !!(FIREBASE_CONFIG.apiKey && FIREBASE_CONFIG.projectId && FIREBASE_CONFIG.appId);

export const API_URL = `${HOST}/api/v1`;
export const PRIVACY_URL = `${HOST}/privacidad`;

/** Cada cuanto se sincroniza automaticamente (ms) mientras la app esta abierta. */
export const SYNC_INTERVAL = 5 * 60 * 1000;
