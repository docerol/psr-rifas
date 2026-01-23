<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRifaRequest;
use App\Http\Requests\UpdateRifaRequest;
use App\Http\Resources\RifaResource;
use App\Models\Rifa;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;

class RifaController extends Controller
{
    /**
     * Lista todas as rifas disponíveis
     */
    public function index(): AnonymousResourceCollection
    {
        $rifas = Rifa::query()
            ->withCount(['orders as sold_tickets'])
            ->with(['winners' => function($query) {
                $query->select('id', 'rifa_id', 'order_id', 'position');
            }])
            ->latest()
            ->paginate(15);

        return RifaResource::collection($rifas);
    }

    /**
     * Armazena uma nova rifa
     */
    public function store(StoreRifaRequest $request): RifaResource
    {
        $data = $request->validated();
        
        // Salva a imagem e atualiza o caminho
        if ($request->hasFile('thumbnail')) {
            $path = $request->file('thumbnail')->store('rifas', 'public');
            $data['thumbnail'] = $path;
        }
        
        $rifa = Rifa::create($data);
        
        return new RifaResource($rifa);
    }

    /**
     * Exibe os detalhes de uma rifa específica
     */
    public function show(Rifa $rifa): RifaResource
    {
        $cacheKey = "rifa.{$rifa->id}";
        $cacheDuration = now()->addHours(1); // Cache por 1 hora

        return Cache::remember($cacheKey, $cacheDuration, function () use ($rifa) {
            $rifa->loadMissing([
                'winners' => function($query) {
                    $query->select('id', 'rifa_id', 'order_id', 'position', 'created_at')
                        ->with(['order' => function($query) {
                            $query->select('id', 'customer_fullname');
                        }]);
                },
                'orders' => function($query) {
                    $query->select('id', 'rifa_id', 'customer_fullname', 'status', 'created_at')
                        ->where('status', 'paid')
                        ->orderBy('created_at', 'desc')
                        ->limit(5);
                }
            ]);

            return new RifaResource($rifa);
        });
    }

    /**
     * Atualiza uma rifa existente
     */
    public function update(UpdateRifaRequest $request, Rifa $rifa): RifaResource
    {
        $data = $request->validated();
        
        // Atualiza a imagem se fornecida
        if ($request->hasFile('thumbnail')) {
            // Remove a imagem antiga se existir
            if ($rifa->thumbnail && Storage::disk('public')->exists($rifa->thumbnail)) {
                Storage::disk('public')->delete($rifa->thumbnail);
            }
            
            $path = $request->file('thumbnail')->store('rifas', 'public');
            $data['thumbnail'] = $path;
        }
        
        $rifa->update($data);
        
        // Limpa o cache desta rifa
        Cache::forget("rifa.{$rifa->id}");
        
        return new RifaResource($rifa->fresh());
    }

    /**
     * Remove uma rifa
     */
    public function destroy(Rifa $rifa): JsonResponse
    {
        // Verifica se a rifa pode ser excluída (sem pedidos associados)
        if ($rifa->orders()->exists()) {
            return response()->json([
                'message' => 'Não é possível excluir uma rifa que já possui pedidos associados.'
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        
        // Remove a imagem se existir
        if ($rifa->thumbnail && Storage::disk('public')->exists($rifa->thumbnail)) {
            Storage::disk('public')->delete($rifa->thumbnail);
        }
        
        // Limpa o cache antes de remover a rifa
        Cache::forget("rifa.{$rifa->id}");
        
        $rifa->delete();
        
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}
