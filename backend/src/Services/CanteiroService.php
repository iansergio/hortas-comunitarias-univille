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
        // TODO: Reativar verificação de permissões em produção
        return $this->canteiroRepository->findAllWhere(['excluido' => 0]);

        /*
        $cargo = $this->getCargoSlug($payloadUsuarioLogado);
        echo "CARGO ===========> " . $cargo;
        switch ($cargo) {
            case 'admin_plataforma':
                return $this->canteiroRepository->findAllWhere(['excluido' => 0]);

            case 'admin_associacao_geral':
                // hortas da associação do usuário
                $hortas = $this->hortaService->findAllWhere([], $payloadUsuarioLogado);
                $hortasUuids = $hortas->pluck('uuid')->toArray();

                return $this->canteiroRepository->findAllWhere(['excluido' => 0])
                    ->filter(fn($canteiro) => in_array($canteiro->horta_uuid, $hortasUuids));

            case 'admin_horta_geral':
                return $this->canteiroRepository->findAllWhere([
                    'excluido' => 0,
                    'horta_uuid' => $payloadUsuarioLogado['horta_uuid']
                ]);

            default:
                // demais cargos só leitura, mas filtrando pela associação/horta
                return $this->canteiroRepository->findAllWhere(['excluido' => 0])
                    ->filter(function($canteiro) use ($payloadUsuarioLogado) {
                        $horta = $this->hortaService->findByUuid($canteiro->horta_uuid, $payloadUsuarioLogado);
                        return $horta->associacao_vinculada_uuid === $payloadUsuarioLogado['associacao_uuid'];
                    });
        }
        */
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

        $data = [
            'excluido' => 1,
            'usuario_alterador_uuid' => $payloadUsuarioLogado['usuario_uuid'],
        ];

        return $this->canteiroRepository->delete($canteiro, $data);
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
