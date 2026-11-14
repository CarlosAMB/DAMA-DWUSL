import React, { useState } from 'react';
import { Alert, ScrollView } from 'react-native';
import { useAuth } from '../../context/AuthContext';
import { Button, Input } from '../../components/ui';

export default function ChangePasswordScreen({ navigation }) {
  const { changePassword, errorMessage } = useAuth();
  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' });
  const [saving, setSaving] = useState(false);
  const set = (k) => (v) => setForm({ ...form, [k]: v });

  const save = async () => {
    if (!form.current_password) return Alert.alert('Error', 'Escribe tu contraseña actual.');
    if (form.password.length < 8 || !/[A-Za-z]/.test(form.password) || !/\d/.test(form.password)) {
      return Alert.alert('Error', 'La nueva contraseña debe tener al menos 8 caracteres, con letras y números.');
    }
    if (form.password !== form.password_confirmation) return Alert.alert('Error', 'Las contraseñas nuevas no coinciden.');
    setSaving(true);
    try {
      await changePassword(form);
      Alert.alert('Contraseña actualizada', 'Usa tu nueva contraseña la próxima vez que inicies sesión.');
      navigation.goBack();
    } catch (e) {
      Alert.alert('No se pudo cambiar', errorMessage(e));
    } finally {
      setSaving(false);
    }
  };

  return (
    <ScrollView contentContainerStyle={{ padding: 16 }} keyboardShouldPersistTaps="handled">
      <Input
        label="Contraseña actual"
        icon="lock-outline"
        secureTextEntry
        value={form.current_password}
        onChangeText={set('current_password')}
      />
      <Input
        label="Nueva contraseña"
        icon="lock-plus-outline"
        secureTextEntry
        value={form.password}
        onChangeText={set('password')}
        placeholder="Mínimo 8 caracteres con letras y números"
      />
      <Input
        label="Confirmar nueva contraseña"
        icon="lock-check-outline"
        secureTextEntry
        value={form.password_confirmation}
        onChangeText={set('password_confirmation')}
      />
      <Button title="Actualizar contraseña" icon="check" onPress={save} loading={saving} />
    </ScrollView>
  );
}
