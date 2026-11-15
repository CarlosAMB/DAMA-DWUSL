import AsyncStorage from '@react-native-async-storage/async-storage';
import { initializeApp } from 'firebase/app';
import {
  EmailAuthProvider,
  createUserWithEmailAndPassword,
  deleteUser,
  getReactNativePersistence,
  initializeAuth,
  reauthenticateWithCredential,
  sendPasswordResetEmail,
  signInWithEmailAndPassword,
  signOut,
  updatePassword,
} from 'firebase/auth';
import { collection, initializeFirestore, onSnapshot, query, where } from 'firebase/firestore';
import { FIREBASE_CONFIG, FIREBASE_ENABLED } from '../config';
import { LOCKED, decryptField } from '../utils/crypto';

/**
 * Firebase en la app:
 *  - Authentication: registro / inicio de sesion con correo y contraseña (Firebase guarda la contraseña cifrada).
 *    Luego Laravel valida el ID token y entrega su token para la API (POST /auth/firebase).
 *  - Firestore: los cambios de los datos llegan en tiempo real (onSnapshot) desde users/{uid}/...
 *    El servidor escribe ahi con los campos sensibles cifrados; aqui se descifran.
 */
let app = null;
export let auth = null;
let firestore = null;

if (FIREBASE_ENABLED) {
  app = initializeApp(FIREBASE_CONFIG);
  // La sesion de Firebase se conserva al cerrar la app
  auth = initializeAuth(app, { persistence: getReactNativePersistence(AsyncStorage) });
  // En React Native la conexion por long polling es la mas estable
  firestore = initializeFirestore(app, { experimentalForceLongPolling: true });
}

const MESSAGES = {
  'auth/invalid-email': 'El correo no es válido.',
  'auth/invalid-credential': 'Correo o contraseña incorrectos.',
  'auth/wrong-password': 'Correo o contraseña incorrectos.',
  'auth/user-not-found': 'Correo o contraseña incorrectos.',
  'auth/email-already-in-use': 'Ya existe una cuenta con ese correo.',
  'auth/weak-password': 'La contraseña es muy débil (mínimo 6 caracteres).',
  'auth/too-many-requests': 'Demasiados intentos. Espera unos minutos e inténtalo de nuevo.',
  'auth/network-request-failed': 'No se pudo conectar con Firebase. Revisa tu conexión a internet.',
  'auth/user-disabled': 'Esta cuenta fue deshabilitada.',
  'auth/requires-recent-login': 'Por seguridad, vuelve a iniciar sesión e inténtalo de nuevo.',
};

/** Mensaje en español para un error de Firebase (o null si no es de Firebase). */
export function firebaseErrorMessage(error) {
  if (!error?.code || !String(error.code).startsWith('auth/')) return null;
  return MESSAGES[error.code] || 'Ocurrió un error con la autenticación. Inténtalo de nuevo.';
}

const isCredentialError = (e) => ['auth/invalid-credential', 'auth/user-not-found', 'auth/wrong-password'].includes(e?.code);

export const firebaseSignIn = (email, password) => signInWithEmailAndPassword(auth, email, password);
export const firebaseSignUp = (email, password) => createUserWithEmailAndPassword(auth, email, password);
export const firebaseSignOut = () => (auth ? signOut(auth) : Promise.resolve());
export const firebaseResetPassword = (email) => sendPasswordResetEmail(auth, email);
export const currentFirebaseUser = () => auth?.currentUser || null;
export { isCredentialError };

/** Vuelve a autenticar (requisito de Firebase antes de cambiar la contraseña o borrar la cuenta). */
export async function reauthenticate(password) {
  const user = auth.currentUser;
  if (!user) throw Object.assign(new Error('Vuelve a iniciar sesión.'), { code: 'auth/requires-recent-login' });
  await reauthenticateWithCredential(user, EmailAuthProvider.credential(user.email, password));
  return user;
}

export async function firebaseChangePassword(currentPassword, newPassword) {
  const user = await reauthenticate(currentPassword);
  await updatePassword(user, newPassword);
}

export const firebaseDeleteCurrentUser = () => (auth?.currentUser ? deleteUser(auth.currentUser) : Promise.resolve());

/** Campos que el servidor cifra en Firestore. */
const ENCRYPTED = ['title', 'name', 'description', 'location'];
const TABLES = ['projects', 'tags', 'events', 'tasks', 'subtasks', 'reminders'];

/**
 * Escucha en tiempo real los datos del usuario en Firestore.
 * Solo pide lo modificado despues de la ultima sincronizacion (menos lecturas).
 * @param {string} uid  uid de Firebase
 * @param {string|null} since  ISO de la ultima sincronizacion con Laravel
 * @param {(table: string, records: object[]) => void} onChanges
 * @param {string} root  coleccion raiz que indica el servidor ("users" en produccion)
 * @returns {() => void} funcion para dejar de escuchar
 */
export function subscribeToUserData(uid, since, onChanges, root = 'users') {
  if (!firestore || !uid) return () => {};
  const unsubs = TABLES.map((table) => {
    const ref = collection(firestore, root, uid, table);
    const q = since ? query(ref, where('updated_at', '>', since)) : ref;
    return onSnapshot(
      q,
      (snap) => {
        // Se ignoran las escrituras pendientes locales (la app nunca escribe, pero por seguridad)
        const records = snap
          .docChanges()
          .filter((c) => c.type !== 'removed' && !c.doc.metadata.hasPendingWrites)
          .map((c) => {
            const data = { ...c.doc.data() };
            let readable = true;
            ENCRYPTED.forEach((f) => {
              if (data[f]) {
                data[f] = decryptField(data[f]);
                if (data[f] === LOCKED) readable = false;
              }
            });
            // Si no se pudo descifrar (otra llave), se ignora: nunca se guarda un dato ilegible
            return readable ? data : null;
          })
          .filter(Boolean);
        if (records.length) onChanges(table, records);
      },
      (error) => console.warn(`[firestore] ${table}:`, error.code || error.message)
    );
  });
  return () => unsubs.forEach((u) => u());
}
