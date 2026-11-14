import React, { useState } from 'react';
import { KeyboardAvoidingView, Linking, Platform, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useAuth } from '../../context/AuthContext';
import { useTheme } from '../../context/ThemeContext';
import { Button, Input } from '../../components/ui';
import { PRIVACY_URL } from '../../config';

export default function RegisterScreen() {
  const { register, errorMessage } = useAuth();
  const { colors } = useTheme();
  const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' });
  const [accept, setAccept] = useState(false);
  const [errors, setErrors] = useState({});
  const [general, setGeneral] = useState('');
  const [loading, setLoading] = useState(false);

  const set = (key) => (value) => setForm({ ...form, [key]: value });

  const validate = () => {
    const e = {};
    if (!form.name.trim()) e.name = 'Ingresa tu nombre';
    if (!/^\S+@\S+\.\S+$/.test(form.email.trim())) e.email = 'Correo no válido';
    if (form.password.length < 8 || !/[A-Za-z]/.test(form.password) || !/\d/.test(form.password)) {
      e.password = 'Mínimo 8 caracteres, con letras y números';
    }
    if (form.password !== form.password_confirmation) e.password_confirmation = 'Las contraseñas no coinciden';
    if (!accept) e.accept = 'Debes aceptar la política de privacidad';
    setErrors(e);
    return Object.keys(e).length === 0;
  };

  const handleRegister = async () => {
    setGeneral('');
    if (!validate()) return;
    setLoading(true);
    try {
      await register({ ...form, email: form.email.trim(), accept_privacy: true });
    } catch (e) {
      const apiErrors = e.response?.data?.errors;
      if (apiErrors) {
        setErrors(Object.fromEntries(Object.entries(apiErrors).map(([k, v]) => [k, v[0]])));
      } else {
        setGeneral(errorMessage(e));
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: colors.background }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
        <Text style={[styles.title, { color: colors.text }]}>Crea tu cuenta</Text>
        <Text style={{ color: colors.textSecondary, marginBottom: 24 }}>Empieza a organizar tus eventos, tareas y proyectos.</Text>

        <Input label="Nombre completo" icon="account-outline" value={form.name} onChangeText={set('name')} error={errors.name} />
        <Input
          label="Correo electrónico"
          icon="email-outline"
          value={form.email}
          onChangeText={set('email')}
          keyboardType="email-address"
          autoCapitalize="none"
          error={errors.email}
        />
        <Input
          label="Contraseña"
          icon="lock-outline"
          value={form.password}
          onChangeText={set('password')}
          secureTextEntry
          error={errors.password}
        />
        <Input
          label="Confirmar contraseña"
          icon="lock-check-outline"
          value={form.password_confirmation}
          onChangeText={set('password_confirmation')}
          secureTextEntry
          error={errors.password_confirmation}
        />

        <Pressable style={styles.checkRow} onPress={() => setAccept(!accept)}>
          <MaterialCommunityIcons
            name={accept ? 'checkbox-marked' : 'checkbox-blank-outline'}
            size={24}
            color={errors.accept ? colors.danger : colors.primary}
          />
          <Text style={{ color: colors.text, flex: 1 }}>
            Acepto la{' '}
            <Text style={{ color: colors.primary, fontWeight: '700' }} onPress={() => Linking.openURL(PRIVACY_URL)}>
              política de privacidad
            </Text>{' '}
            y el tratamiento de mis datos personales.
          </Text>
        </Pressable>
        {errors.accept && <Text style={{ color: colors.danger, fontSize: 12, marginBottom: 10 }}>{errors.accept}</Text>}

        {general ? <Text style={{ color: colors.danger, marginBottom: 12 }}>{general}</Text> : null}

        <Button title="Registrarme" icon="account-plus" onPress={handleRegister} loading={loading} style={{ marginTop: 8 }} />

        <View style={[styles.note, { backgroundColor: colors.primarySoft }]}>
          <MaterialCommunityIcons name="shield-lock-outline" size={18} color={colors.primary} />
          <Text style={{ color: colors.textSecondary, flex: 1, fontSize: 12 }}>
            Tu contraseña se guarda encriptada y tus notas se almacenan cifradas en el servidor.
          </Text>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flexGrow: 1, padding: 24 },
  title: { fontSize: 26, fontWeight: '800', marginBottom: 4 },
  checkRow: { flexDirection: 'row', gap: 10, alignItems: 'center', marginBottom: 8, marginTop: 4 },
  note: { flexDirection: 'row', gap: 8, padding: 12, borderRadius: 10, marginTop: 20, alignItems: 'center' },
});
