import React from 'react';
import { Alert, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { useSync } from '../../context/SyncContext';
import { useTheme } from '../../context/ThemeContext';
import { Card, Row } from '../../components/ui';
import { formatDateTime } from '../../utils/dates';

export default function ProfileScreen({ navigation }) {
  const { user, logout, updateProfile, errorMessage } = useAuth();
  const { colors, darkMode, setTheme } = useTheme();
  const { lastSync, pending, isOnline, syncNow, syncing, lastError } = useSync();

  const toggleDark = async (value) => {
    setTheme(value);
    try {
      await updateProfile({ preferences: { theme: value ? 'dark' : 'light' } });
    } catch {
      /* sin conexion: se queda local */
    }
  };

  const manualSync = async () => {
    try {
      const r = await syncNow({ silent: false });
      if (r)
        Alert.alert(
          'Sincronizado',
          `Enviados: ${r.pushed} · Recibidos: ${r.received}${r.errors.length ? `\nRechazados: ${r.errors.length}` : ''}`
        );
    } catch (e) {
      Alert.alert('Sincronización', e.message);
    }
  };

  return (
    <ScrollView style={{ backgroundColor: colors.background }} contentContainerStyle={{ padding: 16, paddingBottom: 40 }}>
      <Card style={{ alignItems: 'center', paddingVertical: 22 }}>
        <View style={[styles.avatar, { backgroundColor: colors.primary }]}>
          <Text style={{ color: colors.primaryText, fontSize: 30, fontWeight: '800' }}>{user?.name?.[0]?.toUpperCase()}</Text>
        </View>
        <Text style={[styles.name, { color: colors.text }]}>{user?.name}</Text>
        <Text style={{ color: colors.textSecondary }}>{user?.email}</Text>
      </Card>

      <Card>
        <Row
          icon={isOnline ? 'cloud-check-outline' : 'cloud-off-outline'}
          label={syncing ? 'Sincronizando…' : 'Sincronizar ahora'}
          value={pending ? `${pending} pendiente(s)` : ''}
          onPress={manualSync}
          color={lastError ? colors.danger : undefined}
        />
        <Text style={{ color: colors.textSecondary, fontSize: 12, paddingTop: 8 }}>
          {lastSync ? `Última sincronización: ${formatDateTime(lastSync)}` : 'Aún no se ha sincronizado'}
          {lastError ? `\n${lastError}` : ''}
        </Text>
      </Card>

      <Text style={[styles.section, { color: colors.textSecondary }]}>CUENTA</Text>
      <Card>
        <Row icon="account-edit-outline" label="Datos personales" onPress={() => navigation.navigate('EditProfile')} />
        <Row icon="tune-variant" label="Preferencias de organización" onPress={() => navigation.navigate('Preferences')} />
        <Row icon="lock-reset" label="Cambiar contraseña" onPress={() => navigation.navigate('ChangePassword')} />
        <Row
          icon="theme-light-dark"
          label="Modo oscuro"
          right={<Switch value={darkMode} onValueChange={toggleDark} trackColor={{ true: colors.primary }} />}
        />
      </Card>

      <Text style={[styles.section, { color: colors.textSecondary }]}>HERRAMIENTAS</Text>
      <Card>
        <Row icon="tag-multiple-outline" label="Etiquetas" onPress={() => navigation.navigate('Tags')} />
        <Row icon="swap-horizontal" label="Integraciones (Google, importar/exportar)" onPress={() => navigation.navigate('Integrations')} />
        <Row icon="shield-lock-outline" label="Privacidad y mis datos" onPress={() => navigation.navigate('Privacy')} />
        <Row icon="help-circle-outline" label="Ayuda" onPress={() => navigation.navigate('Help')} />
      </Card>

      <Card>
        <Row icon="logout" label="Cerrar sesión" color={colors.danger} onPress={() => logout(pending)} />
      </Card>
      <Text style={{ color: colors.textSecondary, textAlign: 'center', fontSize: 12, marginTop: 8 }}>EasyLife v1.0.0</Text>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  avatar: { width: 76, height: 76, borderRadius: 38, alignItems: 'center', justifyContent: 'center', marginBottom: 10 },
  name: { fontSize: 20, fontWeight: '800' },
  section: { fontSize: 12, fontWeight: '700', marginTop: 10, marginBottom: 6, marginLeft: 4 },
});
