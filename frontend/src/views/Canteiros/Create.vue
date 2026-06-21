<template>
  <div class="container mt-4">
    <div class="row">
      <div class="col-md-8 mx-auto">
        <div class="card shadow">
          <div class="card-body">
            <h2 class="mb-4">Novo Canteiro</h2>

            <div v-if="errorMessage" class="alert alert-danger">
              {{ errorMessage }}
            </div>

            <form @submit.prevent="handleSubmit">
              <div class="mb-3">
                <label class="form-label">Número Identificador <span class="text-danger">*</span></label>
                <input v-model="form.numero_identificador" class="form-control" placeholder="Ex: C-001" />
              </div>

              <div class="mb-3">
                <label class="form-label">Tamanho (m²) <span class="text-danger">*</span></label>
                <input v-model="form.tamanho_m2" type="number" step="0.01" min="0" class="form-control" />
              </div>

              <div class="mb-3">
                <label class="form-label">Localização <span class="text-danger">*</span></label>
                <input v-model="form.localizacao" class="form-control" placeholder="Ex: Setor A - Fileira 1" />
              </div>

              <div class="mb-3">
                <label class="form-label">Horta Vinculada <span class="text-danger">*</span></label>
                <select v-model="form.horta_uuid" class="form-select">
                  <option value="">Selecione uma horta</option>
                  <option v-for="h in hortas" :key="h.uuid" :value="h.uuid">
                    {{ h.nome }}
                  </option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Canteirista Proprietário (opcional)</label>
                <select v-model="form.usuario_uuid" class="form-select">
                  <option value="">Sem proprietário</option>
                  <option v-for="u in usuarios" :key="u.uuid" :value="u.uuid">
                    {{ u.nome }} - {{ u.cpf }}
                  </option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Status</label>
                <select v-model="form.status" class="form-select">
                  <option value="Disponível">Disponível</option>
                  <option value="Ocupado">Ocupado</option>
                  <option value="Em Preparo">Em Preparo</option>
                </select>
              </div>

              <div class="mb-3">
                <label class="form-label">Plantio Atual</label>
                <input v-model="form.plantio_atual" class="form-control" placeholder="Ex: Tomates" />
              </div>

              <div class="mb-3">
                <label class="form-label">Data da Última Colheita</label>
                <input v-model="form.data_ultima_colheita" type="date" class="form-control" />
              </div>

              <div class="d-flex gap-2">
                <button type="submit" class="btn btn-success">Salvar</button>
                <router-link to="/canteiros" class="btn btn-secondary">Cancelar</router-link>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
const STORAGE_KEY = 'canteiros_admin'

const hortas = [
  { uuid: 'horta-1', nome: 'Horta Comunitária Adhemar Garcia' },
  { uuid: 'horta-2', nome: 'Horta Sabor da Terra' }
]

const usuarios = [
  { uuid: 'usuario-1', nome: 'José Silva', cpf: '123.456.789-00' },
  { uuid: 'usuario-2', nome: 'Maria Souza', cpf: '987.654.321-00' }
]

export default {
  name: 'CanteirosCreate',
  data() {
    return {
      errorMessage: '',
      hortas,
      usuarios,
      form: {
        numero_identificador: '',
        tamanho_m2: '',
        localizacao: '',
        horta_uuid: '',
        usuario_uuid: '',
        status: 'Disponível',
        plantio_atual: '',
        data_ultima_colheita: ''
      }
    }
  },
  methods: {
    handleSubmit() {
      this.errorMessage = ''

      if (!this.form.numero_identificador.trim()) {
        this.errorMessage = 'Número identificador é obrigatório.'
        return
      }

      if (!this.form.tamanho_m2 || Number(this.form.tamanho_m2) <= 0) {
        this.errorMessage = 'Tamanho deve ser maior que zero.'
        return
      }

      if (!this.form.localizacao.trim()) {
        this.errorMessage = 'Localização é obrigatória.'
        return
      }

      if (!this.form.horta_uuid) {
        this.errorMessage = 'Selecione uma horta.'
        return
      }

      const lista = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]')
      const horta = this.hortas.find(h => h.uuid === this.form.horta_uuid)
      const usuario = this.usuarios.find(u => u.uuid === this.form.usuario_uuid)

      const novo = {
        id: Date.now().toString(),
        numero_identificador: this.form.numero_identificador,
        tamanho_m2: Number(this.form.tamanho_m2),
        localizacao: this.form.localizacao,
        horta_uuid: this.form.horta_uuid,
        horta_nome: horta?.nome || '',
        usuario_responsavel_uuid: usuario?.uuid || '',
        usuario_responsavel: usuario?.nome || '',
        usuario_responsavel_cpf: usuario?.cpf || '',
        plantio_atual: this.form.plantio_atual,
        data_ultima_colheita: this.form.data_ultima_colheita,
        status: this.form.usuario_uuid ? 'Ocupado' : this.form.status,
        historico: [
          'Canteiro cadastrado',
          this.form.usuario_uuid ? 'Vínculo de proprietário criado automaticamente' : 'Cadastrado sem proprietário'
        ]
      }

      lista.push(novo)
      localStorage.setItem(STORAGE_KEY, JSON.stringify(lista))
      this.$router.push('/canteiros')
    }
  }
}
</script>
