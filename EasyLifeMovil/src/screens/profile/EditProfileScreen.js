import React, { useState } from 'react';
import { Alert, ScrollView, Text } from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { useTheme } from '../../context/ThemeContext';
import { Button, Input } from '../../components/ui';

/** Gestion de datos personales (requiere conexion porque modifica la cuenta). */
export default function EditProfileScreen({ navigation }) {
  const { user, updateProfile, errorMessage } = useAuth();
  const { colors } = useTheme();
  const [form, setForm] = useState({ name: user?.name || '', email: user?.email || '', phone: user?.phone || '' });
  const [saving, setSaving] = useState(false);

  const save = async () => {
    if (!form.name.trim() || !form.email.trim()) return Alert.alert('Datos incompletos', 'Nombre y correo son obligatorios.');
    setSaving(true);
    try {
      await updateProfile({ name: form.name.trim(), email: form.email.trim(), phone: form.phone.trim() || null });
      Alert.alert('Listo', 'Tus datos se actualizaron.');
      navigation.goBack();
    } catch (e) {
      Alert.alert('No se pudo guardar', errorMessage(e));
    } finally {
      setSaving(false);
    }
  };

  return (
    <ScrollView contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
      <Input label="Nombre completo" icon="account-outline" value={form.name} onChangeText={(name) => setForm({ ...form, name })} />
      <Input
        label="Correo electrónico"
        icon="email-outline"
        value={form.email}
        onChangeText={(email) => setForm({ ...form, email })}
        keyboardType="email-address"
        autoCapitalize="none"
      />
      <Input
        label="Teléfono"
        icon="phone-outline"
        value={form.phone}
        onChangeText={(phone) => setForm({ ...form, phone })}
        keyboardType="phone-pad"
      />
      <Text style={{ color: colors.textSecondary, fontSize: 12, marginBottom: 16 }}>
        Se requiere conexión a internet para modificar los datos de tu cuenta.
      </Text>
      <Button title="Guardar cambios" icon="content-save" onPress={save} loading={saving} />
    </ScrollView>
  );
}
