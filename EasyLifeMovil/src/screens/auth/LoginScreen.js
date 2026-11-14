import React, { useState } from 'react';
import { Alert, KeyboardAvoidingView, Platform, ScrollView, StyleSheet, Text, View, Pressable } from 'react-native';
import { MaterialCommunityIcons } from '@expo/vector-icons';
import { useAuth } from '../../context/AuthContext';
import { useTheme } from '../../context/ThemeContext';
import { Button, Input } from '../../components/ui';

export default function LoginScreen({ navigation }) {
  const { login, resetPassword, firebaseEnabled, errorMessage } = useAuth();
  const { colors } = useTheme();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleLogin = async () => {
    setError('');
    if (!email.trim() || !password) {
      setError('Ingresa tu correo y contraseña.');
      return;
    }
    setLoading(true);
    try {
      await login(email.trim(), password);
    } catch (e) {
      setError(errorMessage(e));
    } finally {
      setLoading(false);
    }
  };

  /** Firebase envia un correo con el enlace para crear una nueva contraseña. */
  const handleReset = async () => {
    setError('');
    if (!/^\S+@\S+\.\S+$/.test(email.trim())) {
      setError('Escribe tu correo arriba para enviarte el enlace de recuperación.');
      return;
    }
    try {
      await resetPassword(email.trim());
      Alert.alert('Revisa tu correo', `Si existe una cuenta con ${email.trim()}, recibirás un enlace para crear una nueva contraseña.`);
    } catch (e) {
      setError(errorMessage(e));
    }
  };

  return (
    <KeyboardAvoidingView style={{ flex: 1, backgroundColor: colors.background }} behavior={Platform.OS === 'ios' ? 'padding' : undefined}>
      <ScrollView contentContainerStyle={styles.container} keyboardShouldPersistTaps="handled">
        <View style={styles.header}>
          <View style={[styles.logo, { backgroundColor: colors.primary }]}>
            <MaterialCommunityIcons name="check-circle-outline" size={44} color={colors.primaryText} />
          </View>
          <Text style={[styles.title, { color: colors.text }]}>EasyLife</Text>
          <Text style={{ color: colors.textSecondary, fontSize: 15 }}>Organiza tu vida, reduce el estrés</Text>
        </View>

        <Input
          label="Correo electrónico"
          icon="email-outline"
          value={email}
          onChangeText={setEmail}
          keyboardType="email-address"
          autoCapitalize="none"
          autoComplete="email"
          placeholder="tucorreo@ejemplo.com"
        />
        <Input
          label="Contraseña"
          icon="lock-outline"
          value={password}
          onChangeText={setPassword}
          secureTextEntry={!showPassword}
          placeholder="••••••••"
          onSubmitEditing={handleLogin}
        />
        <View style={{ flexDirection: 'row', justifyContent: 'space-between', marginTop: -6, marginBottom: 12 }}>
          {firebaseEnabled ? (
            <Pressable onPress={handleReset}>
              <Text style={{ color: colors.textSecondary, fontWeight: '600' }}>¿Olvidaste tu contraseña?</Text>
            </Pressable>
          ) : (
            <View />
          )}
          <Pressable onPress={() => setShowPassword(!showPassword)}>
            <Text style={{ color: colors.primary, fontWeight: '600' }}>{showPassword ? 'Ocultar' : 'Mostrar'} contraseña</Text>
          </Pressable>
        </View>

        {error ? (
          <View style={[styles.errorBox, { backgroundColor: colors.danger + '18' }]}>
            <MaterialCommunityIcons name="alert-circle-outline" size={18} color={colors.danger} />
            <Text style={{ color: colors.danger, flex: 1 }}>{error}</Text>
          </View>
        ) : null}

        <Button title="Iniciar sesión" icon="login" onPress={handleLogin} loading={loading} />

        <Pressable onPress={() => navigation.navigate('Register')} style={{ marginTop: 22, alignItems: 'center' }}>
          <Text style={{ color: colors.textSecondary }}>
            ¿No tienes cuenta? <Text style={{ color: colors.primary, fontWeight: '700' }}>Regístrate</Text>
          </Text>
        </Pressable>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  container: { flexGrow: 1, justifyContent: 'center', padding: 24 },
  header: { alignItems: 'center', marginBottom: 32 },
  logo: { width: 84, height: 84, borderRadius: 24, alignItems: 'center', justifyContent: 'center', marginBottom: 14 },
  title: { fontSize: 32, fontWeight: '800' },
  errorBox: { flexDirection: 'row', gap: 8, padding: 12, borderRadius: 10, marginBottom: 14, alignItems: 'center' },
});
