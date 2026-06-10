<?php

namespace App\Services;

use App\Models\CanteiroModel;
use App\Repositories\CanteiroRepository;
use Respect\Validation\Validator as v;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Ramsey\Uuid\Uuid;

class CanteiroService
{
    protected CanteiroRepository $canteiroRepository;
    protected HortaService $hortaService; 
    protected UsuarioService $usuarioService; 
    protected CargoService $cargoService;
    protected CanteiroEUsuarioService $canteiroEUsuarioService;

    public function __construct(
        CanteiroRepository $canteiroRepository, 
        HortaService $hortaService, 
        UsuarioService $usuarioService,
        CargoService $cargoService,
        CanteiroEUsuarioService $canteiroEUsuarioService
    ) {
        $this->canteiroRepository = $canteiroRepository; 
        $this->hortaService = $hortaService;
        $this->usuarioService = $usuarioService;
        $this->cargoService = $cargoService;
        $this->canteiroEUsuarioService = $canteiroEUsuarioService;
    }

    public function findAllWhere(array $payloadUsuarioLogado): Collection
    {
        $cargo = $this->getCargoSlug($payloadUsuarioLogado);
        switch ($cargo) {
            case 'admin_plataforma':
                return $this->canteiroRepository->findAllWhere(['excluido' => 0]);

            case 'admin_associacao_geral':
                $hortas = $this->hortaService->findAllWhere([], $payloadUsuarioLogado);
                $hortasUuids = $hortas->pluck('uuid')->toArray();

                return $this->canteiroRepository->findAllWhere(['excluido' => 0])
                    ->filter(fn($canteiro) => in_array($canteiro->horta_uuid, $hortasUuids));

            case 'admin_horta_geral':
                return $this->canteiroRepository->findAllWhere([
                    'excluido' => 0,
                    'horta_uuid' => $payloadUsuarioLogado['horta_uuid']
                ]);

            case 'canteirista':
                return CanteiroModel::whereHas('usuarios', function ($q) use ($payloadUsuarioLogado) {
                    $q->where('usuario_uuid', $payloadUsuarioLogado['usuario_uuid']);
                })->where('excluido', 0)->with('horta')->get();

            default:
                // Other roles (e.g. dependente) - restrict to linked canteiros
                return CanteiroModel::whereHas('usuarios', function ($q) use ($payloadUsuarioLogado) {
                    $q->where('usuario_uuid', $payloadUsuarioLogado['usuario_uuid']);
                })->where('excluido', 0)->with('horta')->get();
        }
    }
    
    public function findByUuid(string $uuid, array $payloadUsuarioLogado): ?CanteiroModel
    {
        // TODO: Reativar verificações de permissão em produção
        
        $canteiro = $this->canteiroRepository->findByUuid($uuid);
        if (!$canteiro || $canteiro->excluido) {
            throw new Exception('Canteiro não encontrado');
        }
        
        return $canteiro;
    }

    public function create(array $data, array $payloadUsuarioLogado): CanteiroModel
    {
        // TODO: Reativar verificações de permissão em produção
        
        $guarded = ['uuid','usuario_criador_uuid','data_de_criacao','data_de_ultima_alteracao','excluido'];
        foreach ($guarded as $g) unset($data[$g]);
        
        // Extrair usuario_uuid se fornecido (para criar ownership)
        $ownerUuid = $data['usuario_uuid'] ?? null;
        unset($data['usuario_uuid']);
        
        // Mapear campo 'nome' para 'numero_identificador' se necessário
        if (isset($data['nome']) && !isset($data['numero_identificador'])) {
            $data['numero_identificador'] = $data['nome'];
            unset($data['nome']);
        }
        
        // Mapear campo 'area' para 'tamanho_m2' se necessário
        if (isset($data['area']) && !isset($data['tamanho_m2'])) {
            $data['tamanho_m2'] = $data['area'];
            unset($data['area']);
        }
        
        // Validação: Número identificador obrigatório
        if (empty($data['numero_identificador'])) {
            throw new Exception("Número identificador é obrigatório");
        }
        if (!is_string($data['numero_identificador']) || strlen(trim($data['numero_identificador'])) === 0) {
            throw new Exception("Número identificador deve ser uma string válida");
        }
        
        // Validação: Tamanho obrigatório e positivo
        if (empty($data['tamanho_m2'])) {
            throw new Exception("Tamanho em m² é obrigatório");
        }
        if (!is_numeric($data['tamanho_m2']) || $data['tamanho_m2'] <= 0) {
            throw new Exception("Tamanho deve ser um número positivo");
        }
        
        // Validação: Horta obrigatória
        if (empty($data['horta_uuid'])) {
            throw new Exception("Horta é obrigatória");
        }
        if (!is_string($data['horta_uuid'])) {
            throw new Exception("UUID da horta deve ser uma string");
        }
        
        // Validação: Horta deve existir (usar repository para validação interna)
        $horta = $this->hortaService->findByUuidForValidation($data['horta_uuid']);
        if (!$horta || $horta->excluido) {
            throw new Exception("Horta não encontrada ou foi excluída");
        }
        
        // Validação: Se proprietário foi fornecido, validar que ele existe
        if (!empty($ownerUuid)) {
            if (!is_string($ownerUuid)) {
                throw new Exception("UUID do usuário deve ser uma string");
            }
            $owner = $this->usuarioService->findByUuidForValidation($ownerUuid);
            if (!$owner || $owner->excluido) {
                throw new Exception("Usuário proprietário não encontrado ou foi excluído");
            }
        }

        $data['uuid'] = Uuid::uuid1()->toString();
        $data['usuario_criador_uuid'] = $payloadUsuarioLogado['usuario_uuid'];
        $data['usuario_alterador_uuid'] = $payloadUsuarioLogado['usuario_uuid'];
        $data['excluido'] = 0;

        $canteiro = $this->canteiroRepository->create($data);
        
        // RF08 Business Rule: Auto-create ownership relation if owner provided
        if (!empty($ownerUuid)) {
            try {
                $this->canteiroEUsuarioService->createOwnershipRelation(
                    $canteiro->uuid,
                    $ownerUuid,
                    $payloadUsuarioLogado
                );
            } catch (Exception $e) {
                // Log ownership creation failure but don't fail the canteiro creation
                // In production, consider rollback or scheduled retry
                error_log("Aviso: Falha ao criar vínculo de propriedade para canteiro {$canteiro->uuid}: " . $e->getMessage());
            }
        }
        
        return $canteiro;
    }

    public function update(string $uuid, array $data, array $payloadUsuarioLogado): CanteiroModel
    {
        // TODO: Reativar verificações de permissão em produção
        
        $canteiro = $this->canteiroRepository->findByUuid($uuid);
        if (!$canteiro || $canteiro->excluido) {
            throw new Exception('Canteiro não encontrado');
        }
        
        // Mapear campo 'nome' para 'numero_identificador' se necessário
        if (isset($data['nome']) && !isset($data['numero_identificador'])) {
            $data['numero_identificador'] = $data['nome'];
            unset($data['nome']);
        }
        
        // Mapear campo 'area' para 'tamanho_m2' se necessário
        if (isset($data['area']) && !isset($data['tamanho_m2'])) {
            $data['tamanho_m2'] = $data['area'];
            unset($data['area']);
        }

        $data['usuario_alterador_uuid'] = $payloadUsuarioLogado['usuario_uuid'];
        return $this->canteiroRepository->update($canteiro, $data);
    }
    
    public function delete(string $uuid, array $payloadUsuarioLogado): bool
    {
        // TODO: Reativar verificações de permissão em produção
        
        $canteiro = $this->canteiroRepository->findByUuid($uuid);
        if (!$canteiro || $canteiro->excluido) {
            throw new Exception('Canteiro não encontrado');
        }

        $vinculosAtivos = $canteiro->vinculos()->where('ativo', 1)->count();
        if ($vinculosAtivos > 0) {
            throw new Exception('Não é possível excluir o canteiro pois existem usuários vinculados ativamente a ele.');
        }

        $data = [
            'excluido' => 1,
            'usuario_alterador_uuid' => $payloadUsuarioLogado['usuario_uuid'],
        ];

        return $this->canteiroRepository->delete($canteiro, $data);
    }

    public function getSummaryMeusCanteiros($usuarioUuid)
    {
        $canteiros = CanteiroModel::whereHas('usuarios', function ($q) use ($usuarioUuid) {
            $q->where('usuario_uuid', $usuarioUuid);
        })->where('excluido', 0)->get();

        $totalArea = $canteiros->sum('tamanho_m2');
        $totalCanteiros = $canteiros->count();
        $canteirosAtivos = $canteiros->filter(function ($c) {
            return in_array($c->status, ['Ocupado', 'Ativo']);
        })->count();

        return [
            'total_canteiros' => $totalCanteiros,
            'total_area_m2' => $totalArea,
            'canteiros_ativos' => $canteirosAtivos,
            'media_area_m2' => $totalCanteiros > 0 ? round($totalArea / $totalCanteiros, 2) : 0,
        ];
    }

    public function getSummaryAdmin($hortaUuid = null, array $payloadUsuarioLogado = [])
    {
        $query = CanteiroModel::where('excluido', 0);

        if (!empty($payloadUsuarioLogado)) {
            $cargo = $this->getCargoSlug($payloadUsuarioLogado);
            switch ($cargo) {
                case 'admin_plataforma':
                    break;

                case 'admin_associacao_geral':
                    $hortas = $this->hortaService->findAllWhere([], $payloadUsuarioLogado);
                    $hortasUuids = $hortas->pluck('uuid')->toArray();
                    $query->whereIn('horta_uuid', $hortasUuids);
                    break;

                case 'admin_horta_geral':
                    $query->where('horta_uuid', $payloadUsuarioLogado['horta_uuid']);
                    break;

                default:
                    throw new Exception("Acesso negado para este recurso");
            }
        }

        if ($hortaUuid) {
            $query->where('horta_uuid', $hortaUuid);
        }

        $total = $query->count();
        $ocupados = (clone $query)->where('status', 'Ocupado')->count();
        $disponiveis = (clone $query)->where('status', 'Disponível')->count();
        $emPreparo = (clone $query)->where('status', 'Em Preparo')->count();

        return [
            'total' => $total,
            'ocupados' => $ocupados,
            'disponiveis' => $disponiveis,
            'em_preparo' => $emPreparo,
            'percentual_ocupacao' => $total > 0 ? round(($ocupados / $total) * 100, 2) : 0,
        ];
    }

    public function findAllWhereEnhanced(array $params = [], array $payloadUsuarioLogado = []): Collection
    {
        $query = CanteiroModel::where('excluido', 0);

        if (!empty($payloadUsuarioLogado)) {
            $cargo = $this->getCargoSlug($payloadUsuarioLogado);
            switch ($cargo) {
                case 'admin_plataforma':
                    break;

                case 'admin_associacao_geral':
                    $hortas = $this->hortaService->findAllWhere([], $payloadUsuarioLogado);
                    $hortasUuids = $hortas->pluck('uuid')->toArray();
                    $query->whereIn('horta_uuid', $hortasUuids);
                    break;

                case 'admin_horta_geral':
                    $query->where('horta_uuid', $payloadUsuarioLogado['horta_uuid']);
                    break;

                case 'canteirista':
                default:
                    $query->whereHas('usuarios', function ($q) use ($payloadUsuarioLogado) {
                        $q->where('usuario_uuid', $payloadUsuarioLogado['usuario_uuid']);
                    });
                    break;
            }
        }

        // Search by number, horta name, canteirista name, CPF, localizacao
        if (!empty($params['search'])) {
            $search = '%' . trim($params['search']) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('numero_identificador', 'like', $search)
                  ->orWhere('localizacao', 'like', $search)
                  ->orWhereHas('horta', function ($qh) use ($search) {
                      $qh->where('nome_da_horta', 'like', $search);
                  })
                  ->orWhereHas('usuarios', function ($qu) use ($search) {
                      $qu->where('nome_completo', 'like', $search)
                        ->orWhere('cpf', 'like', $search);
                  });
            });
        }

        // Filter by status
        if (!empty($params['status'])) {
            $query->where('status', $params['status']);
        }

        // Filter by horta
        if (!empty($params['horta_uuid'])) {
            $query->where('horta_uuid', $params['horta_uuid']);
        }

        return $query->with(['horta', 'usuarioCriador', 'usuarioAlterador', 'usuarios'])
                     ->get();
    }

    /** Helpers **/
    private function getCargoSlug(array $payloadUsuarioLogado): string
    {
        return $this->cargoService->findByUuidInternal($payloadUsuarioLogado['cargo_uuid'])->slug;
    }

    private function assertCargoPodeAlterar(string $cargo): void
    {
        if (!in_array($cargo, ['admin_plataforma','admin_associacao_geral','admin_horta_geral'])) {
            throw new Exception("Você não tem permissão para criar/alterar/deletar canteiros");
        }
    }

    private function assertPertencimento(string $cargo, $horta, array $payloadUsuarioLogado): void
    {
        switch ($cargo) {
            case 'admin_associacao_geral':
                if ($horta->associacao_vinculada_uuid !== $payloadUsuarioLogado['associacao_uuid']) {
                    throw new Exception("Horta não pertence à sua associação");
                }
                break;

            case 'admin_horta_geral':
                if ($horta->uuid !== $payloadUsuarioLogado['horta_uuid']) {
                    throw new Exception("Horta não pertence a você");
                }
                break;
        }
    }
}
