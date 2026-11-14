import { Platform } from 'react-native';
import * as Notifications from 'expo-notifications';
import { fromDb, getFutureReminders, getTasksDueBetween } from '../db/repository';
import { addDays, endOfDay, formatTime, startOfDay } from '../utils/dates';

/**
 * Notificaciones LOCALES (funcionan sin internet y en Expo Go).
 * Estrategia simple y robusta: cada vez que cambian los datos se cancelan
 * todas las notificaciones programadas y se vuelven a programar.
 */

const CHANNEL_ID = 'recordatorios';
const MAX_SCHEDULED = 50; // iOS permite maximo 64 notificaciones programadas

// Como se muestra una notificacion si llega con la app abierta
Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowBanner: true,
    shouldShowList: true,
    shouldPlaySound: true,
    shouldSetBadge: false,
  }),
});

// Si no se puede crear el canal propio (pasa en Expo Go) se usa el canal por defecto
let channelReady = false;
const channel = () => (channelReady ? { channelId: CHANNEL_ID } : {});

export async function setupNotifications() {
  if (Platform.OS === 'android') {
    try {
      await Notifications.setNotificationChannelAsync(CHANNEL_ID, {
        name: 'Recordatorios',
        importance: Notifications.AndroidImportance.HIGH,
      });
      channelReady = true;
    } catch (e) {
      channelReady = false;
      console.log('Canal de notificaciones no disponible, se usa el canal por defecto');
    }
  }
  try {
    const current = await Notifications.getPermissionsAsync();
    if (current.granted) return true;
    const result = await Notifications.requestPermissionsAsync({
      ios: { allowAlert: true, allowBadge: true, allowSound: true },
    });
    return result.granted;
  } catch (e) {
    console.warn('No se pudieron configurar las notificaciones', e);
    return false;
  }
}

async function describeReminder(db, reminder) {
  const table = reminder.remindable_type === 'event' ? 'events' : 'tasks';
  const item = fromDb(await db.getFirstAsync(`SELECT * FROM ${table} WHERE id = ? AND deleted_at IS NULL`, [reminder.remindable_id]));
  if (!item) return null;
  if (table === 'tasks' && item.status === 'completada') return null;

  const when = reminder.minutes_before ? beforeText(reminder.minutes_before) : 'ahora';
  if (table === 'events') {
    return {
      title: `📅 ${item.title}`,
      body: item.all_day
        ? `Evento de todo el día · ${when}`
        : `Comienza ${when} (${formatTime(item.start_at)})${item.location ? ' · ' + item.location : ''}`,
      data: { type: 'event', id: item.id },
    };
  }
  return {
    title: `✅ ${item.title}`,
    body: `Fecha límite ${when} (${formatTime(item.due_date)})`,
    data: { type: 'task', id: item.id },
  };
}

function beforeText(minutes) {
  if (minutes < 60) return `en ${minutes} min`;
  if (minutes < 1440) return `en ${Math.round(minutes / 60)} h`;
  return `en ${Math.round(minutes / 1440)} día(s)`;
}

/**
 * Reprograma todos los recordatorios.
 * @param prefs preferencias del usuario (notifications_enabled, daily_summary, daily_summary_time)
 */
export async function rescheduleAll(db, prefs = {}) {
  try {
    await Notifications.cancelAllScheduledNotificationsAsync();
    if (prefs.notifications_enabled === false) return 0;

    const reminders = (await getFutureReminders(db)).slice(0, MAX_SCHEDULED);
    let count = 0;
    for (const r of reminders) {
      const content = await describeReminder(db, r);
      if (!content) continue;
      await Notifications.scheduleNotificationAsync({
        content: { ...content, sound: true },
        trigger: { type: Notifications.SchedulableTriggerInputTypes.DATE, date: new Date(r.remind_at), ...channel() },
      });
      count++;
    }

    // Resumen diario automatico con las tareas del dia
    if (prefs.daily_summary !== false) {
      const [hour, minute] = (prefs.daily_summary_time || '07:00').split(':').map(Number);
      const today = await getTasksDueBetween(db, startOfDay(new Date()).toISOString(), endOfDay(addDays(new Date(), 0)).toISOString());
      const pending = today.filter((t) => t.status !== 'completada').length;
      await Notifications.scheduleNotificationAsync({
        content: {
          title: '☀️ Tu resumen de EasyLife',
          body: pending ? `Tienes ${pending} tarea(s) para hoy. ¡Tú puedes!` : 'Revisa tu agenda y planifica tu día.',
          data: { type: 'summary' },
        },
        trigger: { type: Notifications.SchedulableTriggerInputTypes.DAILY, hour, minute, ...channel() },
      });
    }
    return count;
  } catch (e) {
    console.warn('No se pudieron programar las notificaciones', e);
    return 0;
  }
}

/** Notificacion inmediata de prueba (pantalla de perfil). */
export const sendTestNotification = () =>
  Notifications.scheduleNotificationAsync({
    content: { title: '🔔 EasyLife', body: 'Las notificaciones funcionan correctamente.' },
    trigger: { type: Notifications.SchedulableTriggerInputTypes.TIME_INTERVAL, seconds: 2, ...channel() },
  });
