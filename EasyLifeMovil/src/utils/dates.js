/** Utilidades de fecha en espanol (sin dependencias externas). */

export const MONTHS = [
  'enero',
  'febrero',
  'marzo',
  'abril',
  'mayo',
  'junio',
  'julio',
  'agosto',
  'septiembre',
  'octubre',
  'noviembre',
  'diciembre',
];
export const MONTHS_SHORT = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
export const DAYS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
export const DAYS_SHORT = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

const pad = (n) => String(n).padStart(2, '0');
const cap = (s) => s.charAt(0).toUpperCase() + s.slice(1);

/** Hora actual en ISO UTC (formato que entiende la API). */
export const nowIso = () => new Date().toISOString();

/**
 * Normaliza fechas que vienen del servidor ("2026-11-15T14:00:00.000000Z")
 * al formato ISO de JavaScript con milisegundos ("2026-11-15T14:00:00.000Z").
 */
export function normalizeIso(value) {
  if (!value) return null;
  if (/^\d{4}-\d{2}-\d{2}$/.test(value)) return value; // solo fecha
  const d = new Date(String(value).replace(/(\.\d{3})\d+/, '$1'));
  return isNaN(d) ? null : d.toISOString();
}

/** Compara dos ISO a nivel de segundos (el servidor guarda segundos). */
export const secondsOf = (iso) => (iso ? Math.floor(new Date(iso).getTime() / 1000) : 0);

export const toDate = (iso) => (iso ? new Date(iso) : null);

/** Clave local YYYY-MM-DD de una fecha (zona horaria del telefono). */
export const dayKey = (date) => {
  const d = date instanceof Date ? date : new Date(date);
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

export const startOfDay = (d) => {
  const x = new Date(d);
  x.setHours(0, 0, 0, 0);
  return x;
};
export const endOfDay = (d) => {
  const x = new Date(d);
  x.setHours(23, 59, 59, 999);
  return x;
};
export const addDays = (d, n) => {
  const x = new Date(d);
  x.setDate(x.getDate() + n);
  return x;
};
export const addMonths = (d, n) => {
  const x = new Date(d);
  x.setDate(1);
  x.setMonth(x.getMonth() + n);
  return x;
};
export const isSameDay = (a, b) => dayKey(a) === dayKey(b);

export const startOfWeek = (d, weekStartsOn = 1) => {
  const x = startOfDay(d);
  const diff = (x.getDay() - weekStartsOn + 7) % 7;
  return addDays(x, -diff);
};

export const formatTime = (iso) => {
  const d = toDate(iso);
  return d ? `${pad(d.getHours())}:${pad(d.getMinutes())}` : '';
};

/** "lun 15 nov" */
export const formatShortDate = (iso) => {
  const d = toDate(iso);
  return d ? `${DAYS_SHORT[d.getDay()]} ${d.getDate()} ${MONTHS_SHORT[d.getMonth()]}` : '';
};

/** "15 nov 2026, 14:00" */
export const formatDateTime = (iso, withTime = true) => {
  const d = toDate(iso);
  if (!d) return '';
  const base = `${d.getDate()} ${MONTHS_SHORT[d.getMonth()]} ${d.getFullYear()}`;
  return withTime ? `${base}, ${formatTime(iso)}` : base;
};

/** "Lunes, 15 de noviembre" */
export const formatLongDate = (date) => {
  const d = date instanceof Date ? date : new Date(date);
  return `${cap(DAYS[d.getDay()])}, ${d.getDate()} de ${MONTHS[d.getMonth()]}`;
};

export const monthTitle = (date) => `${cap(MONTHS[date.getMonth()])} ${date.getFullYear()}`;

/** Texto relativo para fechas limite: "Hoy", "Mañana", "Vencida hace 2 días"... */
export function relativeDue(iso) {
  if (!iso) return '';
  const today = startOfDay(new Date());
  const due = startOfDay(new Date(iso));
  const days = Math.round((due - today) / 86400000);
  if (days === 0) return `Hoy ${formatTime(iso)}`;
  if (days === 1) return `Mañana ${formatTime(iso)}`;
  if (days === -1) return 'Venció ayer';
  if (days < 0) return `Venció hace ${-days} días`;
  if (days < 7) return `${cap(DAYS[due.getDay()])} ${formatTime(iso)}`;
  return formatDateTime(iso, false);
}

export const isOverdue = (task) => task.status !== 'completada' && task.due_date && new Date(task.due_date) < new Date();
