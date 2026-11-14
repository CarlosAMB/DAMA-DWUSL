import * as Crypto from 'expo-crypto';
import { nowIso } from '../utils/dates';
import { decryptField, encryptField } from '../utils/crypto';

/**
 * Acceso a datos locales. Todas las pantallas usan estas funciones;
 * nunca llaman a la API directamente (asi la app funciona sin conexion).
 */

/** Campos editables de cada tabla (los mismos que acepta la API). */
export const TABLE_FIELDS = {
  projects: ['name', 'description', 'color', 'status', 'due_date'],
  tags: ['name', 'color'],
  events: ['event_type_id', 'title', 'description', 'location', 'start_at', 'end_at', 'all_day'],
  tasks: ['project_id', 'event_id', 'title', 'description', 'priority', 'status', 'due_date', 'completed_at', 'tag_ids'],
  subtasks: ['task_id', 'title', 'is_done', 'position'],
  reminders: ['remindable_type', 'remindable_id', 'remind_at', 'minutes_before'],
};

export const SYNC_TABLES = Object.keys(TABLE_FIELDS);

const BOOLEAN_FIELDS = ['all_day', 'is_done'];

/** Campos con datos personales que se guardan cifrados en SQLite (AES-256, ver utils/crypto.js). */
export const ENCRYPTED_FIELDS = ['description', 'location'];

/** Convierte un valor JS al formato que se guarda en SQLite. */
function toDb(field, value) {
  if (value === undefined) return null;
  if (BOOLEAN_FIELDS.includes(field)) return value ? 1 : 0;
  if (field === 'tag_ids') return JSON.stringify(value || []);
  if (ENCRYPTED_FIELDS.includes(field)) return encryptField(value);
  return value;
}

/** Convierte una fila de SQLite a objeto JS. */
export function fromDb(row) {
  if (!row) return row;
  const out = { ...row };
  BOOLEAN_FIELDS.forEach((f) => {
    if (f in out) out[f] = !!out[f];
  });
  ENCRYPTED_FIELDS.forEach((f) => {
    if (out[f]) out[f] = decryptField(out[f]);
  });
  if ('tag_ids' in out) {
    try {
      out.tag_ids = JSON.parse(out.tag_ids || '[]');
    } catch {
      out.tag_ids = [];
    }
  }
  return out;
}

const rows = async (db, sql, params = []) => (await db.getAllAsync(sql, params)).map(fromDb);
const first = async (db, sql, params = []) => fromDb(await db.getFirstAsync(sql, params));

// ======================= Escritura generica =======================

/**
 * Crea o actualiza un registro y lo marca como pendiente de sincronizar.
 * @returns {Promise<string>} id del registro
 */
export async function saveRecord(db, table, data) {
  const fields = TABLE_FIELDS[table].filter((f) => f in data);
  const now = nowIso();
  const existing = data.id ? await db.getFirstAsync(`SELECT id FROM ${table} WHERE id = ?`, [data.id]) : null;

  if (existing) {
    const sets = fields.map((f) => `${f} = ?`).join(', ');
    await db.runAsync(`UPDATE ${table} SET ${sets}${sets ? ',' : ''} client_updated_at = ?, dirty = 1, sync_error = NULL WHERE id = ?`, [
      ...fields.map((f) => toDb(f, data[f])),
      now,
      data.id,
    ]);
    return data.id;
  }

  const id = data.id || Crypto.randomUUID();
  const cols = ['id', ...fields, 'client_updated_at', 'dirty'];
  await db.runAsync(`INSERT INTO ${table} (${cols.join(', ')}) VALUES (${cols.map(() => '?').join(', ')})`, [
    id,
    ...fields.map((f) => toDb(f, data[f])),
    now,
    1,
  ]);
  return id;
}

/** Eliminacion logica (se envia al servidor y luego se borra fisicamente). */
export async function deleteRecord(db, table, id) {
  const now = nowIso();
  await db.runAsync(`UPDATE ${table} SET deleted_at = ?, client_updated_at = ?, dirty = 1 WHERE id = ?`, [now, now, id]);
}

// ======================= Catalogos =======================

export const getEventTypes = (db) => rows(db, 'SELECT * FROM event_types WHERE is_active = 1 ORDER BY name');
export const getAllEventTypes = (db) => rows(db, 'SELECT * FROM event_types ORDER BY name');
export const getAnnouncements = (db) => rows(db, 'SELECT * FROM announcements ORDER BY published_at DESC');

// ======================= Eventos =======================

const EVENT_SELECT = `SELECT e.*, t.name AS type_name, t.color AS type_color, t.icon AS type_icon
  FROM events e LEFT JOIN event_types t ON t.id = e.event_type_id`;

/** Eventos que se cruzan con el rango [from, to] (ISO UTC). */
export const getEventsBetween = (db, fromIso, toIso) =>
  rows(
    db,
    `${EVENT_SELECT}
    WHERE e.deleted_at IS NULL AND e.start_at <= ? AND COALESCE(e.end_at, e.start_at) >= ?
    ORDER BY e.start_at`,
    [toIso, fromIso]
  );

export const getEvent = (db, id) => first(db, `${EVENT_SELECT} WHERE e.id = ?`, [id]);

export const getUpcomingEvents = (db, limit = 5) =>
  rows(db, `${EVENT_SELECT} WHERE e.deleted_at IS NULL AND COALESCE(e.end_at, e.start_at) >= ? ORDER BY e.start_at LIMIT ?`, [
    nowIso(),
    limit,
  ]);

export async function deleteEvent(db, id) {
  await deleteRecord(db, 'events', id);
  // Las tareas asignadas quedan sin evento; los recordatorios del evento se eliminan
  const tasks = await db.getAllAsync('SELECT id FROM tasks WHERE event_id = ? AND deleted_at IS NULL', [id]);
  for (const t of tasks) await saveRecord(db, 'tasks', { id: t.id, event_id: null });
  await deleteRemindersOf(db, 'event', id);
}

// ======================= Tareas =======================

const TASK_SELECT = `SELECT tk.*, p.name AS project_name, p.color AS project_color,
    (SELECT COUNT(*) FROM subtasks s WHERE s.task_id = tk.id AND s.deleted_at IS NULL) AS subtasks_total,
    (SELECT COUNT(*) FROM subtasks s WHERE s.task_id = tk.id AND s.deleted_at IS NULL AND s.is_done = 1) AS subtasks_done
  FROM tasks tk LEFT JOIN projects p ON p.id = tk.project_id AND p.deleted_at IS NULL`;

const PRIORITY_ORDER = `CASE tk.priority WHEN 'urgente' THEN 0 WHEN 'alta' THEN 1 WHEN 'media' THEN 2 ELSE 3 END`;

/**
 * Lista de tareas con filtros:
 * { status: 'pendientes'|'completadas'|'todas', priority, projectId, tagId, eventId, search }
 */
export async function getTasks(db, filters = {}) {
  const where = ['tk.deleted_at IS NULL'];
  const params = [];

  if (filters.status === 'pendientes') where.push("tk.status != 'completada'");
  if (filters.status === 'completadas') where.push("tk.status = 'completada'");
  if (filters.priority) {
    where.push('tk.priority = ?');
    params.push(filters.priority);
  }
  if (filters.projectId) {
    where.push('tk.project_id = ?');
    params.push(filters.projectId);
  }
  if (filters.eventId) {
    where.push('tk.event_id = ?');
    params.push(filters.eventId);
  }
  if (filters.tagId) {
    where.push('tk.tag_ids LIKE ?');
    params.push(`%"${filters.tagId}"%`);
  }
  if (filters.search) {
    where.push('tk.title LIKE ?');
    params.push(`%${filters.search}%`);
  }

  return rows(
    db,
    `${TASK_SELECT} WHERE ${where.join(' AND ')}
    ORDER BY (tk.status = 'completada'), (tk.due_date IS NULL), tk.due_date, ${PRIORITY_ORDER}`,
    params
  );
}

export const getTask = (db, id) => first(db, `${TASK_SELECT} WHERE tk.id = ?`, [id]);

export const getTasksDueBetween = (db, fromIso, toIso) =>
  rows(db, `${TASK_SELECT} WHERE tk.deleted_at IS NULL AND tk.due_date BETWEEN ? AND ? ORDER BY tk.due_date`, [fromIso, toIso]);

export const getOverdueTasks = (db) =>
  rows(db, `${TASK_SELECT} WHERE tk.deleted_at IS NULL AND tk.status != 'completada' AND tk.due_date < ? ORDER BY tk.due_date`, [nowIso()]);

export async function toggleTask(db, task) {
  const done = task.status !== 'completada';
  await saveRecord(db, 'tasks', { id: task.id, status: done ? 'completada' : 'pendiente', completed_at: done ? nowIso() : null });
}

export async function deleteTask(db, id) {
  await deleteRecord(db, 'tasks', id);
  const subs = await db.getAllAsync('SELECT id FROM subtasks WHERE task_id = ? AND deleted_at IS NULL', [id]);
  for (const s of subs) await deleteRecord(db, 'subtasks', s.id);
  await deleteRemindersOf(db, 'task', id);
}

// ======================= Subtareas =======================

export const getSubtasks = (db, taskId) =>
  rows(db, 'SELECT * FROM subtasks WHERE task_id = ? AND deleted_at IS NULL ORDER BY position, client_updated_at', [taskId]);

// ======================= Proyectos =======================

const PROJECT_SELECT = `SELECT p.*,
    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL) AS tasks_total,
    (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.deleted_at IS NULL AND t.status = 'completada') AS tasks_done
  FROM projects p`;

const withProgress = (p) => p && { ...p, progress: p.tasks_total ? Math.round((p.tasks_done * 100) / p.tasks_total) : 0 };

export const getProjects = async (db) =>
  (await rows(db, `${PROJECT_SELECT} WHERE p.deleted_at IS NULL ORDER BY (p.status = 'completado'), p.name`)).map(withProgress);

export const getProject = async (db, id) => withProgress(await first(db, `${PROJECT_SELECT} WHERE p.id = ?`, [id]));

export async function deleteProject(db, id) {
  await deleteRecord(db, 'projects', id);
  const tasks = await db.getAllAsync('SELECT id FROM tasks WHERE project_id = ? AND deleted_at IS NULL', [id]);
  for (const t of tasks) await saveRecord(db, 'tasks', { id: t.id, project_id: null });
}

// ======================= Etiquetas =======================

export const getTags = (db) => rows(db, 'SELECT * FROM tags WHERE deleted_at IS NULL ORDER BY name');

export async function deleteTag(db, id) {
  await deleteRecord(db, 'tags', id);
  const tasks = await rows(db, 'SELECT id, tag_ids FROM tasks WHERE tag_ids LIKE ? AND deleted_at IS NULL', [`%"${id}"%`]);
  for (const t of tasks) await saveRecord(db, 'tasks', { id: t.id, tag_ids: t.tag_ids.filter((x) => x !== id) });
}

// ======================= Recordatorios =======================

export const getRemindersOf = (db, type, id) =>
  rows(db, 'SELECT * FROM reminders WHERE remindable_type = ? AND remindable_id = ? AND deleted_at IS NULL ORDER BY remind_at', [type, id]);

export const getFutureReminders = (db) =>
  rows(db, 'SELECT * FROM reminders WHERE deleted_at IS NULL AND remind_at > ? ORDER BY remind_at', [nowIso()]);

export async function deleteRemindersOf(db, type, id) {
  const list = await db.getAllAsync('SELECT id FROM reminders WHERE remindable_type = ? AND remindable_id = ? AND deleted_at IS NULL', [
    type,
    id,
  ]);
  for (const r of list) await deleteRecord(db, 'reminders', r.id);
}

/**
 * Reemplaza los recordatorios de un evento/tarea por una lista de "minutos antes".
 * baseIso: fecha del evento (inicio) o de la tarea (fecha limite).
 */
export async function setReminders(db, type, id, baseIso, minutesList) {
  await deleteRemindersOf(db, type, id);
  if (!baseIso) return;
  const base = new Date(baseIso).getTime();
  for (const minutes of minutesList) {
    await saveRecord(db, 'reminders', {
      remindable_type: type,
      remindable_id: id,
      minutes_before: minutes,
      remind_at: new Date(base - minutes * 60000).toISOString(),
    });
  }
}

// ======================= Estadisticas =======================

export async function getStats(db) {
  const r = await db.getFirstAsync(
    `SELECT
      (SELECT COUNT(*) FROM tasks WHERE deleted_at IS NULL AND status != 'completada') AS pending,
      (SELECT COUNT(*) FROM tasks WHERE deleted_at IS NULL AND status = 'completada') AS completed,
      (SELECT COUNT(*) FROM tasks WHERE deleted_at IS NULL AND status != 'completada' AND due_date < ?) AS overdue,
      (SELECT COUNT(*) FROM projects WHERE deleted_at IS NULL AND status = 'activo') AS projects`,
    [nowIso()]
  );
  return r;
}

export async function countPendingChanges(db) {
  let total = 0;
  for (const t of SYNC_TABLES) {
    const r = await db.getFirstAsync(`SELECT COUNT(*) AS c FROM ${t} WHERE dirty = 1`);
    total += r.c;
  }
  return total;
}

// ======================= Meta (clave/valor) =======================

export const getMeta = async (db, key) => (await db.getFirstAsync('SELECT value FROM meta WHERE key = ?', [key]))?.value ?? null;
export const setMeta = (db, key, value) => db.runAsync('INSERT OR REPLACE INTO meta (key, value) VALUES (?, ?)', [key, value]);
