import React, { useState } from 'react';
import { Alert, Linking, ScrollView, Text, TextInput, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useSQLiteContext } from 'expo-sqlite';
import { useAuth } from '../../context/AuthContext';
import { useTheme } from '../../context/ThemeContext';
import { Button, Card, Row, SectionTitle } from '../../components/ui';
import { exportData } from '../../services/importExport';
import { PRIVACY_URL } from '../../config';

const MEASURES = [
  ['lock-outline', 'Tu contraseña se guarda con hash bcrypt: nadie puede leerla.'],
  ['shield-key-outline', 'La sesión se guarda en el almacenamiento seguro del teléfono (Keystore/Keychain).'],
  ['database-lock-outline', 'Las descripciones de tus eventos, tareas y proyectos se cifran (AES-256) en el servidor.'],
  ['account-eye-outline', 'Los administradores solo ven estadísticas, nunca el contenido de tus datos.'],
  ['web', 'La comunicación con el servidor usa tokens que expiran y pueden revocarse.'],
];

export default function PrivacyScreen() {
  const db = useSQLiteContext();
  const { deleteAccount, errorMessage } = useAuth();
  const { colors } = useTheme();
  const [password, setPassword] = useState('');
  const [deleting, setDeleting] = useState(false);

  const confirmDelete = () => {
    if (!password) return Alert.alert('Confirma tu contraseña', 'Escribe tu contraseña para eliminar la cuenta.');
    Alert.alert('Eliminar cuenta', 'Se eliminarán permanentemente tu cuenta y TODOS tus datos. Esta acción no se puede deshacer.', [
      { text: 'Cancelar', style: 'cancel' },
      {
        text: 'Eliminar definitivamente',
        style: 'destructive',
        onPress: async () => {
          setDeleting(true);
          try {
            await deleteAccount(password);
          } catch (e) {
            Alert.alert('Error', errorMessage(e));
            setDeleting(false);
          }
        },
      },
    ]);
  };

  return (
    <ScrollView contentContainerStyle={{ padding: 16, paddingBottom: 40 }} keyboardShouldPersistTaps="handled">
      <SectionTitle>Cómo protegemos tus datos</SectionTitle>
      <Card>
        {MEASURES.map(([icon, text]) => (
          <View key={icon} style={{ flexDirection: 'row', gap: 10, paddingVertical: 6 }}>
            <MaterialCommunityIcons name={icon} size={20} color={colors.success} />
            <Text style={{ color: colors.text, flex: 1 }}>{text}</Text>
          </View>
        ))}
        <Row icon="file-document-outline" label="Leer la política de privacidad" onPress={() => Linking.openURL(PRIVACY_URL)} />
      </Card>

      <SectionTitle>Tus derechos</SectionTitle>
      <Card>
        <Row
          icon="download-outline"
          label="Descargar todos mis datos (JSON)"
          onPress={() => exportData(db, 'json').catch((e) => Alert.alert('Error', e.message))}
        />
      </Card>

      <SectionTitle>Zona de peligro</SectionTitle>
      <Card style={{ borderColor: colors.danger }}>
        <Text style={{ color: colors.text, marginBottom: 10 }}>
          Eliminar tu cuenta borra todos tus eventos, tareas, proyectos y etiquetas del servidor y de este teléfono.
        </Text>
        <TextInput
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          placeholder="Tu contraseña"
          placeholderTextColor={colors.textSecondary}
          style={{ borderWidth: 1, borderColor: colors.border, borderRadius: 10, padding: 12, color: colors.text, marginBottom: 10 }}
        />
        <Button title="Eliminar mi cuenta" icon="account-remove" variant="danger" onPress={confirmDelete} loading={deleting} />
      </Card>
    </ScrollView>
  );
}
