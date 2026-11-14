import * as WebBrowser from 'expo-web-browser';
import * as Linking from 'expo-linking';
import api from './api';

/**
 * Conexion con Google Calendar / Google Tasks.
 * El servidor Laravel hace todo el flujo OAuth y guarda los tokens; la app solo:
 *  1. Pide la URL de autorizacion (indicando a donde regresar: exp://... en Expo Go, easylife://... en un build)
 *  2. La abre en el navegador; al terminar, el servidor redirige de vuelta a la app con ?status=ok|cancelled|error
 *
 * En el emulador ejecutar antes:  adb reverse tcp:8000 tcp:8000
 * (Google solo acepta "localhost" como direccion de regreso, no 10.0.2.2)
 */
export async function connectGoogle() {
  const returnUrl = Linking.createURL('google');
  const { data } = await api.get('/google/auth-url', { params: { return_url: returnUrl } });

  const result = await WebBrowser.openAuthSessionAsync(data.url, returnUrl);
  if (result.type !== 'success') return 'cancelled';

  const { queryParams } = Linking.parse(result.url);
  return queryParams?.status || 'error';
}

/** Sincroniza con Google en el servidor (luego hay que llamar a syncNow para traer los cambios al telefono). */
export const syncGoogle = async () => (await api.post('/google/sync', null, { timeout: 60000 })).data;

/** Desconecta la cuenta (el servidor revoca el acceso en Google). Devuelve el usuario actualizado. */
export const disconnectGoogle = async () => (await api.delete('/google')).data.user;
