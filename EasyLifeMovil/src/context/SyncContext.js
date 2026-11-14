import React, { createContext, useCallback, useContext, useEffect, useRef, useState } from 'react';
import { AppState } from 'react-native';
import NetInfo from '@react-native-community/netinfo';
import { useSQLiteContext } from 'expo-sqlite';
import { useAuth } from './AuthContext';
import { onAuthStateChanged } from 'firebase/auth';
import { applyRemoteRecords, synchronize } from '../services/sync';
import { auth, subscribeToUserData } from '../services/firebase';
import { rescheduleAll } from '../services/notifications';
import { countPendingChanges, getMeta } from '../db/repository';
import { errorMessage } from '../services/api';
import { SYNC_INTERVAL } from '../config';

/**
 * Coordina la sincronizacion y avisa a las pantallas cuando los datos cambian.
 *  - version: numero que aumenta con cada cambio local o sincronizacion (las pantallas recargan)
 *  - notifyChange(): llamar despues de guardar algo en SQLite
 */
export const SyncContext = createContext();

export const SyncProvider = ({ children }) => {
  const db = useSQLiteContext();
  const { user, setUser } = useAuth();
  const [version, setVersion] = useState(0);
  const [isOnline, setIsOnline] = useState(true);
  const [syncing, setSyncing] = useState(false);
  const [lastSync, setLastSync] = useState(null);
  const [pending, setPending] = useState(0);
  const [lastError, setLastError] = useState(null);
  const [googleError, setGoogleError] = useState(null);
  const [realtime, setRealtime] = useState(false); // escuchando cambios de Firestore

  const running = useRef(false);
  const debounce = useRef(null);
  const prefsRef = useRef({});
  prefsRef.current = user?.preferences || {};

  const refreshStatus = useCallback(async () => {
    setPending(await countPendingChanges(db));
    setLastSync(await getMeta(db, 'last_sync_local'));
  }, [db]);

  const syncNow = useCallback(
    async ({ silent = true } = {}) => {
      if (!user || running.current) return null;
      running.current = true; // se marca ANTES de cualquier await para evitar dos sincronizaciones a la vez
      const net = await NetInfo.fetch();
      if (!net.isConnected) {
        running.current = false;
        setIsOnline(false);
        if (!silent) throw new Error('Sin conexión. Tus cambios se guardaron y se enviarán cuando vuelva el internet.');
        return null;
      }

      setSyncing(true);
      try {
        const result = await synchronize(db);
        if (result.user) await setUser(result.user);
        setLastError(null);
        // null = no se sincronizo con Google en esta llamada (se conserva el estado anterior)
        if (result.google) setGoogleError(result.google.ok ? null : result.google.message);
        setVersion((v) => v + 1);
        await rescheduleAll(db, result.user?.preferences || prefsRef.current);
        return result;
      } catch (e) {
        const msg = errorMessage(e);
        setLastError(msg);
        if (!silent) throw new Error(msg);
        return null;
      } finally {
        running.current = false;
        setSyncing(false);
        refreshStatus();
      }
    },
    [db, user, setUser, refreshStatus]
  );

  /** Llamar despues de cada cambio local: refresca pantallas y sincroniza en 2 s. */
  const notifyChange = useCallback(() => {
    setVersion((v) => v + 1);
    refreshStatus();
    rescheduleAll(db, prefsRef.current);
    clearTimeout(debounce.current);
    debounce.current = setTimeout(() => syncNow(), 2000);
  }, [db, syncNow, refreshStatus]);

  // Tiempo real con Firestore: los cambios hechos en otro dispositivo (o que llegan de Google)
  // aparecen al instante sin esperar la siguiente sincronizacion.
  useEffect(() => {
    if (!user?.firebase_uid || !auth) return undefined;
    let unsubData = () => {};
    let cancelled = false;

    const unsubAuth = onAuthStateChanged(auth, async (fbUser) => {
      unsubData();
      unsubData = () => {};
      setRealtime(false);
      if (!fbUser || fbUser.uid !== user.firebase_uid) return;

      const since = await getMeta(db, 'last_synced_at');
      if (cancelled) return;
      setRealtime(true);
      unsubData = subscribeToUserData(fbUser.uid, since, async (table, records) => {
        try {
          await applyRemoteRecords(db, table, records);
          setVersion((v) => v + 1);
          refreshStatus();
          rescheduleAll(db, prefsRef.current);
        } catch (e) {
          console.warn('[firestore] no se pudo aplicar:', e.message);
        }
      });
    });

    return () => {
      cancelled = true;
      unsubAuth();
      unsubData();
      setRealtime(false);
    };
  }, [db, user?.firebase_uid, refreshStatus]);

  // Sincroniza al iniciar sesion, al recuperar internet, al volver a la app y cada X minutos
  useEffect(() => {
    if (!user) return undefined;
    refreshStatus();
    syncNow();

    const unsubNet = NetInfo.addEventListener((state) => {
      const online = !!state.isConnected;
      setIsOnline((prev) => {
        if (!prev && online) syncNow();
        return online;
      });
    });
    const appSub = AppState.addEventListener('change', (s) => s === 'active' && syncNow());
    const timer = setInterval(() => syncNow(), SYNC_INTERVAL);

    return () => {
      unsubNet();
      appSub.remove();
      clearInterval(timer);
      clearTimeout(debounce.current);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [user?.id]);

  return (
    <SyncContext.Provider
      value={{ version, isOnline, syncing, lastSync, pending, lastError, googleError, setGoogleError, realtime, syncNow, notifyChange }}
    >
      {children}
    </SyncContext.Provider>
  );
};

export const useSync = () => useContext(SyncContext);
