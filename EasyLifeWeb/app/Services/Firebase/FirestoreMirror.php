<?php

namespace App\Services\Firebase;

use App\Models\User;
use App\Services\SyncService;
use App\Support\DataCipher;
use Illuminate\Database\Eloquent\Model;

/**
 * Espejo en tiempo real de los datos de cada usuario en Cloud Firestore.
 *
 *   {coleccion}/{firebase_uid}/{projects|tags|events|tasks|subtasks|reminders}/{id}
 *
 * {coleccion} es "users" en produccion y otra (ej. "dev_users") en desarrollo local
 * (FIREBASE_COLLECTION): cada servidor tiene su propia base y su propia llave por usuario,
 * asi que no deben mezclar sus datos en Firestore aunque compartan el proyecto de Firebase.
 *
 * - Laravel sigue siendo la fuente principal (panel admin, Google, reportes).
 * - Cada vez que un registro cambia (desde la app, la API, Google o una importacion) se encola aqui
 *   y al terminar la peticion se escribe en Firestore en un solo lote.
 * - Los telefonos del usuario escuchan esas colecciones con onSnapshot y reciben el cambio al instante.
 * - Titulos, nombres, descripciones y ubicaciones se guardan CIFRADOS con la llave del usuario:
 *   en la consola de Firebase solo se ve texto cifrado.
 * - Reglas de seguridad (firebase/firestore.rules): cada usuario solo puede LEER su carpeta; nadie escribe desde la app.
 */
class FirestoreMirror
{
    /** Campos que se cifran antes de subirlos. */
    public const ENCRYPTED_FIELDS = ['title', 'name', 'description', 'location'];

    /** Campos internos que no viajan a Firestore. */
    private const HIDDEN_FIELDS = ['user_id', 'google_event_id', 'google_task_id'];

    /** @var array<string, array{0:string,1:string}> "tabla|id" => [tabla, id] */
    private array $pending = [];

    private bool $flushScheduled = false;

    public function __construct(private FirebaseClient $firebase) {}

    /** Coleccion raiz de este servidor en Firestore (la app la recibe en el perfil del usuario). */
    public static function collection(): string
    {
        $name = (string) config('services.firebase.collection', 'users');

        return preg_match('/^[a-z_]{1,40}$/', $name) ? $name : 'users';
    }

    public function enabled(): bool
    {
        return $this->firebase->serverConfigured();
    }

    /** Encola un registro que cambio (lo llaman los eventos de Eloquent, ver AppServiceProvider). */
    public function queue(Model $model): void
    {
        if (! $this->enabled()) {
            return;
        }
        $table = array_search(get_class($model), SyncService::TABLES, true);
        if ($table === false) {
            return;
        }
        $this->pending[$table.'|'.$model->getKey()] = [$table, (string) $model->getKey()];

        if (! $this->flushScheduled) {
            $this->flushScheduled = true;
            app()->terminating(fn () => $this->flush());
        }
    }

    /** Escribe en Firestore todo lo encolado (nunca hace fallar la peticion original). */
    public function flush(): void
    {
        $pending = $this->pending;
        $this->pending = [];
        $this->flushScheduled = false;
        if (! $pending || ! $this->enabled()) {
            return;
        }

        try {
            $byTable = [];
            foreach ($pending as [$table, $id]) {
                $byTable[$table][] = $id;
            }

            $writes = [];
            $users = [];
            foreach ($byTable as $table => $ids) {
                $query = SyncService::TABLES[$table]::withTrashed()->whereIn('id', $ids);
                if ($table === 'tasks') {
                    $query->with('tags:id');
                }
                foreach ($query->get() as $model) {
                    $user = $users[$model->user_id] ??= User::find($model->user_id);
                    if ($user?->firebase_uid) {
                        $writes[] = ['update' => $this->document($user, $table, $model)];
                    }
                }
            }

            $this->commit($writes);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /** Sube todos los datos de un usuario (al vincular su cuenta de Firebase). Devuelve cuantos documentos subio. */
    public function mirrorUser(User $user): int
    {
        if (! $this->enabled() || ! $user->firebase_uid) {
            return 0;
        }

        $writes = [];
        foreach (SyncService::TABLES as $table => $class) {
            $query = $class::ownedBy($user->id);
            if ($table === 'tasks') {
                $query->with('tags:id');
            }
            foreach ($query->get() as $model) {
                $writes[] = ['update' => $this->document($user, $table, $model)];
            }
        }
        $this->commit($writes);

        return count($writes);
    }

    /** Borra la carpeta del usuario en Firestore (cuando elimina su cuenta). */
    public function deleteUser(string $firebaseUid): void
    {
        if (! $this->enabled()) {
            return;
        }

        $writes = [];
        foreach (array_keys(SyncService::TABLES) as $table) {
            $pageToken = null;
            do {
                $response = $this->firebase->http()->get($this->baseUrl()."/".self::collection()."/{$firebaseUid}/{$table}", array_filter([
                    'pageSize' => 300,
                    'mask.fieldPaths' => 'id',
                    'pageToken' => $pageToken,
                ]))->throw();
                foreach ($response->json('documents', []) as $doc) {
                    $writes[] = ['delete' => $doc['name']];
                }
                $pageToken = $response->json('nextPageToken');
            } while ($pageToken);
        }
        $this->commit($writes);
    }

    /** Documento de Firestore con los mismos campos que recibe el movil en /sync. */
    public function document(User $user, string $table, Model $model): array
    {
        $data = array_diff_key(SyncService::serialize($table, $model), array_flip(self::HIDDEN_FIELDS));
        $key = $user->dataKey();
        foreach (self::ENCRYPTED_FIELDS as $field) {
            if (isset($data[$field]) && is_string($data[$field])) {
                $data[$field] = DataCipher::encrypt($data[$field], $key);
            }
        }

        return [
            'name' => $this->documentPrefix()."/".self::collection()."/{$user->firebase_uid}/{$table}/{$model->getKey()}",
            'fields' => $this->encodeFields($data),
        ];
    }

    private function commit(array $writes): void
    {
        foreach (array_chunk($writes, 450) as $chunk) {
            $this->firebase->http()->post($this->baseUrl().':commit', ['writes' => $chunk])->throw();
        }
    }

    private function documentPrefix(): string
    {
        return 'projects/'.$this->firebase->projectId().'/databases/(default)/documents';
    }

    private function baseUrl(): string
    {
        return 'https://firestore.googleapis.com/v1/'.$this->documentPrefix();
    }

    private function encodeFields(array $data): array
    {
        return array_map(fn ($v) => $this->encodeValue($v), $data);
    }

    /** Convierte un valor PHP al formato de la API REST de Firestore. */
    private function encodeValue(mixed $value): array
    {
        return match (true) {
            $value === null => ['nullValue' => null],
            is_bool($value) => ['booleanValue' => $value],
            is_int($value) => ['integerValue' => (string) $value],
            is_float($value) => ['doubleValue' => $value],
            $value instanceof \DateTimeInterface => ['stringValue' => $value->format('Y-m-d\TH:i:s.u\Z')],
            $value instanceof \Illuminate\Support\Collection => $this->encodeValue($value->all()),
            is_array($value) && array_is_list($value) => ['arrayValue' => ['values' => array_map(fn ($v) => $this->encodeValue($v), $value)]],
            is_array($value) => ['mapValue' => ['fields' => $this->encodeFields($value)]],
            default => ['stringValue' => (string) $value],
        };
    }
}
