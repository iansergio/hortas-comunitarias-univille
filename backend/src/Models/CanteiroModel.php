<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CanteiroModel extends Model
{
    protected $table = 'canteiros';
    protected $primaryKey = 'uuid';
    public $incrementing = false;

    public $timestamps = true;
    const CREATED_AT = 'data_de_criacao';
    const UPDATED_AT = 'data_de_ultima_alteracao';

    protected $fillable = [
        'uuid',
        'numero_identificador',
        'tamanho_m2',
        'status',
        'localizacao',
        'plantio_atual',
        'data_ultima_colheita',
        'horta_uuid',
        'excluido',
        'usuario_criador_uuid',
        'usuario_alterador_uuid',
        'usuario_anterior_uuid',
    ];

    // Relacionamentos
    public function horta()
    {
        return $this->belongsTo(HortaModel::class, 'horta_uuid', 'uuid');
    }

    public function usuarioCriador()
    {
        return $this->belongsTo(UsuarioModel::class, 'usuario_criador_uuid', 'uuid');
    }

    public function usuarioAlterador()
    {
        return $this->belongsTo(UsuarioModel::class, 'usuario_alterador_uuid', 'uuid');
    }

    public function usuarioAnterior()
    {
        return $this->belongsTo(UsuarioModel::class, 'usuario_anterior_uuid', 'uuid');
    }

    public function usuarios()
    {
        return $this->belongsToMany(UsuarioModel::class, 'canteiros_e_usuarios', 'canteiro_uuid', 'usuario_uuid')
            ->withPivot('tipo_vinculo', 'percentual_responsabilidade', 'ativo', 'excluido')
            ->wherePivot('excluido', 0)
            ->wherePivot('ativo', 1);
    }

    public function vinculos()
    {
        return $this->hasMany(CanteiroEUsuarioModel::class, 'canteiro_uuid', 'uuid')
            ->where('excluido', 0);
    }
}
