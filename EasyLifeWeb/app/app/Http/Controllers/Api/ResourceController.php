<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Dates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * CRUD generico para los recursos del usuario (eventos, tareas, proyectos...).
 * Cada controlador hijo indica el modelo y, opcionalmente, filtros y relaciones.
 * Un usuario solo puede ver y modificar SUS registros (si no, responde 404).
 */
abstract class ResourceController extends Controller
{
    /** Clase del modelo Eloquent, ej: Event::class */
    protected string $model;

    /** Campos tipo fecha/hora que se normalizan a UTC. */
    protected array $dateFields = [];

    /** Relaciones a cargar al listar. */
    protected array $with = [];

    protected string $orderBy = 'created_at';

    public function index(Request $request): JsonResponse
    {
        $query = $this->model::ownedBy($request->user()->id)->with($this->with);

        if ($request->filled('updated_since')) {
            $query->where('updated_at', '>=', Dates::toUtc($request->input('updated_since')));
        }

        $this->applyFilters($query, $request);

        $items = $query->orderBy($this->orderBy)->get();

        return response()->json(['data' => $items->map(fn ($m) => $this->transform($m))]);
    }

    public function store(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $data = $request->validate($this->model::rules($userId) + ['id' => ['nullable', 'uuid', 'unique:'.(new $this->model)->getTable().',id']]);

        $model = new $this->model;
        $model->fill($this->prepare($data));
        $model->user_id = $userId;
        $model->client_updated_at = now();
        if (! empty($data['id'])) {
            $model->id = $data['id'];
        }
        $model->save();
        $this->afterSave($model, $data);

        return response()->json(['data' => $this->transform($model->fresh($this->with))], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json(['data' => $this->transform($this->findOwned($request, $id)->load($this->with))]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $model = $this->findOwned($request, $id);

        // En actualizaciones los campos son opcionales ("sometimes")
        $rules = collect($this->model::rules($request->user()->id))
            ->map(fn ($r) => array_merge(['sometimes'], (array) $r))->all();
        $data = $request->validate($rules);

        $model->fill($this->prepare($data));
        $model->client_updated_at = now();
        $model->save();
        $this->afterSave($model, $data);

        return response()->json(['data' => $this->transform($model->fresh($this->with))]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->findOwned($request, $id)->delete(); // borrado logico

        return response()->json(['message' => 'Registro eliminado']);
    }

    // ----------------- Puntos de extension -----------------

    protected function applyFilters(Builder $query, Request $request): void {}

    protected function afterSave(Model $model, array $data): void {}

    protected function transform(Model $model): array
    {
        return $model->toArray();
    }

    /** Deja solo los campos permitidos y convierte fechas a UTC. */
    protected function prepare(array $data): array
    {
        $data = array_intersect_key($data, array_flip($this->model::syncFields()));
        foreach ($this->dateFields as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = Dates::toUtc($data[$field]);
            }
        }

        return $data;
    }

    protected function findOwned(Request $request, string $id): Model
    {
        return $this->model::ownedBy($request->user()->id)->findOrFail($id);
    }
}
