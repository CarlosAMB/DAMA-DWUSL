import { useCallback, useEffect, useRef, useState } from 'react';
import { useFocusEffect } from '@react-navigation/native';
import { useSQLiteContext } from 'expo-sqlite';
import { useSync } from '../context/SyncContext';

/**
 * Ejecuta una consulta a SQLite y la repite cuando:
 *  - la pantalla vuelve a tener el foco
 *  - cambian los datos (version del SyncContext)
 *  - cambian las dependencias indicadas
 *
 * Uso: const { data, loading, reload } = useLocalQuery((db) => getTasks(db, filtros), [filtros]);
 */
export function useLocalQuery(queryFn, deps = [], initial = null) {
  const db = useSQLiteContext();
  const { version } = useSync();
  const [data, setData] = useState(initial);
  const [loading, setLoading] = useState(true);
  const requestId = useRef(0);

  const load = useCallback(async () => {
    const id = ++requestId.current;
    try {
      const result = await queryFn(db);
      // Si mientras tanto se lanzo otra consulta, esta respuesta ya es vieja
      if (id === requestId.current) setData(result);
    } catch (e) {
      console.warn('Error en consulta local', e);
    } finally {
      if (id === requestId.current) setLoading(false);
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [db, version, ...deps]);

  // Recarga cuando cambian los datos o los filtros
  useEffect(() => {
    load();
  }, [load]);

  // Recarga al volver a la pantalla
  useFocusEffect(
    useCallback(() => {
      load();
    }, [load])
  );

  return { data, loading, reload: load };
}
