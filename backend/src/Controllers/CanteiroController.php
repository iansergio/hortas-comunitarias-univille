<?php

namespace App\Controllers;

use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;
use App\Services\CanteiroService;

class CanteiroController
{
    protected CanteiroService $canteiroService;

    public function __construct(CanteiroService $canteiroService)
    {
        $this->canteiroService = $canteiroService;
    }

    
    public function list(Request $request, Response $response)
    {
        $payloadUsuarioLogado = [
            'usuario_uuid' => $request->getAttribute('usuario_uuid'),
            'cargo_uuid' => $request->getAttribute('cargo_uuid'),
            'associacao_uuid' => $request->getAttribute('associacao_uuid'),
            'horta_uuid' => $request->getAttribute('horta_uuid'),
        ];

        $canteiros = $this->canteiroService->findAllWhere($payloadUsuarioLogado);
        
        // Formatar resposta para o frontend
        $canteirosFormatados = $canteiros->map(function($canteiro) {
            return [
                'id' => $canteiro->uuid,
                'nome' => $canteiro->numero_identificador ?? '—',
                'numero_identificador' => $canteiro->numero_identificador ?? '—',
                'area' => $canteiro->tamanho_m2 ?? 0,
                'tamanho_m2' => $canteiro->tamanho_m2 ?? 0,
                'horta_nome' => $canteiro->horta->nome_da_horta ?? '—',
                'horta_uuid' => $canteiro->horta_uuid,
                'plantio_atual' => $canteiro->plantio_atual ?? '—',
                'data_ultima_colheita' => $canteiro->data_ultima_colheita ?? null,
                'status' => $canteiro->status ?? 'Disponível',
                'ativo' => !$canteiro->excluido,
            ];
        });
        
        $response->getBody()->write(json_encode($canteirosFormatados));
        return $response->withStatus(200);
    }

    public function get(Request $request, Response $response, array $args)
    {
        $payloadUsuarioLogado = [
            'usuario_uuid' => $request->getAttribute('usuario_uuid'),
            'cargo_uuid' => $request->getAttribute('cargo_uuid'),
            'associacao_uuid' => $request->getAttribute('associacao_uuid'),
            'horta_uuid' => $request->getAttribute('horta_uuid'),
        ];
        $canteiro = $this->canteiroService->findByUuid($args['uuid'],$payloadUsuarioLogado);
        if (!$canteiro) return $response->withStatus(404);

        // Formatar resposta para o frontend
        $canteiroFormatado = [
            'id' => $canteiro->uuid,
            'nome' => $canteiro->numero_identificador ?? '',
            'numero_identificador' => $canteiro->numero_identificador ?? '',
            'area' => $canteiro->tamanho_m2 ?? 0,
            'tamanho_m2' => $canteiro->tamanho_m2 ?? 0,
            'horta_uuid' => $canteiro->horta_uuid,
            'horta_nome' => $canteiro->horta->nome_da_horta ?? '',
            'ativo' => !$canteiro->excluido,
        ];

        $response->getBody()->write(json_encode($canteiroFormatado));
        return $response->withStatus(200);
    }

    public function create(Request $request, Response $response)
    {
        $payloadUsuarioLogado = [
            'usuario_uuid' => $request->getAttribute('usuario_uuid'),
            'cargo_uuid' => $request->getAttribute('cargo_uuid'),
            'associacao_uuid' => $request->getAttribute('associacao_uuid'),
            'horta_uuid' => $request->getAttribute('horta_uuid'),
        ];
        $data = (array)$request->getParsedBody();
        $uuidUsuarioLogado = $request->getAttribute('usuario_uuid');
        $canteiro = $this->canteiroService->create($data,$payloadUsuarioLogado);

        // Formatar resposta
        $canteiroFormatado = [
            'id' => $canteiro->uuid,
            'numero_identificador' => $canteiro->numero_identificador,
            'tamanho_m2' => $canteiro->tamanho_m2,
            'horta_uuid' => $canteiro->horta_uuid,
        ];

        $response->getBody()->write(json_encode($canteiroFormatado));
        return $response->withStatus(201);
    }

    public function update(Request $request, Response $response, array $args)
    {
        $payloadUsuarioLogado = [
            'usuario_uuid' => $request->getAttribute('usuario_uuid'),
            'cargo_uuid' => $request->getAttribute('cargo_uuid'),
            'associacao_uuid' => $request->getAttribute('associacao_uuid'),
            'horta_uuid' => $request->getAttribute('horta_uuid'),
        ];
        $data = (array)$request->getParsedBody();
        $uuidUsuarioLogado = $request->getAttribute('usuario_uuid');
        $canteiro = $this->canteiroService->update($args['uuid'], $data,$payloadUsuarioLogado);

        // Formatar resposta
        $canteiroFormatado = [
            'id' => $canteiro->uuid,
            'numero_identificador' => $canteiro->numero_identificador,
            'tamanho_m2' => $canteiro->tamanho_m2,
            'horta_uuid' => $canteiro->horta_uuid,
        ];

        $response->getBody()->write(json_encode($canteiroFormatado));
        return $response->withStatus(200);
    }

    public function delete(Request $request, Response $response, array $args)
    {   
        $payloadUsuarioLogado = [
            'usuario_uuid' => $request->getAttribute('usuario_uuid'),
            'cargo_uuid' => $request->getAttribute('cargo_uuid'),
            'associacao_uuid' => $request->getAttribute('associacao_uuid'),
            'horta_uuid' => $request->getAttribute('horta_uuid'),
        ];
        $uuidUsuarioLogado = $request->getAttribute('usuario_uuid');
        $this->canteiroService->delete($args['uuid'],$payloadUsuarioLogado);
        
        $response->getBody()->write(json_encode([
            "message" => "Registro UUID: " . $args['uuid'] . " excluído"
        ]));
        return $response->withStatus(200);
    }

    public function getSummaryMeusCanteiros(Request $request, Response $response)
    {
        $usuarioUuid = $request->getAttribute('usuario_uuid');
        $summary = $this->canteiroService->getSummaryMeusCanteiros($usuarioUuid);

        $response->getBody()->write(json_encode($summary));
        return $response->withStatus(200);
    }

    public function getSummaryAdmin(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();
        $hortaUuid = $queryParams['horta_uuid'] ?? null;
        $payloadUsuarioLogado = [
            'usuario_uuid' => $request->getAttribute('usuario_uuid'),
            'cargo_uuid' => $request->getAttribute('cargo_uuid'),
            'associacao_uuid' => $request->getAttribute('associacao_uuid'),
            'horta_uuid' => $request->getAttribute('horta_uuid'),
        ];
        $summary = $this->canteiroService->getSummaryAdmin($hortaUuid, $payloadUsuarioLogado);

        $response->getBody()->write(json_encode($summary));
        return $response->withStatus(200);
    }

    public function listEnhanced(Request $request, Response $response)
    {
        $queryParams = $request->getQueryParams();
        $params = [
            'search' => $queryParams['search'] ?? null,
            'status' => $queryParams['status'] ?? null,
            'horta_uuid' => $queryParams['horta_uuid'] ?? null,
        ];

        $payloadUsuarioLogado = [
            'usuario_uuid' => $request->getAttribute('usuario_uuid'),
            'cargo_uuid' => $request->getAttribute('cargo_uuid'),
            'associacao_uuid' => $request->getAttribute('associacao_uuid'),
            'horta_uuid' => $request->getAttribute('horta_uuid'),
        ];

        $canteiros = $this->canteiroService->findAllWhereEnhanced($params, $payloadUsuarioLogado);
        
        // Formatar resposta para o frontend
        $canteirosFormatados = $canteiros->map(function($canteiro) {
            $usuario = $canteiro->usuarios->first();
            return [
                'id' => $canteiro->uuid,
                'numero_identificador' => $canteiro->numero_identificador ?? '—',
                'tamanho_m2' => $canteiro->tamanho_m2 ?? 0,
                'status' => $canteiro->status ?? 'Disponível',
                'localizacao' => $canteiro->localizacao ?? '',
                'plantio_atual' => $canteiro->plantio_atual ?? '',
                'data_ultima_colheita' => $canteiro->data_ultima_colheita ?? null,
                'horta_nome' => $canteiro->horta->nome_da_horta ?? '—',
                'horta_uuid' => $canteiro->horta_uuid,
                'usuario_responsavel' => $usuario ? $usuario->nome_completo : '—',
                'usuario_responsavel_cpf' => $usuario ? $usuario->cpf : null,
                'usuario_responsavel_uuid' => $usuario ? $usuario->uuid : null,
                'usuario_responsavel_tipo_vinculo' => $usuario ? $usuario->pivot->tipo_vinculo : null,
                'usuario_responsavel_percentual' => $usuario ? $usuario->pivot->percentual_responsabilidade : null,
                'ativo' => !$canteiro->excluido,
            ];
        });
        
        $response->getBody()->write(json_encode($canteirosFormatados));
        return $response->withStatus(200);
    }
}
