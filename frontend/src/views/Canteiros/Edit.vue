<template>
  <div class="container mt-4">
    <div class="row">
      <div class="col-md-8 mx-auto">
        <div class="card shadow">
          <div class="card-body">
            <h2 class="mb-4">Editar Canteiro</h2>

            <div v-if="errorMessage" class="alert alert-danger">
              {{ errorMessage }}
            </div>

            <form v-if="form" @submit.prevent="handleSubmit">
              <div class="mb-3">
                <label class="form-label">Número Identificador</label>
                <input v-model="form.numero_identificador" class="form-control" />
              </div>

              <div class="mb-3">
                <label class="form-label">Tamanho (m²)</label>
                <input v-model="form.tamanho_m2" type="number" step="0.01" min="0" class="form-control" />
              </div>

              <div class="mb-3">
                <label class="form-label">Localização</label>
                <input v-model="form.localizacao" class="form-control" />
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
                <input v-model="form.plantio_atual" class="form-control" />
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

            <div v-else class="text-muted">
              Canteiro não encontrado.
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
const STORAGE_KEY = 'canteiros_admin'

export default {
  name: 'CanteirosEdit',
  data() {
    return {
      errorMessage: '',
      form: null
    }
  },
  mounted() {
    const lista = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]')
    const item = lista.find(c => c.id === this.$route.params.id)

    if (item) {
      this.form = { ...item }
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

      const lista = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]')
      const index = lista.findIndex(c => c.id === this.$route.params.id)

      if (index >= 0) {
        this.form.tamanho_m2 = Number(this.form.tamanho_m2)
        this.form.historico = [
          ...(this.form.historico || []),
          'Canteiro editado'
        ]

        lista[index] = this.form
        localStorage.setItem(STORAGE_KEY, JSON.stringify(lista))
      }

      this.$router.push('/canteiros')
    }
  }
}
</script>
