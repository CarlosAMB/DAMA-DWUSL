import api from './api';
import { ENCRYPTED_FIELDS, SYNC_TABLES, TABLE_FIELDS, fromDb, getMeta, setMeta } from '../db/repository';
import { encryptField } from '../utils/crypto';
import { normalizeIso, secondsOf } from '../utils/dates';

/**
 * Sincronizacion offline <-> servidor.
 *
 * 1. Junta los registros locales con dirty = 1 y los envia a POST /sync.
 * 2. El servidor aplica los cambios (gana el mas reciente) y devuelve
 *    todo lo que cambio desde la ultima sincronizacion.
 * 3. Se marcan como sincronizados los registros enviados y se guardan
 *    localmente los cambios recibidos.
 */

const DATE_FIELDS = ['start_at', 'end_at', 'due_date', 'completed_at', 'remind_at', 'client_updated_at', 'updated_at', 'deleted_at'];

async function collectChanges(db) {
  const changes = {};
  const sent = {};
  for (const table of SYNC_TABLES) {
    const list = (await db.getAllAsync(`SELECT * FROM ${table} WHERE dirty = 1`)).map(fromDb);
    sent[table] = list.map((r) => ({ id: r.id, client_updated_at: r.client_updated_at, deleted: !!r.deleted_at }));
    changes[table] = list.map((r) => {
      const payload = { id: r.id, client_updated_at: r.client_updated_at, deleted_at: r.deleted_at };
      TABLE_FIELDS[table].forEach((f) => {
        payload[f] = r[f];
      });
      return payload;
    });
  }
  return { changes, sent };
}

/** Guarda en SQLite un registro recibido del servidor. */
async function applyServerRecord(db, table, record) {
  const local = await db.getFirstAsync(`SELECT dirty, client_updated_at FROM ${table} WHERE id = ?`, [record.id]);

  // Si el usuario lo modifico localmente despues, se conserva el cambio local
  if (local?.dirty && secondsOf(local.client_updated_at) > secondsOf(normalizeIso(record.client_updated_at))) {
    return;
  }

  if (record.deleted_at) {
    await db.runAsync(`DELETE FROM ${table} WHERE id = ?`, [record.id]);
    return;
  }

  const fields = TABLE_FIELDS[table];
  const values = fields.map((f) => {
    let v = record[f];
    if (f === 'tag_ids') return JSON.stringify(v || []);
    if (typeof v === 'boolean') return v ? 1 : 0;
    if (ENCRYPTED_FIELDS.includes(f)) return encryptField(v ?? null); // se guarda cifrado en SQLite
    if (DATE_FIELDS.includes(f) && f !== 'due_date') return normalizeIso(v);
    if (f === 'due_date' && v) return table === 'projects' ? String(v).slice(0, 10) : normalizeIso(v);
    return v ?? null;
  });

  const cols = ['id', ...fields, 'client_updated_at', 'updated_at', 'deleted_at', 'dirty', 'sync_error'];
  await db.runAsync(`INSERT OR REPLACE INTO ${table} (${cols.join(', ')}) VALUES (${cols.map(() => '?').join(', ')})`, [
    record.id,
    ...values,
    normalizeIso(record.client_updated_at),
    normalizeIso(record.updated_at),
    null,
    0,
    null,
  ]);
}

async function saveCatalogs(db, data) {
  await db.withTransactionAsync(async () => {
    await db.runAsync('DELETE FROM event_types');
    for (const t of data.event_types || []) {
      await db.runAsync('INSERT INTO event_types (id, name, color, icon, is_active) VALUES (?, ?, ?, ?, ?)', [
        t.id,
        t.name,
        t.color,
        t.icon,
        t.is_active ? 1 : 0,
      ]);
    }
    await db.runAsync('DELETE FROM announcements');
    for (const a of data.announcements || []) {
      await db.runAsync('INSERT INTO announcements (id, title, body, level, published_at) VALUES (?, ?, ?, ?, ?)', [
        a.id,
        a.title,
        a.body,
        a.level,
        normalizeIso(a.published_at),
      ]);
    }
  });
}

let inFlight = null;

// Candado: la sincronizacion con Laravel y los cambios que llegan de Firestore nunca escriben a la vez
let lock = Promise.resolve();
function exclusive(fn) {
  const run = lock.then(fn, fn);
  lock = run.catch(() => {});
  return run;
}

/**
 * Aplica registros que llegaron en tiempo real desde Firestore (ya descifrados).
 * Usa la misma regla que /sync: si el cambio local es mas reciente, se conserva.
 * @returns {Promise<number>} cuantos se procesaron
 */
export function applyRemoteRecords(db, table, records) {
  if (!records.length || !TABLE_FIELDS[table]) return Promise.resolve(0);
  return exclusive(async () => {
    await db.withTransactionAsync(async () => {
      for (const record of records) await applyServerRecord(db, table, record);
    });
    return records.length;
  });
}

/**
 * Ejecuta una sincronizacion completa.
 * Si ya hay una en curso devuelve la misma promesa: nunca corren dos a la vez
 * (dos transacciones simultaneas en SQLite se anularian entre si).
 * @returns {Promise<{user: object, pushed: number, received: number, errors: array}>}
 */
export function synchronize(db) {
  if (!inFlight) {
    inFlight = exclusive(() => runSync(db)).finally(() => {
      inFlight = null;
    });
  }
  return inFlight;
}

async function runSync(db) {
  // Preferencias cambiadas sin conexion: se envian primero
  const pendingPrefs = await getMeta(db, 'pending_preferences');
  if (pendingPrefs) {
    await api.put('/profile', { preferences: JSON.parse(pendingPrefs) });
    await db.runAsync("DELETE FROM meta WHERE key = 'pending_preferences'");
  }

  const lastSyncedAt = await getMeta(db, 'last_synced_at');
  const { changes, sent } = await collectChanges(db);

  // Tiempo de espera mayor: el servidor tambien puede sincronizar con Google en esta llamada
  const { data } = await api.post('/sync', { last_synced_at: lastSyncedAt, changes }, { timeout: 60000 });

  let received = 0;
  await db.withTransactionAsync(async () => {
    // 1) Marcar lo enviado como sincronizado (si no cambio mientras tanto)
    for (const table of SYNC_TABLES) {
      for (const s of sent[table]) {
        if (s.deleted) {
          await db.runAsync(`DELETE FROM ${table} WHERE id = ? AND client_updated_at = ?`, [s.id, s.client_updated_at]);
        } else {
          await db.runAsync(`UPDATE ${table} SET dirty = 0 WHERE id = ? AND client_updated_at = ?`, [s.id, s.client_updated_at]);
        }
      }
    }

    // 2) Registros rechazados por el servidor (validacion): se guarda el motivo
    for (const err of data.push?.errors || []) {
      const msg = Object.values(err.messages || {})
        .flat()
        .join(' ');
      await db.runAsync(`UPDATE ${err.table} SET sync_error = ?, dirty = 0 WHERE id = ?`, [msg, err.id]);
    }

    // 3) Aplicar cambios que vienen del servidor (orden por dependencias)
    for (const table of SYNC_TABLES) {
      for (const record of data.changes?.[table] || []) {
        await applyServerRecord(db, table, record);
        received++;
      }
    }

    await setMeta(db, 'last_synced_at', data.server_time);
    await setMeta(db, 'last_sync_local', new Date().toISOString());
  });

  await saveCatalogs(db, data);

  const pushed = Object.values(sent).reduce((n, list) => n + list.length, 0);
  return { user: data.user, pushed, received, errors: data.push?.errors || [], google: data.google };
}
