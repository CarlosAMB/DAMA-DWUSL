import { File, Paths } from 'expo-file-system';
import * as DocumentPicker from 'expo-document-picker';
import * as Sharing from 'expo-sharing';
import { fromDb, getAllEventTypes, getProjects, getTags, getTasks, saveRecord, setReminders } from '../db/repository';
import { addDays } from '../utils/dates';

/**
 * Importacion / exportacion de datos desde el telefono (funciona sin internet).
 *  - .ics  -> Google Calendar, Outlook, Apple Calendar
 *  - .csv  -> Excel, Google Sheets, Todoist, Microsoft To Do
 *  - .json -> respaldo completo de EasyLife
 */

// ============================== ICS ==============================

const icsDate = (iso) =>
  new Date(iso)
    .toISOString()
    .replace(/[-:]/g, '')
    .replace(/\.\d{3}/, '');
const icsDay = (date) => `${date.getFullYear()}${String(date.getMonth() + 1).padStart(2, '0')}${String(date.getDate()).padStart(2, '0')}`;
const esc = (s = '') => String(s).replace(/\\/g, '\\\\').replace(/;/g, '\\;').replace(/,/g, '\\,').replace(/\r?\n/g, '\\n');
const unesc = (s = '') => s.replace(/\\n/gi, '\n').replace(/\\;/g, ';').replace(/\\,/g, ',').replace(/\\\\/g, '\\');
const PRIORITY_ICS = { urgente: 1, alta: 3, media: 5, baja: 9 };

function fold(line) {
  if (line.length <= 74) return line;
  const parts = [];
  for (let i = 0; i < line.length; i += 74) parts.push(line.slice(i, i + 74));
  return parts.join('\r\n ');
}

export function buildIcs(events, tasks, typesById = {}) {
  const stamp = icsDate(new Date().toISOString());
  const lines = [
    'BEGIN:VCALENDAR',
    'VERSION:2.0',
    'PRODID:-//EasyLife//Organizador Personal//ES',
    'CALSCALE:GREGORIAN',
    'X-WR-CALNAME:EasyLife',
  ];

  events.forEach((e) => {
    lines.push('BEGIN:VEVENT', `UID:${e.id}@easylife`, `DTSTAMP:${stamp}`);
    if (e.all_day) {
      lines.push(`DTSTART;VALUE=DATE:${icsDay(new Date(e.start_at))}`);
      lines.push(`DTEND;VALUE=DATE:${icsDay(addDays(new Date(e.end_at || e.start_at), 1))}`);
    } else {
      lines.push(`DTSTART:${icsDate(e.start_at)}`);
      lines.push(`DTEND:${icsDate(e.end_at || new Date(new Date(e.start_at).getTime() + 3600000).toISOString())}`);
    }
    lines.push(`SUMMARY:${esc(e.title)}`);
    if (e.description) lines.push(`DESCRIPTION:${esc(e.description)}`);
    if (e.location) lines.push(`LOCATION:${esc(e.location)}`);
    if (typesById[e.event_type_id]) lines.push(`CATEGORIES:${esc(typesById[e.event_type_id].name)}`);
    lines.push('END:VEVENT');
  });

  tasks.forEach((t) => {
    lines.push('BEGIN:VTODO', `UID:${t.id}@easylife`, `DTSTAMP:${stamp}`, `SUMMARY:${esc(t.title)}`);
    if (t.description) lines.push(`DESCRIPTION:${esc(t.description)}`);
    if (t.due_date) lines.push(`DUE:${icsDate(t.due_date)}`);
    lines.push(`PRIORITY:${PRIORITY_ICS[t.priority] || 5}`);
    lines.push(`STATUS:${t.status === 'completada' ? 'COMPLETED' : t.status === 'en_progreso' ? 'IN-PROCESS' : 'NEEDS-ACTION'}`);
    lines.push('END:VTODO');
  });

  lines.push('END:VCALENDAR');
  return lines.map(fold).join('\r\n') + '\r\n';
}

function parseIcsDate(value, params) {
  if (/^\d{8}$/.test(value)) {
    return { iso: new Date(+value.slice(0, 4), +value.slice(4, 6) - 1, +value.slice(6, 8)).toISOString(), allDay: true };
  }
  const m = value.match(/^(\d{4})(\d{2})(\d{2})T(\d{2})(\d{2})(\d{2})(Z?)$/);
  if (!m) return null;
  const [, y, mo, d, h, mi, s, z] = m;
  // Con Z es UTC; sin Z (o con TZID) se interpreta como hora local del telefono
  const date = z ? new Date(Date.UTC(+y, +mo - 1, +d, +h, +mi, +s)) : new Date(+y, +mo - 1, +d, +h, +mi, +s);
  return { iso: date.toISOString(), allDay: params.includes('VALUE=DATE') };
}

export function parseIcs(content) {
  const lines = content
    .replace(/\r\n[ \t]/g, '')
    .replace(/\n[ \t]/g, '')
    .split(/\r\n|\n|\r/);
  const result = { events: [], tasks: [] };
  let current = null;
  let kind = null;

  for (const line of lines) {
    if (line === 'BEGIN:VEVENT' || line === 'BEGIN:VTODO') {
      current = {};
      kind = line === 'BEGIN:VEVENT' ? 'events' : 'tasks';
      continue;
    }
    if (line === 'END:VEVENT' || line === 'END:VTODO') {
      if (current) result[kind].push(current);
      current = null;
      continue;
    }
    if (!current) continue;
    const idx = line.indexOf(':');
    if (idx < 0) continue;
    const [key, ...paramParts] = line.slice(0, idx).split(';');
    const params = paramParts.join(';').toUpperCase();
    const value = line.slice(idx + 1);

    switch (key.toUpperCase()) {
      case 'SUMMARY':
        current.title = unesc(value).slice(0, 150);
        break;
      case 'DESCRIPTION':
        current.description = unesc(value).slice(0, 5000);
        break;
      case 'LOCATION':
        current.location = unesc(value).slice(0, 150);
        break;
      case 'DTSTART': {
        const d = parseIcsDate(value, params);
        if (d) {
          current.start_at = d.iso;
          current.all_day = d.allDay;
        }
        break;
      }
      case 'DTEND': {
        const d = parseIcsDate(value, params);
        if (d) current.end_at = d.allDay ? addDays(new Date(d.iso), -1).toISOString() : d.iso;
        break;
      }
      case 'DUE': {
        const d = parseIcsDate(value, params);
        if (d) current.due_date = d.iso;
        break;
      }
      case 'PRIORITY': {
        const p = +value;
        current.priority = !p ? 'media' : p <= 2 ? 'urgente' : p <= 4 ? 'alta' : p <= 6 ? 'media' : 'baja';
        break;
      }
      case 'STATUS':
        current.status = value === 'COMPLETED' ? 'completada' : value === 'IN-PROCESS' ? 'en_progreso' : 'pendiente';
        break;
      default:
        break;
    }
  }
  return result;
}

// ============================== CSV ==============================

const CSV_HEADERS = ['titulo', 'descripcion', 'prioridad', 'estado', 'fecha_limite', 'proyecto', 'etiquetas'];

const csvCell = (v) => {
  const s = v == null ? '' : String(v);
  return /[",\n;]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
};

/** Divide un CSV respetando comillas. */
function parseCsvRows(text) {
  const rows = [];
  let row = [];
  let cell = '';
  let quoted = false;
  for (let i = 0; i < text.length; i++) {
    const c = text[i];
    if (quoted) {
      if (c === '"' && text[i + 1] === '"') {
        cell += '"';
        i++;
      } else if (c === '"') quoted = false;
      else cell += c;
    } else if (c === '"') quoted = true;
    else if (c === ',') {
      row.push(cell);
      cell = '';
    } else if (c === '\n' || c === '\r') {
      if (c === '\r' && text[i + 1] === '\n') i++;
      row.push(cell);
      rows.push(row);
      row = [];
      cell = '';
    } else cell += c;
  }
  if (cell || row.length) {
    row.push(cell);
    rows.push(row);
  }
  return rows.filter((r) => r.some((x) => x.trim()));
}

const pad = (n) => String(n).padStart(2, '0');
const localDateTime = (iso) => {
  const d = new Date(iso);
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
};

export function buildCsv(tasks, projectsById, tagsById) {
  const lines = [CSV_HEADERS.join(',')];
  tasks.forEach((t) => {
    lines.push(
      [
        t.title,
        t.description,
        t.priority,
        t.status,
        t.due_date ? localDateTime(t.due_date) : '',
        projectsById[t.project_id]?.name || '',
        t.tag_ids
          .map((id) => tagsById[id]?.name)
          .filter(Boolean)
          .join('|'),
      ]
        .map(csvCell)
        .join(',')
    );
  });
  return '\uFEFF' + lines.join('\r\n');
}

// ============================== Exportar ==============================

async function shareText(filename, content, mimeType) {
  const file = new File(Paths.cache, filename);
  if (file.exists) file.delete();
  file.create();
  await file.write(content);
  if (!(await Sharing.isAvailableAsync())) throw new Error('Compartir no está disponible en este dispositivo.');
  await Sharing.shareAsync(file.uri, { mimeType, dialogTitle: 'Exportar desde EasyLife' });
}

export async function exportData(db, format) {
  const [tasks, tags, projects, types] = await Promise.all([
    getTasks(db, { status: 'todas' }),
    getTags(db),
    getProjects(db),
    getAllEventTypes(db),
  ]);
  const events = (await db.getAllAsync('SELECT * FROM events WHERE deleted_at IS NULL ORDER BY start_at')).map(fromDb); // descifra
  const stamp = new Date().toISOString().slice(0, 10);
  const byId = (list) => Object.fromEntries(list.map((x) => [x.id, x]));

  if (format === 'ics') {
    await shareText(
      `easylife_${stamp}.ics`,
      buildIcs(
        events.map((e) => ({ ...e, all_day: !!e.all_day })),
        tasks,
        byId(types)
      ),
      'text/calendar'
    );
  } else if (format === 'csv') {
    await shareText(`easylife_tareas_${stamp}.csv`, buildCsv(tasks, byId(projects), byId(tags)), 'text/csv');
  } else {
    const subtasks = await db.getAllAsync('SELECT * FROM subtasks WHERE deleted_at IS NULL');
    const tagsById = byId(tags);
    const payload = {
      app: 'EasyLife',
      version: 1,
      exported_at: new Date().toISOString(),
      projects,
      tags,
      events,
      subtasks,
      tasks: tasks.map((t) => ({ ...t, tag_names: t.tag_ids.map((id) => tagsById[id]?.name).filter(Boolean) })),
    };
    await shareText(`easylife_respaldo_${stamp}.json`, JSON.stringify(payload, null, 2), 'application/json');
  }
}

// ============================== Importar ==============================

async function findOrCreate(db, table, name, cache) {
  const key = name.trim().toLowerCase();
  if (!key) return null;
  if (!cache[key]) cache[key] = await saveRecord(db, table, { name: name.trim().slice(0, table === 'tags' ? 40 : 120) });
  return cache[key];
}

/** Abre el selector de archivos e importa. Devuelve { events, tasks } importados o null si se cancelo. */
export async function importFromFile(db, defaultReminderMinutes = 15) {
  const result = await DocumentPicker.getDocumentAsync({
    copyToCacheDirectory: true,
    type: ['text/calendar', 'text/csv', 'application/json', 'text/plain', '*/*'],
  });
  if (result.canceled) return null;

  const asset = result.assets[0];
  const text = (await new File(asset.uri).text()).replace(/^\uFEFF/, '');
  const name = (asset.name || '').toLowerCase();
  const counts = { events: 0, tasks: 0 };

  // Caches para no duplicar proyectos/etiquetas existentes
  const projectCache = Object.fromEntries((await getProjects(db)).map((p) => [p.name.toLowerCase(), p.id]));
  const tagCache = Object.fromEntries((await getTags(db)).map((t) => [t.name.toLowerCase(), t.id]));

  if (name.endsWith('.ics') || text.startsWith('BEGIN:VCALENDAR')) {
    const data = parseIcs(text);
    for (const e of data.events) {
      if (!e.title || !e.start_at) continue;
      const id = await saveRecord(db, 'events', {
        title: e.title,
        description: e.description || null,
        location: e.location || null,
        start_at: e.start_at,
        end_at: e.end_at || null,
        all_day: !!e.all_day,
      });
      if (new Date(e.start_at) > new Date()) await setReminders(db, 'event', id, e.start_at, [defaultReminderMinutes]);
      counts.events++;
    }
    for (const t of data.tasks) {
      if (!t.title) continue;
      await saveRecord(db, 'tasks', {
        title: t.title,
        description: t.description || null,
        due_date: t.due_date || null,
        priority: t.priority || 'media',
        status: t.status || 'pendiente',
        completed_at: t.status === 'completada' ? new Date().toISOString() : null,
        tag_ids: [],
      });
      counts.tasks++;
    }
  } else if (name.endsWith('.json') || text.trim().startsWith('{')) {
    const data = JSON.parse(text);
    for (const e of data.events || []) {
      if (!e.title || !e.start_at) continue;
      await saveRecord(db, 'events', {
        title: e.title,
        description: e.description || null,
        location: e.location || null,
        start_at: new Date(e.start_at).toISOString(),
        end_at: e.end_at ? new Date(e.end_at).toISOString() : null,
        all_day: !!e.all_day,
        event_type_id: e.event_type_id || null,
      });
      counts.events++;
    }
    for (const t of data.tasks || []) {
      if (!t.title) continue;
      const tagIds = [];
      for (const tn of t.tag_names || []) tagIds.push(await findOrCreate(db, 'tags', tn, tagCache));
      await saveRecord(db, 'tasks', {
        title: t.title,
        description: t.description || null,
        priority: t.priority || 'media',
        status: t.status || 'pendiente',
        due_date: t.due_date ? new Date(t.due_date).toISOString() : null,
        completed_at: t.completed_at || null,
        tag_ids: tagIds.filter(Boolean),
      });
      counts.tasks++;
    }
  } else {
    // CSV: titulo, descripcion, prioridad, estado, fecha_limite, proyecto, etiquetas
    const [header, ...rows] = parseCsvRows(text);
    const cols = header.map((h) =>
      h
        .trim()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, '_')
    );
    const get = (row, ...keys) => {
      for (const k of keys) {
        const i = cols.indexOf(k);
        if (i >= 0 && row[i]) return row[i].trim();
      }
      return '';
    };
    for (const row of rows) {
      const title = get(row, 'titulo', 'title', 'content', 'tarea');
      if (!title) continue;
      const priority = get(row, 'prioridad', 'priority').toLowerCase();
      const status = get(row, 'estado', 'status').toLowerCase();
      const due = get(row, 'fecha_limite', 'due_date', 'fecha');
      const dueDate = due ? new Date(due.replace(' ', 'T')) : null;
      const project = get(row, 'proyecto', 'project');
      const tagIds = [];
      for (const tn of get(row, 'etiquetas', 'tags', 'labels').split(/[|;]/))
        if (tn.trim()) tagIds.push(await findOrCreate(db, 'tags', tn, tagCache));
      await saveRecord(db, 'tasks', {
        title: title.slice(0, 150),
        description: get(row, 'descripcion', 'description') || null,
        priority: ['baja', 'media', 'alta', 'urgente'].includes(priority) ? priority : 'media',
        status: ['pendiente', 'en_progreso', 'completada'].includes(status) ? status : 'pendiente',
        due_date: dueDate && !isNaN(dueDate) ? dueDate.toISOString() : null,
        project_id: project ? await findOrCreate(db, 'projects', project, projectCache) : null,
        tag_ids: tagIds.filter(Boolean),
      });
      counts.tasks++;
    }
  }
  return counts;
}
