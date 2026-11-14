import CryptoJS from 'crypto-js';
import * as Crypto from 'expo-crypto';
import * as SecureStore from 'expo-secure-store';

/**
 * Cifrado de datos sensibles (AES-256-CBC + HMAC-SHA256), compatible con App\Support\DataCipher de Laravel.
 *
 *  - La llave es propia de cada usuario: la entrega el servidor al iniciar sesion y se guarda en
 *    SecureStore (Keystore de Android), nunca en SQLite.
 *  - Se usa para:
 *      1. Guardar cifrados en SQLite la descripcion y la ubicacion (src/db/repository.js)
 *      2. Descifrar los titulos, nombres, descripciones y ubicaciones que llegan cifrados desde Firestore
 *
 * Formato: "ev1:" + base64(iv) + ":" + base64(texto cifrado) + ":" + base64(hmac)
 */
export const PREFIX = 'ev1:';
const KEY_STORE = 'easylife_data_key';

let encKey = null;
let macKey = null;

function applyKey(base64Key) {
  if (!base64Key) {
    encKey = macKey = null;
    return;
  }
  const master = CryptoJS.enc.Base64.parse(base64Key);
  // Igual que en PHP: hash_hmac('sha256', 'enc', $key) y hash_hmac('sha256', 'mac', $key)
  encKey = CryptoJS.HmacSHA256('enc', master);
  macKey = CryptoJS.HmacSHA256('mac', master);
}

/** Guarda la llave que envio el servidor (al iniciar sesion). */
export async function saveDataKey(base64Key) {
  applyKey(base64Key);
  if (base64Key) await SecureStore.setItemAsync(KEY_STORE, base64Key);
  else await SecureStore.deleteItemAsync(KEY_STORE);
}

/** Carga la llave guardada (al abrir la app). */
export async function loadDataKey() {
  applyKey(await SecureStore.getItemAsync(KEY_STORE));
  return !!encKey;
}

export const hasDataKey = () => !!encKey;

export function encryptField(plain) {
  if (!encKey || plain == null || plain === '') return plain ?? null;
  const text = String(plain);
  if (text.startsWith(PREFIX)) return text;

  const iv = CryptoJS.lib.WordArray.create(Crypto.getRandomBytes(16));
  const cipher = CryptoJS.AES.encrypt(CryptoJS.enc.Utf8.parse(text), encKey, {
    iv,
    mode: CryptoJS.mode.CBC,
    padding: CryptoJS.pad.Pkcs7,
  }).ciphertext;
  const mac = CryptoJS.HmacSHA256(iv.clone().concat(cipher), macKey);
  const b64 = (w) => CryptoJS.enc.Base64.stringify(w);
  return `${PREFIX}${b64(iv)}:${b64(cipher)}:${b64(mac)}`;
}

export function decryptField(value) {
  if (typeof value !== 'string' || !value.startsWith(PREFIX)) return value;
  if (!encKey) return '🔒';
  try {
    const [iv, cipher, mac] = value.slice(PREFIX.length).split(':').map((p) => CryptoJS.enc.Base64.parse(p));
    const expected = CryptoJS.HmacSHA256(iv.clone().concat(cipher), macKey);
    if (CryptoJS.enc.Base64.stringify(expected) !== CryptoJS.enc.Base64.stringify(mac)) return '🔒';
    return CryptoJS.AES.decrypt({ ciphertext: cipher }, encKey, { iv, mode: CryptoJS.mode.CBC, padding: CryptoJS.pad.Pkcs7 }).toString(
      CryptoJS.enc.Utf8
    );
  } catch {
    return '🔒';
  }
}
