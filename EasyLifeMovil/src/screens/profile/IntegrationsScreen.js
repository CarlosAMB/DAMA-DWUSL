import React, { useState } from 'react';
import { Alert, ScrollView, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useSQLiteContext } from 'expo-sqlite';
import { useAuth } from '../../context/AuthContext';
import { useSync } from '../../context/SyncContext';
import { useTheme } from '../../context/ThemeContext';
import { Button, Card, Row, SectionTitle } from '../../components/ui';
import { exportData, importFromFile } from '../../services/importExport';
import { connectGoogle, disconnectGoogle, syncGoogle } from '../../services/google';
import { errorMessage } from '../../services/api';
import { formatDateTime } from '../../utils/dates';
import { hasDataKey } from '../../utils/crypto';
import { pushSupported } from '../../services/push';

/**
 * Integracion con otras aplicaciones:
 *  - Google Calendar + Google Tasks (sincronizacion automatica en ambos sentidos, via el servidor)
 *  - Exportar / importar archivos .ics, .csv y .json
 */
export default function IntegrationsScreen() {
  const db = useSQLiteContext();
  const { user, setUser } = useAuth();
  const { colors } = useTheme();
  const { notifyChange, syncNow, isOnline, googleError, setGoogleError, realtime } = useSync();
  const [busy, setBusy] = useState(null);

  const onConnect = async () => {
    setBusy('google');
    try {
      const status = await connectGoogle();
      // Se sincroniza siempre: actualiza el usuario y trae los eventos de Google
      await syncNow({ silent: true });
      if (status === 'ok') Alert.alert('Google conectado', 'Tus eventos y tareas se sincronizarán automáticamente con Google.');
      else if (status === 'error') Alert.alert('No se pudo conectar', 'Ocurrió un problema al conectar con Google. Inténtalo de nuevo.');
    } catch (e) {
      Alert.alert('No se pudo conectar', errorMessage(e));
    } finally {
      setBusy(null);
    }
  };

  const onSyncGoogle = async () => {
    setBusy('google-sync');
    try {
      await syncNow({ silent: false }); // primero se suben los cambios del telefono
      const r = await syncGoogle();
      setGoogleError(null);
      await syncNow({ silent: false }); // luego se bajan los que llegaron de Google
      const s = r.stats;
      Alert.alert(
        'Sincronizado con Google',
        `Desde Google: ${s.events_pulled} evento(s), ${s.tasks_pulled} tarea(s)\nHacia Google: ${s.events_pushed} evento(s), ${s.tasks_pushed} tarea(s)`
      );
    } catch (e) {
      const msg = e.response ? errorMessage(e) : e.message;
      setGoogleError(msg);
      if (e.response?.data?.user) await setUser(e.response.data.user);
      Alert.alert('No se pudo sincronizar', msg);
    } finally {
      setBusy(null);
    }
  };

  const onDisconnect = () =>
    Alert.alert(
      'Desconectar Google',
      'Se dejará de sincronizar con Google Calendar y Google Tasks. Los datos que ya están en ambos lados se conservan.',
      [
        { text: 'Cancelar', style: 'cancel' },
        {
          text: 'Desconectar',
          style: 'destructive',
          onPress: async () => {
            setBusy('google');
            try {
              await setUser(await disconnectGoogle());
              setGoogleError(null);
            } catch (e) {
              Alert.alert('Error', errorMessage(e));
            } finally {
              setBusy(null);
            }
          },
        },
      ]
    );

  const doExport = async (format) => {
    setBusy(format);
    try {
      await exportData(db, format);
    } catch (e) {
      Alert.alert('No se pudo exportar', e.message);
    } finally {
      setBusy(null);
    }
  };

  const doImport = async () => {
    setBusy('import');
    try {
      const r = await importFromFile(db, user?.preferences?.default_reminder_minutes ?? 15);
      if (r) {
        notifyChange();
        Alert.alert(
          'Importación completada',
          `Eventos: ${r.events}\nTareas: ${r.tasks}\n\nSe sincronizarán con tu cuenta automáticamente.`
        );
      }
    } catch (e) {
      Alert.alert('No se pudo importar', 'Revisa que el archivo sea .ics, .csv o .json válido.\n' + e.message);
    } finally {
      setBusy(null);
    }
  };

  return (
    <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }}>
      <SectionTitle>Firebase</SectionTitle>
      <Card>
        {[
          ['shield-account-outline', 'Autenticación', user?.firebase_uid ? 'Cuenta protegida con Firebase Authentication' : 'Sin vincular'],
          ['database-sync-outline', 'Tiempo real (Firestore)', realtime ? 'Activo: los cambios llegan al instante' : 'Inactivo'],
          ['bell-ring-outline', 'Notificaciones push (FCM)', pushSupported() ? 'Disponibles en este dispositivo' : 'Solo en la app instalada (APK)'],
          ['lock-outline', 'Cifrado', hasDataKey() ? 'Datos sensibles cifrados con AES-256' : 'Pendiente de llave'],
        ].map(([icon, label, value]) => (
          <View key={label} style={{ flexDirection: 'row', gap: 10, alignItems: 'center', paddingVertical: 6 }}>
            <MaterialCommunityIcons name={icon} size={22} color={colors.primary} />
            <View style={{ flex: 1 }}>
              <Text style={{ color: colors.text, fontWeight: '600' }}>{label}</Text>
              <Text style={{ color: colors.textSecondary, fontSize: 12 }}>{value}</Text>
            </View>
          </View>
        ))}
      </Card>

      <SectionTitle>Google Calendar y Google Tasks</SectionTitle>
      <Card>
        <View style={{ flexDirection: 'row', gap: 12, alignItems: 'center', marginBottom: 10 }}>
          <MaterialCommunityIcons name="google" size={30} color="#EA4335" />
          <View style={{ flex: 1 }}>
            <Text style={{ color: colors.text, fontWeight: '700' }}>
              {user?.google_connected ? 'Cuenta conectada' : 'Sincronización con Google'}
            </Text>
            <Text style={{ color: colors.textSecondary, fontSize: 12 }}>
              {user?.google_connected
                ? `${user.google_email || ''}${user.google_synced_at ? `\nÚltima sincronización: ${formatDateTime(user.google_synced_at)}` : ''}`
                : 'Tus eventos se sincronizan con Google Calendar y tus tareas con Google Tasks, en ambos sentidos.'}
            </Text>
          </View>
        </View>

        {user?.google_connected && googleError ? (
          <View style={{ flexDirection: 'row', gap: 6, marginBottom: 10 }}>
            <MaterialCommunityIcons name="alert-circle-outline" size={16} color={colors.danger} />
            <Text style={{ color: colors.danger, fontSize: 12, flex: 1 }}>{googleError}</Text>
          </View>
        ) : null}

        {user?.google_connected ? (
          <View style={{ gap: 8 }}>
            <Button
              title="Sincronizar ahora"
              icon="sync"
              small
              disabled={!isOnline || !!busy}
              loading={busy === 'google-sync'}
              onPress={onSyncGoogle}
            />
            <Button
              title="Desconectar"
              icon="link-off"
              variant="outline"
              small
              disabled={!!busy}
              loading={busy === 'google'}
              onPress={onDisconnect}
            />
          </View>
        ) : (
          <Button
            title="Conectar con Google"
            icon="google"
            disabled={!isOnline || !!busy}
            loading={busy === 'google'}
            onPress={onConnect}
          />
        )}
        {!isOnline ? (
          <Text style={{ color: colors.textSecondary, fontSize: 12, marginTop: 6 }}>Necesitas conexión a internet para usar Google.</Text>
        ) : null}

        <Text style={{ color: colors.textSecondary, fontSize: 12, marginTop: 12, marginBottom: 8 }}>
          ¿Prefieres no conectar tu cuenta? Exporta tu calendario y ábrelo con Google Calendar.
        </Text>
        <Button
          title="Exportar para Google Calendar (.ics)"
          icon="calendar-export"
          variant="outline"
          small
          loading={busy === 'ics'}
          onPress={() => doExport('ics')}
        />
      </Card>

      <SectionTitle>Exportar</SectionTitle>
      <Card>
        <Row icon="calendar-export" label="Calendario y tareas (.ics)" value="Google / Outlook / Apple" onPress={() => doExport('ics')} />
        <Row
          icon="file-delimited-outline"
          label="Tareas en hoja de cálculo (.csv)"
          value="Excel / Sheets"
          onPress={() => doExport('csv')}
        />
        <Row icon="code-json" label="Respaldo completo (.json)" value="EasyLife" onPress={() => doExport('json')} />
      </Card>

      <SectionTitle>Importar</SectionTitle>
      <Card>
        <Text style={{ color: colors.textSecondary, marginBottom: 10 }}>
          Importa eventos y tareas desde otras aplicaciones:{'\n'}• <Text style={{ fontWeight: '700' }}>.ics</Text> exportado de Google
          Calendar, Outlook o Apple{'\n'}• <Text style={{ fontWeight: '700' }}>.csv</Text> con columnas: titulo, descripcion, prioridad,
          estado, fecha_limite, proyecto, etiquetas{'\n'}• <Text style={{ fontWeight: '700' }}>.json</Text> de un respaldo de EasyLife
        </Text>
        <Button title="Seleccionar archivo" icon="file-import-outline" loading={busy === 'import'} onPress={doImport} />
      </Card>
    </ScrollView>
  );
}
