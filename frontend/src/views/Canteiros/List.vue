<template>
  <div class="container mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h2>Meus Canteiros</h2>
        <p class="text-muted">Gerencie e acompanhe seus canteiros comunitários</p>
      </div>
      <router-link to="/canteiros/criar" class="btn btn-success align-self-start">+ Novo Canteiro</router-link>
    </div>

    <CanteirosSummary :total="canteiros.length" :area="totalArea" :ativos="activeCount" />

    <div class="card mb-4 p-3 mt-3">
      <div class="d-flex">
        <input v-model="q" class="form-control me-2" placeholder="Buscar canteiros..." />
        <button class="btn btn-outline-secondary">Filtros</button>
      </div>
    </div>

    <div>
      <CanteiroItem v-for="c in filtered" :key="c.id" :canteiro="c" @delete="confirmDelete" />
    </div>
  </div>
</template>

<script>
import { computed, onMounted, ref } from 'vue'
import { useStore } from 'vuex'
import CanteirosSummary from '@/components/CanteirosSummary.vue'
import CanteiroItem from '@/components/CanteiroItem.vue'

export default {
  name: 'CanteirosList',
  components: { CanteirosSummary, CanteiroItem },
  setup() {
    const store = useStore()
    const q = ref('')

    // true = usa dados fake para conseguir mexer na tela sem login/API
    // depois que o login funcionar, troque para false
    const usarMock = true

    const mockCanteiros = [
      {
        id: '1',
        uuid: '1',
        nome: 'Canteiro 001',
        numero_identificador: 'C-001',
        area: 10,
        tamanho_m2: 10,
        ativo: true
      },
      {
        id: '2',
        uuid: '2',
        nome: 'Canteiro 002',
        numero_identificador: 'C-002',
        area: 15,
        tamanho_m2: 15,
        ativo: true
      }
    ]

    const normalizarCanteiro = (c) => ({
      ...c,
      id: c.id || c.uuid,
      nome: c.nome || c.numero_identificador || `Canteiro ${c.id || c.uuid || ''}`,
      area: Number(c.area || c.tamanho_m2 || 0),
      ativo: c.ativo ?? true
    })

    const canteiros = computed(() => {
      if (usarMock) {
        return mockCanteiros
      }

      const data = store.getters['canteiros/allCanteiros']

      if (Array.isArray(data)) {
        return data.map(normalizarCanteiro)
      }

      if (Array.isArray(data?.data)) {
        return data.data.map(normalizarCanteiro)
      }

      if (Array.isArray(data?.canteiros)) {
        return data.canteiros.map(normalizarCanteiro)
      }

      return []
    })

    onMounted(async () => {
      if (!usarMock) {
        try {
          await store.dispatch('canteiros/fetchCanteiros')
        } catch (error) {
          console.error('Erro ao carregar canteiros:', error)
        }
      }
    })

    const totalArea = computed(() => {
      const sum = canteiros.value.reduce((s, c) => s + Number(c.area || 0), 0)
      return `${sum}m²`
    })

    const activeCount = computed(() => {
      return canteiros.value.filter(c => c.ativo).length
    })

    const filtered = computed(() => {
      if (!q.value) return canteiros.value

      return canteiros.value.filter(c =>
        c.nome.toLowerCase().includes(q.value.toLowerCase())
      )
    })

    const confirmDelete = async (item) => {
      if (usarMock) {
        alert('Delete desativado no modo teste sem API')
        return
      }

      if (confirm(`Excluir ${item.nome}?`)) {
        const res = await store.dispatch('canteiros/deleteCanteiro', item.id)
        if (!res.success) alert(res.message)
      }
    }

    return {
      q,
      canteiros,
      filtered,
      totalArea,
      activeCount,
      confirmDelete
    }
  }
}
</script>