import React, { createContext, useCallback, useContext, useEffect, useState } from 'react';
import * as SecureStore from 'expo-secure-store';
import { Alert } from 'react-native';
import { useSQLiteContext } from 'expo-sqlite';
import api, { TOKEN_KEY, errorMessage as apiErrorMessage, setUnauthorizedHandler } from '../services/api';
import {
  currentFirebaseUser,
  firebaseChangePassword,
  firebaseDeleteCurrentUser,
  firebaseErrorMessage,
  firebaseResetPassword,
  firebaseSignIn,
  firebaseSignOut,
  firebaseSignUp,
  isCredentialError,
  reauthenticate,
} from '../services/firebase';
import { registerForPush, unregisterPush } from '../services/push';
import { clearDatabase } from '../db/schema';
import { FIREBASE_ENABLED } from '../config';
import { loadDataKey, saveDataKey } from '../utils/crypto';
import { useTheme } from './ThemeContext';

/**
 * Sesion del usuario.
 *
 * Con Firebase configurado (lo normal):
 *   1. Firebase Authentication valida el correo y la contraseña (la guarda cifrada).
 *   2. La app envia el ID token de Firebase a Laravel (POST /auth/firebase).
 *   3. Laravel lo verifica y entrega su token Sanctum + la llave de cifrado del usuario.
 * Sin Firebase (EXPO_PUBLIC_FIREBASE_* vacios) se usa el inicio de sesion directo con Laravel.
 *
 * El token, el perfil y la llave de cifrado se guardan en SecureStore (Keystore de Android),
 * asi la app puede abrirse sin internet con la ultima sesion.
 */
const USER_KEY = 'easylife_user';
const DEVICE_NAME = 'EasyLife móvil';

/** Mensaje entendible para errores de Firebase o de la API. */
export const errorMessage = (e) => firebaseErrorMessage(e) || apiErrorMessage(e);

export const AuthContext = createContext();

export const AuthProvider = ({ children }) => {
  const db = useSQLiteContext();
  const { setTheme } = useTheme();
  const [user, setUserState] = useState(null);
  const [loading, setLoading] = useState(true);

  const setUser = useCallback(
    async (u) => {
      setUserState(u);
      if (u) {
        await SecureStore.setItemAsync(USER_KEY, JSON.stringify(u));
        if (u.preferences?.theme) setTheme(u.preferences.theme === 'dark');
      } else {
        await SecureStore.deleteItemAsync(USER_KEY);
      }
    },
    [setTheme]
  );

  const clearSession = useCallback(async () => {
    await SecureStore.deleteItemAsync(TOKEN_KEY);
    await SecureStore.deleteItemAsync(USER_KEY);
    await saveDataKey(null);
    await clearDatabase(db);
    await firebaseSignOut().catch(() => {});
    setUserState(null);
  }, [db]);

  // Restaurar sesion guardada al abrir la app
  useEffect(() => {
    (async () => {
      try {
        const token = await SecureStore.getItemAsync(TOKEN_KEY);
        const saved = await SecureStore.getItemAsync(USER_KEY);
        const hasKey = await loadDataKey();
        if (token && saved) {
          setUserState(JSON.parse(saved));
          // Sesiones anteriores a la llave de cifrado: se pide al servidor (si hay internet)
          if (!hasKey) {
            api
              .get('/auth/me')
              .then(({ data }) => saveDataKey(data.data_key))
              .catch(() => {});
          }
        }
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  // Si el servidor rechaza el token (expirado o cuenta desactivada) se cierra la sesion
  useEffect(() => {
    setUnauthorizedHandler((message) => {
      clearSession();
      Alert.alert('Sesión finalizada', message || 'Vuelve a iniciar sesión.');
    });
  }, [clearSession]);

  const handleAuth = async (data) => {
    await clearDatabase(db); // por si antes habia otra cuenta en el dispositivo
    await SecureStore.setItemAsync(TOKEN_KEY, data.token);
    await saveDataKey(data.data_key);
    await setUser(data.user);
    registerForPush(); // en segundo plano (solo en el APK)
  };

  /** Envia el ID token de Firebase a Laravel. Si Laravel lo rechaza se cierra la sesion de Firebase. */
  const exchangeToken = async (firebaseUser, extra, { deleteOnFail = false } = {}) => {
    try {
      const idToken = await firebaseUser.getIdToken();
      const { data } = await api.post('/auth/firebase', { id_token: idToken, device_name: DEVICE_NAME, ...extra });
      return data;
    } catch (e) {
      if (deleteOnFail) await firebaseDeleteCurrentUser().catch(() => {});
      await firebaseSignOut().catch(() => {});
      throw e;
    }
  };

  const login = async (email, password) => {
    if (!FIREBASE_ENABLED) {
      const { data } = await api.post('/auth/login', { email, password, device_name: DEVICE_NAME });
      return handleAuth(data);
    }

    let credential;
    try {
      credential = await firebaseSignIn(email, password);
    } catch (e) {
      if (!isCredentialError(e)) throw e;
      // Cuenta creada antes de usar Firebase (ej. demo@easylife.test): se crea en Firebase y Laravel
      // la vincula solo si la contraseña coincide con la registrada. Si no coincide se deshace.
      try {
        credential = await firebaseSignUp(email, password);
      } catch {
        throw e;
      }
      return handleAuth(await exchangeToken(credential.user, { password }, { deleteOnFail: true }));
    }
    return handleAuth(await exchangeToken(credential.user, { password }));
  };

  const register = async (form) => {
    if (!FIREBASE_ENABLED) {
      const { data } = await api.post('/auth/register', { ...form, device_name: DEVICE_NAME });
      return handleAuth(data);
    }
    const credential = await firebaseSignUp(form.email.trim(), form.password);
    // password: por si ya tenia cuenta en EasyLife antes de Firebase (se vincula si coincide)
    const data = await exchangeToken(
      credential.user,
      { name: form.name, accept_privacy: !!form.accept_privacy, password: form.password },
      { deleteOnFail: true }
    );
    return handleAuth(data);
  };

  /** Envia el correo para restablecer la contraseña (Firebase). */
  const resetPassword = (email) => firebaseResetPassword(email);

  const changePassword = async ({ current_password, password, password_confirmation }) => {
    if (FIREBASE_ENABLED && user?.firebase_uid) {
      await firebaseChangePassword(current_password, password);
      return;
    }
    await api.put('/profile/password', { current_password, password, password_confirmation });
  };

  const logout = async (pendingChanges = 0) => {
    const doLogout = async () => {
      await unregisterPush();
      try {
        await api.post('/auth/logout');
      } catch {
        /* sin conexion: igual se cierra localmente */
      }
      await clearSession();
    };
    if (pendingChanges > 0) {
      Alert.alert(
        'Cambios sin sincronizar',
        `Tienes ${pendingChanges} cambio(s) que no se han enviado al servidor y se perderán. ¿Cerrar sesión de todos modos?`,
        [
          { text: 'Cancelar', style: 'cancel' },
          { text: 'Cerrar sesión', style: 'destructive', onPress: doLogout },
        ]
      );
    } else {
      await doLogout();
    }
  };

  /** Actualiza perfil/preferencias en el servidor (requiere conexion). */
  const updateProfile = async (changes) => {
    const { data } = await api.put('/profile', changes);
    await setUser(data.user);
    return data.user;
  };

  const deleteAccount = async (password) => {
    if (FIREBASE_ENABLED && user?.firebase_uid && currentFirebaseUser()) {
      // Firebase exige un inicio de sesion reciente: se confirma la contraseña y se envia un token nuevo
      const fbUser = await reauthenticate(password);
      const idToken = await fbUser.getIdToken(true);
      await unregisterPush();
      await api.delete('/profile', { data: { id_token: idToken } });
      await firebaseDeleteCurrentUser().catch(() => {});
    } else {
      await api.delete('/profile', { data: { password } });
    }
    await clearSession();
  };

  return (
    <AuthContext.Provider
      value={{
        user,
        loading,
        login,
        register,
        logout,
        setUser,
        updateProfile,
        deleteAccount,
        changePassword,
        resetPassword,
        firebaseEnabled: FIREBASE_ENABLED,
        errorMessage,
      }}
    >
      {children}
    </AuthContext.Provider>
  );
};

export const useAuth = () => useContext(AuthContext);
