<template>
  <div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h1 class="h3">Gestão de Canteiros</h1>
    </div>

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <div class="card p-3 h-100">
          <small class="text-muted">Total</small>
          <div class="h4 text-primary mt-2">{{ summary.total || 0 }}</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card p-3 h-100">
          <small class="text-muted">Ocupados</small>
          <div class="h4 text-warning mt-2">{{ summary.ocupados || 0 }}</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card p-3 h-100">
          <small class="text-muted">Disponíveis</small>
          <div class="h4 text-success mt-2">{{ summary.disponiveis || 0 }}</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card p-3 h-100">
          <small class="text-muted">Ocupação</small>
          <div class="h4 text-info mt-2">{{ summary.percentual_ocupacao || 0 }}%</div>
        </div>
      </div>
    </div>

    <!-- Search and Filters -->
    <div class="card mb-4 p-3">
      <div class="row g-3 align-items-end">
        <div class="col-md-4">
          <label class="form-label">Buscar</label>
          <input 
            v-model="searchQuery" 
            type="text" 
            class="form-control" 
            placeholder="Número, nome, CPF..."
            @keyup.enter="filtrar"
          >
        </div>
        <div class="col-md-2">
          <label class="form-label">Status</label>
          <select v-model="filterStatus" class="form-select">
            <option value="">Todos</option>
            <option value="Disponível">Disponível</option>
            <option value="Ocupado">Ocupado</option>
            <option value="Em Preparo">Em Preparo</option>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Horta</label>
          <select v-model="filterHorta" class="form-select">
            <option value="">Todas as hortas</option>
            <option v-for="horta in hortas" :key="horta.uuid" :value="horta.uuid">
              {{ horta.nome_da_horta }}
            </option>
          </select>
        </div>
        <div class="col-md-3">
          <button @click="filtrar" class="btn btn-primary w-100">
            <i class="fas fa-search"></i> Filtrar
          </button>
        </div>
      </div>
    </div>

    <!-- Canteiros Table -->
    <div class="card">
      <div v-if="loading" class="card-body text-center py-5">
        <div class="spinner-border" role="status">
          <span class="visually-hidden">Carregando...</span>
        </div>
      </div>

      <div v-else-if="canteiros.length === 0" class="card-body">
        <p class="text-muted text-center mb-0">Nenhum canteiro encontrado</p>
      </div>

      <div v-else class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Número</th>
              <th>Horta</th>
              <th>Localização</th>
              <th>Tamanho</th>
              <th>Status</th>
              <th>Plantio</th>
              <th>Responsável</th>
              <th>Ações</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="canteiro in canteiros" :key="canteiro.id">
              <td class="fw-bold">{{ canteiro.numero_identificador }}</td>
              <td>{{ canteiro.horta_nome }}</td>
              <td>{{ canteiro.localizacao || '—' }}</td>
              <td>{{ canteiro.tamanho_m2 }}m²</td>
              <td>
                <span :class="`badge bg-${statusColor(canteiro.status)}`">
                  {{ canteiro.status }}
                </span>
              </td>
              <td>{{ canteiro.plantio_atual || '—' }}</td>
              <td>{{ canteiro.usuario_responsavel || '—' }}</td>
              <td>
                <router-link :to="`/canteiros/${canteiro.id}/edit`" class="btn btn-sm btn-warning me-1">
                  <i class="fas fa-edit"></i>
                </router-link>
                <button @click="deletarCanteiro(canteiro.id)" class="btn btn-sm btn-danger">
                  <i class="fas fa-trash"></i>
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script>
import { mapState, mapActions } from 'vuex';

export default {
  name: 'CanteirosAdminView',
  data() {
    return {
      searchQuery: '',
      filterStatus: '',
      filterHorta: '',
      loading: false,
      canteiros: [],
      hortas: [],
    };
  },
  computed: {
    ...mapState('canteiros', ['summary']),
  },
  methods: {
    ...mapActions('canteiros', ['fetchSummaryAdmin', 'fetchCanteiros', 'deleteCanteiro']),
    async filtrar() {
      this.loading = true;
      try {
        const params = {
          search: this.searchQuery || undefined,
          status: this.filterStatus || undefined,
          horta_uuid: this.filterHorta || undefined,
        };
        await this.fetchCanteiros(params);
        this.canteiros = this.$store.state.canteiros.items;
        if (this.filterHorta) {
          await this.fetchSummaryAdmin(this.filterHorta);
        } else {
          await this.fetchSummaryAdmin();
        }
      } catch (error) {
        console.error('Erro ao filtrar:', error);
      } finally {
        this.loading = false;
      }
    },
    async deletarCanteiro(id) {
      if (confirm('Tem certeza que deseja deletar este canteiro?')) {
        try {
          await this.deleteCanteiro(id);
          await this.filtrar();
        } catch (error) {
          console.error('Erro ao deletar:', error);
        }
      }
    },
    statusColor(status) {
      const colors = {
        'Disponível': 'success',
        'Ocupado': 'warning',
        'Em Preparo': 'info',
      };
      return colors[status] || 'secondary';
    },
    async loadHortas() {
      try {
        const response = await this.$store.state.hortasService.getAll();
        this.hortas = response;
      } catch (error) {
        console.error('Erro ao carregar hortas:', error);
      }
    },
  },
  async mounted() {
    await this.loadHortas();
    await this.fetchSummaryAdmin();
    await this.filtrar();
  },
};
</script>
