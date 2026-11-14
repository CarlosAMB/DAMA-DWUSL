/**
 * Base de datos local (SQLite). Es la fuente de verdad de la app:
 * todo se lee y se escribe aqui, y el servicio de sincronizacion
 * se encarga de enviar/recibir cambios con la API cuando hay internet.
 *
 * Columnas de control en cada tabla sincronizable:
 *  - dirty: 1 si el registro cambio localmente y falta enviarlo al servidor
 *  - client_updated_at: hora (UTC) de la ultima modificacion en este dispositivo
 *  - deleted_at: eliminacion logica pendiente de sincronizar
 *  - sync_error: mensaje si el servidor rechazo el registro
 */
const DATABASE_VERSION = 1;

const SYNC_COLUMNS = `
  client_updated_at TEXT,
  updated_at TEXT,
  deleted_at TEXT,
  dirty INTEGER NOT NULL DEFAULT 0,
  sync_error TEXT`;

export async function migrateDatabase(db) {
  await db.execAsync('PRAGMA journal_mode = WAL;');

  const row = await db.getFirstAsync('PRAGMA user_version');
  let version = row?.user_version ?? 0;
  if (version >= DATABASE_VERSION) return;

  if (version === 0) {
    await db.execAsync(`
      CREATE TABLE IF NOT EXISTS projects (
        id TEXT PRIMARY KEY NOT NULL,
        name TEXT NOT NULL,
        description TEXT,
        color TEXT DEFAULT '#6366F1',
        status TEXT DEFAULT 'activo',
        due_date TEXT,
        ${SYNC_COLUMNS}
      );

      CREATE TABLE IF NOT EXISTS tags (
        id TEXT PRIMARY KEY NOT NULL,
        name TEXT NOT NULL,
        color TEXT DEFAULT '#10B981',
        ${SYNC_COLUMNS}
      );

      CREATE TABLE IF NOT EXISTS events (
        id TEXT PRIMARY KEY NOT NULL,
        event_type_id INTEGER,
        title TEXT NOT NULL,
        description TEXT,
        location TEXT,
        start_at TEXT NOT NULL,
        end_at TEXT,
        all_day INTEGER DEFAULT 0,
        google_event_id TEXT,
        ${SYNC_COLUMNS}
      );
      CREATE INDEX IF NOT EXISTS idx_events_start ON events(start_at);

      CREATE TABLE IF NOT EXISTS tasks (
        id TEXT PRIMARY KEY NOT NULL,
        project_id TEXT,
        event_id TEXT,
        title TEXT NOT NULL,
        description TEXT,
        priority TEXT DEFAULT 'media',
        status TEXT DEFAULT 'pendiente',
        due_date TEXT,
        completed_at TEXT,
        tag_ids TEXT DEFAULT '[]',
        ${SYNC_COLUMNS}
      );
      CREATE INDEX IF NOT EXISTS idx_tasks_due ON tasks(due_date);

      CREATE TABLE IF NOT EXISTS subtasks (
        id TEXT PRIMARY KEY NOT NULL,
        task_id TEXT NOT NULL,
        title TEXT NOT NULL,
        is_done INTEGER DEFAULT 0,
        position INTEGER DEFAULT 0,
        ${SYNC_COLUMNS}
      );

      CREATE TABLE IF NOT EXISTS reminders (
        id TEXT PRIMARY KEY NOT NULL,
        remindable_type TEXT NOT NULL,
        remindable_id TEXT NOT NULL,
        remind_at TEXT NOT NULL,
        minutes_before INTEGER,
        ${SYNC_COLUMNS}
      );

      CREATE TABLE IF NOT EXISTS event_types (
        id INTEGER PRIMARY KEY NOT NULL,
        name TEXT NOT NULL,
        color TEXT,
        icon TEXT,
        is_active INTEGER DEFAULT 1
      );

      CREATE TABLE IF NOT EXISTS announcements (
        id INTEGER PRIMARY KEY NOT NULL,
        title TEXT,
        body TEXT,
        level TEXT,
        published_at TEXT
      );

      CREATE TABLE IF NOT EXISTS meta (
        key TEXT PRIMARY KEY NOT NULL,
        value TEXT
      );
    `);
    version = 1;
  }

  await db.execAsync(`PRAGMA user_version = ${DATABASE_VERSION}`);
}

/** Borra todos los datos locales (al cerrar sesion o eliminar la cuenta). */
export async function clearDatabase(db) {
  await db.execAsync(`
    DELETE FROM projects; DELETE FROM tags; DELETE FROM events; DELETE FROM tasks;
    DELETE FROM subtasks; DELETE FROM reminders; DELETE FROM announcements; DELETE FROM meta;
  `);
}
