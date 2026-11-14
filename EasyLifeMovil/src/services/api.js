import axios from 'axios';
import * as SecureStore from 'expo-secure-store';
import { API_URL } from '../config';

export const TOKEN_KEY = 'easylife_token';

/** Cliente HTTP para la API Laravel. Agrega el token Sanctum en cada peticion. */
const api = axios.create({
  baseURL: API_URL,
  timeout: 15000,
  headers: { Accept: 'application/json' },
});

let onUnauthorized = null;
/** El AuthContext registra aqui que hacer cuando el token expira (cerrar sesion). */
export const setUnauthorizedHandler = (fn) => {
  onUnauthorized = fn;
};

api.interceptors.request.use(async (config) => {
  const token = await SecureStore.getItemAsync(TOKEN_KEY);
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const status = error.response?.status;
    if ((status === 401 || status === 403) && error.config?.url !== '/auth/login' && onUnauthorized) {
      onUnauthorized(error.response?.data?.message);
    }
    return Promise.reject(error);
  }
);

/** Convierte un error de axios en un mensaje entendible para el usuario. */
export function errorMessage(error) {
  if (!error.response) {
    // Errores que no son de red (ej. SQLite) se muestran tal cual
    if (!error.isAxiosError) return error.message || 'Ocurrió un error inesperado.';
    return 'No se pudo conectar con el servidor. Revisa tu conexión a internet.';
  }
  const data = error.response.data;
  if (data?.errors) {
    return Object.values(data.errors).flat().join('\n');
  }
  return data?.message || 'Ocurrió un error inesperado.';
}

export default api;
