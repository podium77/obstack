<template>
  <div class="query-builder-page">
    <!-- Header -->
    <div class="page-header">
      <div class="header-content">
        <h1>
          <span class="icon">📋</span>
          Concepteur de Requêtes
        </h1>
        <p>Construisez des requêtes SQL visuellement, comme dans phpMyAdmin</p>
      </div>
    </div>

    <!-- Main Content -->
    <div class="query-builder-container">
      <!-- Left Panel: Query Builder -->
      <div class="builder-panel">
        <!-- Database Selection -->
        <div class="builder-section">
          <div class="section-header">
            <h2>📊 Base de Données & Table</h2>
          </div>
          <div class="section-content">
            <div class="form-group">
              <label for="connection">Connexion :</label>
              <select v-model="selectedConnection" id="connection" @change="onConnectionChange">
                <option value="">-- Sélectionner une connexion --</option>
                <option v-for="conn in connections" :key="conn.id" :value="conn.id">
                  {{ conn.name }} ({{ conn.type }})
                </option>
              </select>
            </div>

            <div v-if="selectedConnection" class="form-group">
              <label for="table">Table :</label>
              <select v-model="selectedTable" id="table" @change="onTableChange">
                <option value="">-- Sélectionner une table --</option>
                <option v-for="table in tables" :key="table" :value="table">
                  {{ table }}
                </option>
              </select>
            </div>
          </div>
        </div>

        <!-- Column Selection -->
        <div v-if="selectedTable" class="builder-section">
          <div class="section-header">
            <h2>📌 Colonnes</h2>
            <button class="btn-small" @click="toggleAllColumns">
              {{ allColumnsSelected ? 'Désélectionner tout' : 'Sélectionner tout' }}
            </button>
          </div>
          <div class="section-content">
            <div v-if="columns.length === 0" class="empty-state">
              Aucune colonne trouvée
            </div>
            <div v-else class="column-list">
              <div v-for="column in columns" :key="column.name" class="column-item">
                <label class="checkbox-label">
                  <input 
                    type="checkbox" 
                    v-model="column.selected"
                    @change="updateColumnSelection"
                  />
                  <span class="column-name">{{ column.name }}</span>
                  <span class="column-type">{{ column.type }}</span>
                </label>
              </div>
            </div>
          </div>
        </div>

        <!-- WHERE Conditions -->
        <div v-if="selectedTable" class="builder-section">
          <div class="section-header">
            <h2>🔍 Conditions (WHERE)</h2>
            <button class="btn-small" @click="addCondition">+ Ajouter</button>
          </div>
          <div class="section-content">
            <div v-if="conditions.length === 0" class="empty-state">
              Aucune condition. <a href="#" @click="addCondition">Ajouter une condition</a>
            </div>
            <div v-else class="conditions-list">
              <div v-for="(cond, idx) in conditions" :key="idx" class="condition-row">
                <div class="condition-controls">
                  <select v-model="cond.column" class="select-sm" @change="updateQuery">
                    <option value="">-- Colonne --</option>
                    <option v-for="col in columns" :key="col.name" :value="col.name">
                      {{ col.name }}
                    </option>
                  </select>

                  <select v-model="cond.operator" class="select-sm" @change="updateQuery">
                    <option value="=">=</option>
                    <option value="!=">!=</option>
                    <option value=">">&gt;</option>
                    <option value="<">&lt;</option>
                    <option value=">=">&gt;=</option>
                    <option value="<=">&lt;=</option>
                    <option value="LIKE">LIKE</option>
                    <option value="IN">IN</option>
                    <option value="IS NULL">IS NULL</option>
                    <option value="IS NOT NULL">IS NOT NULL</option>
                  </select>

                  <input 
                    v-if="!['IS NULL', 'IS NOT NULL'].includes(cond.operator)"
                    v-model="cond.value" 
                    type="text" 
                    placeholder="Valeur"
                    class="input-sm"
                    @input="updateQuery"
                  />

                  <select v-if="idx < conditions.length - 1" v-model="cond.logic" class="select-sm" @change="updateQuery">
                    <option value="AND">AND</option>
                    <option value="OR">OR</option>
                  </select>

                  <button class="btn-danger-small" @click="removeCondition(idx)">✕</button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- ORDER BY -->
        <div v-if="selectedTable" class="builder-section">
          <div class="section-header">
            <h2>⬆️ Tri (ORDER BY)</h2>
            <button class="btn-small" @click="addOrderBy">+ Ajouter</button>
          </div>
          <div class="section-content">
            <div v-if="orderBy.length === 0" class="empty-state">
              Aucun tri appliqué
            </div>
            <div v-else class="order-list">
              <div v-for="(order, idx) in orderBy" :key="idx" class="order-row">
                <select v-model="order.column" class="select-sm" @change="updateQuery">
                  <option value="">-- Colonne --</option>
                  <option v-for="col in columns" :key="col.name" :value="col.name">
                    {{ col.name }}
                  </option>
                </select>

                <select v-model="order.direction" class="select-sm" @change="updateQuery">
                  <option value="ASC">ASC ⬆️</option>
                  <option value="DESC">DESC ⬇️</option>
                </select>

                <button class="btn-danger-small" @click="removeOrderBy(idx)">✕</button>
              </div>
            </div>
          </div>
        </div>

        <!-- LIMIT -->
        <div v-if="selectedTable" class="builder-section">
          <div class="section-header">
            <h2>📏 Limite</h2>
          </div>
          <div class="section-content">
            <div class="form-group-inline">
              <label for="limit">Nombre de lignes :</label>
              <input 
                v-model.number="limit" 
                type="number" 
                id="limit"
                min="1"
                max="10000"
                placeholder="10"
                @input="updateQuery"
              />
            </div>
            <div class="form-group-inline">
              <label for="offset">Décalage (OFFSET) :</label>
              <input 
                v-model.number="offset" 
                type="number" 
                id="offset"
                min="0"
                placeholder="0"
                @input="updateQuery"
              />
            </div>
          </div>
        </div>
      </div>

      <!-- Right Panel: SQL Query & Results -->
      <div class="results-panel">
        <!-- SQL Query Display -->
        <div class="sql-section">
          <div class="section-header">
            <h2>💻 Requête SQL Générée</h2>
            <button class="btn-small" @click="copySql" title="Copier">📋</button>
          </div>
          <div class="sql-display">
            <pre><code>{{ generatedQuery }}</code></pre>
          </div>
          <div class="sql-actions">
            <button class="btn-primary" @click="executeQuery" :disabled="!selectedTable">
              ▶️ Exécuter la Requête
            </button>
            <button class="btn-secondary" @click="resetBuilder">
              🔄 Réinitialiser
            </button>
          </div>
        </div>

        <!-- Query Results -->
        <div v-if="queryExecuted" class="results-section">
          <div class="section-header">
            <h2>📊 Résultats ({{ results.length }} lignes)</h2>
          </div>

          <div v-if="queryError" class="error-message">
            <strong>❌ Erreur :</strong> {{ queryError }}
          </div>

          <div v-else-if="results.length === 0" class="empty-state">
            Aucun résultat trouvé
          </div>

          <div v-else class="results-table-wrapper">
            <table class="results-table">
              <thead>
                <tr>
                  <th v-for="col in resultColumns" :key="col">{{ col }}</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, idx) in results.slice(0, 50)" :key="idx">
                  <td v-for="col in resultColumns" :key="col">
                    <span class="cell-value">{{ row[col] }}</span>
                  </td>
                </tr>
              </tbody>
            </table>

            <div v-if="results.length > 50" class="results-info">
              Affichage des 50 premières lignes sur {{ results.length }}
            </div>
          </div>
        </div>
      </div>

      <!-- Right Panel: Schema Diagram -->
      <div class="schema-panel" :class="{ fullscreen: schemaFullscreen }">
        <div class="schema-header">
          <h2>🗺️ Schéma de la Base de Données</h2>
          <button 
            v-if="selectedConnection" 
            class="fullscreen-btn" 
            @click="toggleFullscreen"
            :title="schemaFullscreen ? 'Quitter plein écran' : 'Plein écran'"
          >
            {{ schemaFullscreen ? '⛌' : '⛋' }}
          </button>
        </div>

        <div class="schema-content">
          <div v-if="!selectedConnection" class="schema-placeholder">
            <p>Sélectionnez une connexion pour voir le schéma</p>
          </div>

          <div v-else-if="schemaLoading" class="loading">
            ⏳ Chargement du schéma...
          </div>

          <div v-else-if="schemaError" class="error-message">
            <strong>❌ Erreur :</strong> {{ schemaError }}
          </div>

          <div v-else-if="Object.keys(schema).length === 0" class="empty-state">
            Aucune table trouvée
          </div>

          <div v-else class="diagram-container">
            <div class="diagram-toolbar">
              <button class="toolbar-btn" @click="resetDiagram" title="Réinitialiser">↻</button>
              <button class="toolbar-btn" @click="zoomOut" title="Zoomer arrière">−</button>
              <span class="zoom-level">{{ (zoomLevel * 100).toFixed(0) }}%</span>
              <button class="toolbar-btn" @click="zoomIn" title="Zoomer avant">+</button>
            </div>

            <div 
              class="diagram-canvas"
              @wheel="handleWheel"
              @mousedown="handleCanvasMouseDown"
              @mousemove="handleCanvasMouseMove"
              @mouseup="handleCanvasMouseUp"
              @mouseleave="handleCanvasMouseLeave"
              :style="{ cursor: isDragging ? 'grabbing' : 'grab' }"
            >
              <!-- SVG pour les liaisons -->
              <svg class="relations-svg">
                <defs>
                  <marker id="arrowhead" markerWidth="10" markerHeight="10" refX="9" refY="3" orient="auto">
                    <polygon points="0 0, 10 3, 0 6" fill="#f59e0b" />
                  </marker>
                </defs>
                <path 
                  v-for="(line, idx) in relationLines" 
                  :key="`line-${idx}`"
                  :d="`M ${line.x1} ${line.y1} C ${line.cx1} ${line.cy1} ${line.cx2} ${line.cy2} ${line.x2} ${line.y2}`"
                  stroke="#f59e0b"
                  stroke-width="2"
                  fill="none"
                  marker-end="url(#arrowhead)"
                  class="relation-line"
                />
              </svg>

              <!-- Tables -->
              <div 
                v-for="(tableInfo, tableName) in schema" 
                :key="tableName"
                class="table-card-diagram"
                :style="getTableStyle(tableName)"
                @mousedown="startDraggingTable($event, tableName)"
                :class="{ dragging: draggingTable === tableName }"
              >
                <div class="table-header-diagram">
                  <h3>{{ tableName }}</h3>
                  <span class="col-badge">{{ tableInfo.columns.length }}</span>
                </div>

                <div class="table-columns-diagram">
                  <div 
                    v-for="column in tableInfo.columns" 
                    :key="column.name" 
                    class="column-item"
                    :class="{ 'has-relation': highlightedColumns[tableName]?.has(column.name) }"
                    :title="highlightedColumns[tableName]?.has(column.name) ? '🔗 Colonne avec relation' : ''"
                  >
                    <span class="col-name">{{ column.name }}</span>
                    <span class="col-type">{{ column.type.substring(0, 10) }}</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Removed Schema Modal - Now integrated in schema-panel above -->
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useToast } from '@/composables/useToast'

interface Condition {
  column: string
  operator: string
  value: string
  logic: string
}

interface OrderBy {
  column: string
  direction: 'ASC' | 'DESC'
}

interface Connection {
  id: string
  name: string
  type: string
}

interface Column {
  name: string
  type: string
  selected: boolean
}

const { showToast } = useToast()

// State
const connections = ref<Connection[]>([])
const selectedConnection = ref<string>('')
const tables = ref<string[]>([])
const selectedTable = ref<string>('')
const columns = ref<Column[]>([])
const conditions = ref<Condition[]>([])
const orderBy = ref<OrderBy[]>([])
const limit = ref<number>(10)
const offset = ref<number>(0)

const results = ref<any[]>([])
const queryExecuted = ref(false)
const queryError = ref<string>('')

// Schema state
const schema = ref<any>({})
const schemaLoading = ref(false)
const schemaError = ref<string>('')

// Diagram state
const tablePositions = ref<Record<string, { x: number; y: number }>>({})
const zoomLevel = ref(1)
const draggingTable = ref<string>('')
const isDragging = ref(false)
const dragStart = ref({ x: 0, y: 0 })
const schemaFullscreen = ref(false)
const highlightedColumns = ref<Record<string, Set<string>>>({}) // tableName -> Set of columnNames

// Computed
const generatedQuery = computed(() => {
  if (!selectedTable.value) return 'SELECT ...'

  const selectedColumns = columns.value
    .filter(c => c.selected)
    .map(c => c.name)
  
  const columnsPart = selectedColumns.length > 0 
    ? selectedColumns.join(', ') 
    : '*'

  let query = `SELECT ${columnsPart}\nFROM ${selectedTable.value}`

  // WHERE clause
  if (conditions.value.length > 0) {
    const whereParts = conditions.value.map((cond, idx) => {
      const logic = idx > 0 ? ` ${cond.logic} ` : ''
      if (['IS NULL', 'IS NOT NULL'].includes(cond.operator)) {
        return `${logic}${cond.column} ${cond.operator}`
      }
      return `${logic}${cond.column} ${cond.operator} '${cond.value}'`
    })
    query += `\nWHERE ${whereParts.join('')}`
  }

  // ORDER BY clause
  if (orderBy.value.length > 0) {
    const orderParts = orderBy.value
      .filter(o => o.column)
      .map(o => `${o.column} ${o.direction}`)
    if (orderParts.length > 0) {
      query += `\nORDER BY ${orderParts.join(', ')}`
    }
  }

  // LIMIT clause
  if (limit.value) {
    query += `\nLIMIT ${limit.value}`
    if (offset.value > 0) {
      query += ` OFFSET ${offset.value}`
    }
  }

  return query
})

const allColumnsSelected = computed(() => {
  return columns.value.length > 0 && columns.value.every(c => c.selected)
})

const resultColumns = computed(() => {
  return results.value.length > 0 ? Object.keys(results.value[0]) : []
})

// Methods
const loadConnections = async () => {
  try {
    const response = await fetch('/api/database/connections', {
      credentials: 'include'
    })
    const data = await response.json()
    connections.value = data
  } catch (error) {
    console.error('Erreur lors du chargement des connexions:', error)
    showToast('Erreur lors du chargement des connexions', 'error')
  }
}

const onConnectionChange = async () => {
  tables.value = []
  selectedTable.value = ''
  columns.value = []

  if (!selectedConnection.value) return

  try {
    const response = await fetch(`/api/database/${selectedConnection.value}/tables`, {
      credentials: 'include'
    })
    const data = await response.json()
    tables.value = data.tables || []
    
    // Charger aussi le schéma pour le diagramme
    await loadSchema()
  } catch (error) {
    console.error('Erreur lors du chargement des tables:', error)
    showToast('Erreur lors du chargement des tables', 'error')
  }
}

const onTableChange = async () => {
  columns.value = []

  if (!selectedConnection.value || !selectedTable.value) return

  try {
    const response = await fetch(
      `/api/database/${selectedConnection.value}/table/${selectedTable.value}/structure`,
      {
        credentials: 'include'
      }
    )
    const data = await response.json()
    columns.value = data.columns.map((col: any) => ({
      name: col.name,
      type: col.type,
      selected: true
    })) || []
  } catch (error) {
    console.error('Erreur lors du chargement des colonnes:', error)
    showToast('Erreur lors du chargement des colonnes', 'error')
  }
}

const updateColumnSelection = () => {
  updateQuery()
}

const toggleAllColumns = () => {
  const selectAll = !allColumnsSelected.value
  columns.value.forEach(col => {
    col.selected = selectAll
  })
  updateQuery()
}

const addCondition = () => {
  conditions.value.push({
    column: '',
    operator: '=',
    value: '',
    logic: 'AND'
  })
}

const removeCondition = (idx: number) => {
  conditions.value.splice(idx, 1)
  updateQuery()
}

const addOrderBy = () => {
  orderBy.value.push({
    column: '',
    direction: 'ASC'
  })
}

const removeOrderBy = (idx: number) => {
  orderBy.value.splice(idx, 1)
  updateQuery()
}

const updateQuery = () => {
  // La requête est mise à jour via le computed property
}

const copySql = () => {
  navigator.clipboard.writeText(generatedQuery.value)
  showToast('Requête copiée au presse-papiers', 'success')
}

const executeQuery = async () => {
  if (!selectedConnection.value) {
    showToast('Veuillez sélectionner une connexion', 'warning')
    return
  }

  try {
    queryError.value = ''
    results.value = []

    const response = await fetch('/api/database/query/execute', {
      method: 'POST',
      credentials: 'include',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        connectionId: selectedConnection.value,
        query: generatedQuery.value
      })
    })

    if (!response.ok) {
      const errorData = await response.json()
      queryError.value = errorData.error || 'Erreur lors de l\'exécution'
      showToast('Erreur lors de l\'exécution de la requête', 'error')
    } else {
      const data = await response.json()
      results.value = data.results || []
      queryExecuted.value = true
      showToast(`${results.value.length} ligne(s) trouvée(s)`, 'success')
    }
  } catch (error) {
    console.error('Erreur lors de l\'exécution:', error)
    queryError.value = 'Erreur de connexion'
    showToast('Erreur lors de l\'exécution de la requête', 'error')
  }
}

const resetBuilder = () => {
  selectedConnection.value = ''
  selectedTable.value = ''
  columns.value = []
  conditions.value = []
  orderBy.value = []
  limit.value = 10
  offset.value = 0
  results.value = []
  queryExecuted.value = false
  queryError.value = ''
}

const loadSchema = async () => {
  if (!selectedConnection.value) return

  schemaLoading.value = true
  schemaError.value = ''
  schema.value = {}

  try {
    const response = await fetch(`/api/database/${selectedConnection.value}/schema`, {
      credentials: 'include'
    })
    const data = await response.json()
    schema.value = data.tables || {}
    calculateTablePositions()
  } catch (error) {
    console.error('Erreur lors du chargement du schéma:', error)
    schemaError.value = 'Impossible de charger le schéma'
  } finally {
    schemaLoading.value = false
  }
}

// Diagram functions
const calculateTablePositions = () => {
  const positions: Record<string, { x: number; y: number }> = {}
  const totalTables = Object.keys(schema.value).length
  const cols = Math.ceil(Math.sqrt(totalTables))
  let idx = 0

  // Calculer la largeur totale du diagramme
  const diagramWidth = cols * 320
  // Utiliser un offset pour centrer/aligner à droite (on choisit de laisser une marge à gauche)
  const offsetX = 40

  for (const tableName of Object.keys(schema.value)) {
    const row = Math.floor(idx / cols)
    const col = idx % cols
    positions[tableName] = {
      x: offsetX + col * 320,
      y: 20 + row * 400
    }
    idx++
  }

  tablePositions.value = positions
  calculateHighlightedColumns()
}

const calculateHighlightedColumns = () => {
  const highlighted: Record<string, Set<string>> = {}
  
  for (const [tableName, tableInfo] of Object.entries(schema.value)) {
    highlighted[tableName] = new Set<string>()
    
    // Marquer les colonnes qui ont des relations sortantes
    for (const rel of (tableInfo as any).relations || []) {
      highlighted[tableName].add(rel.column_name)
    }
  }
  
  // Aussi chercher les colonnes qui sont référencées par d'autres tables
  for (const [tableName, tableInfo] of Object.entries(schema.value)) {
    for (const [otherTableName, otherTableInfo] of Object.entries(schema.value)) {
      if (tableName === otherTableName) continue
      
      for (const rel of (otherTableInfo as any).relations || []) {
        if (rel.referenced_table_name === tableName) {
          if (!highlighted[tableName]) highlighted[tableName] = new Set<string>()
          highlighted[tableName].add(rel.referenced_column_name)
        }
      }
    }
  }
  
  highlightedColumns.value = highlighted
}

const toggleFullscreen = () => {
  schemaFullscreen.value = !schemaFullscreen.value
}

const getTableStyle = (tableName: string) => {
  const pos = tablePositions.value[tableName] || { x: 0, y: 0 }
  return {
    transform: `translate(${pos.x}px, ${pos.y}px) scale(${zoomLevel.value})`,
    transformOrigin: '0 0',
    left: '0',
    top: '0'
  }
}

const relationLines = computed(() => {
  const lines: any[] = []
  const tableCardWidth = 240  // Correspond au CSS (240px)
  const tableCardHeight = (col: any[]) => Math.min(col.length * 24 + 50, 280)  // Correspond au max-height CSS

  for (const [tableName, tableInfo] of Object.entries(schema.value)) {
    const sourcePos = tablePositions.value[tableName]
    if (!sourcePos) continue

    for (const rel of (tableInfo as any).relations || []) {
      const targetPos = tablePositions.value[rel.referenced_table_name]
      if (!targetPos) continue

      // Points de connexion ajustés pour le zoom
      // Les tables utilisent: transform: translate(pos.x, pos.y) scale(zoom)
      // Donc les coordonnées réelles à l'écran sont: pos + (taille_native * zoom)
      const sourceHeight = tableCardHeight((tableInfo as any).columns)
      
      const x1 = sourcePos.x + (tableCardWidth / 2) * zoomLevel.value
      const y1 = sourcePos.y + sourceHeight * zoomLevel.value
      const x2 = targetPos.x + (tableCardWidth / 2) * zoomLevel.value
      const y2 = targetPos.y + (25 * zoomLevel.value)

      // Points de contrôle pour la courbe de Bézier
      // Crée une courbe lisse entre les deux tables
      const midX = (x1 + x2) / 2
      const offsetY = Math.abs(y2 - y1) * 0.3  // Offre une courbe douce
      
      const cx1 = x1
      const cy1 = y1 + offsetY
      const cx2 = x2
      const cy2 = y2 - offsetY

      lines.push({ x1, y1, x2, y2, cx1, cy1, cx2, cy2 })
    }
  }

  return lines
})

// Drag and drop functions
const startDraggingTable = (event: MouseEvent, tableName: string) => {
  isDragging.value = true
  draggingTable.value = tableName
  dragStart.value = {
    x: event.clientX,
    y: event.clientY
  }
  event.preventDefault()
}

const handleCanvasMouseMove = (event: MouseEvent) => {
  if (!isDragging.value || !draggingTable.value) return

  const deltaX = (event.clientX - dragStart.value.x) / zoomLevel.value
  const deltaY = (event.clientY - dragStart.value.y) / zoomLevel.value

  const pos = tablePositions.value[draggingTable.value]
  if (pos) {
    pos.x += deltaX
    pos.y += deltaY
  }

  dragStart.value = {
    x: event.clientX,
    y: event.clientY
  }
}

const handleCanvasMouseUp = () => {
  isDragging.value = false
  draggingTable.value = ''
}

const handleCanvasMouseLeave = () => {
  isDragging.value = false
  draggingTable.value = ''
}

// Zoom functions
const handleWheel = (event: WheelEvent) => {
  event.preventDefault()
  const delta = event.deltaY > 0 ? -0.1 : 0.1
  zoomLevel.value = Math.max(0.3, Math.min(2, zoomLevel.value + delta))
}

const zoomIn = () => {
  zoomLevel.value = Math.min(2, zoomLevel.value + 0.2)
}

const zoomOut = () => {
  zoomLevel.value = Math.max(0.3, zoomLevel.value - 0.2)
}

const resetDiagram = () => {
  zoomLevel.value = 1
  calculateTablePositions()
}

// Watch for modal opening

// Lifecycle
onMounted(() => {
  loadConnections()
})

import { onMounted } from 'vue'
</script>

<style scoped lang="css">
.query-builder-page {
  display: flex;
  flex-direction: column;
  height: 100vh;
  background: var(--color-bg);
}

.page-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 3rem 2rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.header-content h1 {
  margin: 0;
  font-size: 2rem;
  display: flex;
  align-items: center;
  gap: 1rem;
}

.header-content p {
  margin: 0.5rem 0 0 0;
  opacity: 0.9;
}

.query-builder-container {
  display: grid;
  grid-template-columns: 1fr 1.5fr;
  grid-template-rows: auto 1fr;
  gap: 1.5rem;
  padding: 2rem;
  flex: 1;
  overflow: hidden;
}

.builder-panel {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  overflow-y: auto;
  background: var(--color-bg-secondary);
  border-radius: 8px;
  padding: 1.5rem;
  min-height: 0;
  grid-column: 1;
  grid-row: 1;
}

.results-panel {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  overflow-y: auto;
  background: var(--color-bg-secondary);
  border-radius: 8px;
  padding: 1.5rem;
  min-height: 0;
  grid-column: 1;
  grid-row: 2;
}

.schema-panel {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  overflow-y: auto;
  background: var(--color-bg-secondary);
  border-radius: 8px;
  padding: 1.5rem;
  min-height: 0;
  grid-column: 2;
  grid-row: 1 / 3;
}

.builder-section {
  background: var(--color-bg);
  border-radius: 6px;
  border: 1px solid var(--color-border);
  overflow: hidden;
}

.section-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: var(--color-bg-tertiary);
  padding: 1rem;
  border-bottom: 1px solid var(--color-border);
}

.section-header h2 {
  margin: 0;
  font-size: 1rem;
  font-weight: 600;
}

.section-content {
  padding: 1rem;
}

.form-group {
  margin-bottom: 1rem;
}

.form-group:last-child {
  margin-bottom: 0;
}

.form-group label,
.form-group-inline label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 500;
  font-size: 0.9rem;
}

.form-group-inline {
  display: grid;
  grid-template-columns: auto 1fr;
  align-items: center;
  gap: 1rem;
  margin-bottom: 0.5rem;
}

.form-group select,
.form-group input,
.select-sm,
.input-sm {
  width: 100%;
  padding: 0.5rem;
  border: 1px solid var(--color-border);
  border-radius: 4px;
  background: var(--color-bg);
  color: var(--color-text);
  font-size: 0.9rem;
}

.select-sm,
.input-sm {
  width: auto;
  min-width: 120px;
}

.column-list {
  display: grid;
  gap: 0.5rem;
}

.column-item {
  padding: 0.5rem;
}

.checkbox-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  cursor: pointer;
  font-size: 0.9rem;
}

.checkbox-label input[type="checkbox"] {
  width: auto;
  cursor: pointer;
}

.column-name {
  font-weight: 500;
}

.column-type {
  color: var(--color-text-secondary);
  font-size: 0.85rem;
  margin-left: auto;
}

.condition-row,
.order-row {
  display: grid;
  grid-template-columns: 1fr auto auto auto auto;
  gap: 0.5rem;
  margin-bottom: 0.5rem;
  align-items: center;
}

.condition-controls {
  display: contents;
}

.conditions-list,
.order-list {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.empty-state {
  text-align: center;
  color: var(--color-text-secondary);
  padding: 1.5rem;
  font-size: 0.9rem;
}

.empty-state a {
  color: var(--color-primary);
  text-decoration: none;
}

.empty-state a:hover {
  text-decoration: underline;
}

.btn-small,
.btn-primary,
.btn-secondary,
.btn-danger-small {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 500;
  transition: all 0.2s ease;
}

.btn-small {
  background: var(--color-bg-tertiary);
  color: var(--color-text);
  border: 1px solid var(--color-border);
}

.btn-small:hover {
  background: var(--color-border);
}

.btn-primary {
  background: #667eea;
  color: white;
  padding: 0.75rem 1.5rem;
}

.btn-primary:hover:not(:disabled) {
  background: #5568d3;
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.btn-primary:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.btn-secondary {
  background: var(--color-bg-tertiary);
  color: var(--color-text);
  border: 1px solid var(--color-border);
}

.btn-secondary:hover {
  background: var(--color-border);
}

.btn-danger-small {
  background: #f56565;
  color: white;
  padding: 0.4rem 0.8rem;
  font-size: 0.85rem;
}

.btn-danger-small:hover {
  background: #e53e3e;
}

.sql-section {
  background: var(--color-bg);
  border-radius: 6px;
  border: 1px solid var(--color-border);
  overflow: hidden;
}

.sql-display {
  background: #1e1e1e;
  color: #d4d4d4;
  padding: 1rem;
  font-family: 'Courier New', monospace;
  overflow-x: auto;
  border-bottom: 1px solid var(--color-border);
}

.sql-display pre {
  margin: 0;
  font-size: 0.9rem;
  line-height: 1.5;
}

.sql-display code {
  color: #9cdcfe;
}

.sql-actions {
  display: flex;
  gap: 0.5rem;
  padding: 1rem;
  border-top: 1px solid var(--color-border);
}

.sql-actions button {
  flex: 1;
}

.results-section {
  background: var(--color-bg);
  border-radius: 6px;
  border: 1px solid var(--color-border);
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.results-table-wrapper {
  overflow: auto;
  flex: 1;
}

.results-table {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.9rem;
}

.results-table thead {
  position: sticky;
  top: 0;
  background: var(--color-bg-tertiary);
  z-index: 1;
}

.results-table th {
  padding: 0.75rem;
  text-align: left;
  font-weight: 600;
  border-bottom: 1px solid var(--color-border);
  color: var(--color-text);
}

.results-table td {
  padding: 0.75rem;
  border-bottom: 1px solid var(--color-border);
}

.results-table tbody tr:hover {
  background: var(--color-bg-tertiary);
}

.cell-value {
  word-break: break-word;
  max-width: 300px;
  display: block;
}

.error-message {
  background: #fed7d7;
  color: #742a2a;
  padding: 1rem;
  border-radius: 4px;
  margin-bottom: 1rem;
  border-left: 4px solid #f56565;
}

.results-info {
  padding: 1rem;
  text-align: center;
  background: var(--color-bg-tertiary);
  font-size: 0.9rem;
  color: var(--color-text-secondary);
  border-top: 1px solid var(--color-border);
}

/* Schema Panel Styles */
.schema-panel {
  border: 1px solid var(--color-border);
  min-height: 500px;
  transition: all 0.3s ease;
}

.schema-panel.fullscreen {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 999;
  width: 100%;
  height: 100vh;
  min-height: 100vh;
  border: none;
  margin: 0;
  padding: 0;
  border-radius: 0;
}

.schema-panel.fullscreen .schema-content {
  height: 100vh;
}

.schema-header {
  padding: 1rem;
  border-bottom: 1px solid var(--color-border);
  background: var(--color-bg);
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.schema-header h2 {
  margin: 0;
  font-size: 1rem;
  color: #667eea;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.fullscreen-btn {
  background: #667eea;
  color: white;
  border: none;
  padding: 0.5rem 0.75rem;
  border-radius: 4px;
  cursor: pointer;
  font-size: 1rem;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 40px;
  min-height: 40px;
}

.fullscreen-btn:hover {
  background: #5568d3;
  transform: scale(1.05);
}

.schema-content {
  flex: 1;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.schema-placeholder {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 100%;
  color: var(--color-text-secondary);
  font-size: 0.95rem;
  text-align: center;
  padding: 1rem;
}

/* Diagram styles */
.diagram-container {
  display: flex;
  flex-direction: column;
  height: 100%;
  flex: 1;
}

.diagram-toolbar {
  padding: 0.5rem;
  border-bottom: 1px solid var(--color-border);
  display: flex;
  align-items: center;
  gap: 0.3rem;
  background: var(--color-bg-secondary);
  flex-wrap: wrap;
}

.toolbar-btn {
  padding: 0.4rem 0.6rem;
  background: #667eea;
  color: white;
  border: none;
  border-radius: 3px;
  cursor: pointer;
  font-size: 0.8rem;
  transition: all 0.2s ease;
  min-width: 30px;
}

.toolbar-btn:hover {
  background: #5568d3;
  transform: scale(1.05);
}

.zoom-level {
  min-width: 50px;
  text-align: center;
  font-weight: 500;
  color: var(--color-text-secondary);
  font-size: 0.85rem;
}

.toolbar-hint {
  margin-left: auto;
  font-size: 0.8rem;
  color: var(--color-text-secondary);
  display: none;
}

.diagram-canvas {
  flex: 1;
  overflow: auto;
  background: var(--color-bg);
  position: relative;
  user-select: none;
}

.relations-svg {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  pointer-events: none;
  z-index: 5;
}

.relation-line {
  opacity: 0.7;
  transition: opacity 0.2s ease;
}

.relation-line:hover {
  opacity: 1;
  stroke-width: 3;
}

.table-card-diagram {
  position: absolute;
  width: 240px;
  background: var(--color-bg-secondary);
  border: 2px solid #667eea;
  border-radius: 6px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
  cursor: grab;
  z-index: 2;
  transition: all 0.2s ease;
  max-height: 280px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.table-card-diagram:hover {
  border-color: #764ba2;
  box-shadow: 0 4px 16px rgba(102, 126, 234, 0.3);
}

.table-card-diagram.dragging {
  cursor: grabbing;
  opacity: 0.9;
  box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4);
}

.table-header-diagram {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  padding: 0.6rem;
  border-radius: 4px 4px 0 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-shrink: 0;
}

.table-header-diagram h3 {
  margin: 0;
  font-size: 0.85rem;
  font-weight: 600;
  word-break: break-word;
}

.col-badge {
  background: rgba(255, 255, 255, 0.3);
  padding: 0.2rem 0.4rem;
  border-radius: 10px;
  font-size: 0.7rem;
  font-weight: 500;
  min-width: 25px;
  text-align: center;
}

.table-columns-diagram {
  flex: 1;
  overflow-y: auto;
  padding: 0.4rem;
  font-size: 0.75rem;
}

.column-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 0.3rem;
  margin-bottom: 0.15rem;
  background: var(--color-bg);
  border-radius: 2px;
  border-left: 2px solid #667eea;
  transition: all 0.2s ease;
}

.column-item.has-relation {
  background: rgba(102, 126, 234, 0.15);
  border-left: 3px solid #f59e0b;
  box-shadow: inset 0 0 8px rgba(245, 158, 11, 0.2);
  font-weight: 600;
}

.column-item.has-relation::before {
  content: '🔗';
  margin-right: 0.3rem;
  font-size: 0.65rem;
}

.col-name {
  color: var(--color-text);
  font-weight: 500;
  word-break: break-word;
  flex: 1;
  font-size: 0.7rem;
}

.column-item.has-relation .col-name {
  color: #f59e0b;
  font-weight: 600;
}

.col-type {
  color: var(--color-text-secondary);
  font-family: monospace;
  font-size: 0.65rem;
  margin-left: 0.3rem;
  flex-shrink: 0;
}

.column-item.has-relation .col-type {
  color: #f59e0b;
}

.empty-state {
  padding: 2rem;
  text-align: center;
  color: var(--color-text-secondary);
}

@media (max-width: 1600px) {
  .query-builder-container {
    grid-template-columns: 1fr 1.2fr;
    grid-template-rows: auto 1fr;
  }
  
  .builder-panel {
    grid-column: 1;
    grid-row: 1;
  }
  
  .results-panel {
    grid-column: 1;
    grid-row: 2;
  }
  
  .schema-panel {
    grid-column: 2;
    grid-row: 1 / 3;
  }
}

@media (max-width: 1000px) {
  .query-builder-container {
    grid-template-columns: 1fr;
    grid-template-rows: auto auto auto;
    gap: 1rem;
  }
  
  .builder-panel {
    grid-column: 1;
    grid-row: 1;
  }
  
  .results-panel {
    grid-column: 1;
    grid-row: 2;
  }
  
  .schema-panel {
    grid-column: 1;
    grid-row: 3;
    min-height: 300px;
  }
}

@media (max-width: 768px) {
  .page-header {
    padding: 2rem 1rem;
  }

  .page-header h1 {
    font-size: 1.5rem;
  }

  .query-builder-container {
    padding: 1rem;
    gap: 0.75rem;
    grid-template-columns: 1fr;
    grid-template-rows: auto auto auto;
  }

  .builder-panel {
    grid-column: 1;
    grid-row: 1;
  }
  
  .results-panel {
    grid-column: 1;
    grid-row: 2;
  }
  
  .schema-panel {
    grid-column: 1;
    grid-row: 3;
    min-height: 250px;
  }

  .condition-row,
  .order-row {
    grid-template-columns: 1fr;
  }
  
  .table-card-diagram {
    width: 200px;
    max-height: 250px;
  }
  
  .diagram-toolbar {
    padding: 0.4rem;
  }
  
  .toolbar-btn {
    padding: 0.3rem 0.5rem;
    font-size: 0.7rem;
    min-width: 25px;
  }
}
</style>
