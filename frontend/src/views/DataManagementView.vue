<template>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900">📊 Data Management</h1>
        <p class="mt-2 text-gray-600">Import, export, and bulk manage your database</p>
      </div>

      <!-- Tabs -->
      <div class="flex gap-4 mb-8 border-b border-gray-200">
        <button
          @click="activeTab = 'import'"
          :class="[
            'px-4 py-3 font-medium border-b-2 transition',
            activeTab === 'import'
              ? 'border-indigo-600 text-indigo-600'
              : 'border-transparent text-gray-600 hover:text-gray-900'
          ]"
        >
          📥 Import
        </button>
        <button
          @click="activeTab = 'export'"
          :class="[
            'px-4 py-3 font-medium border-b-2 transition',
            activeTab === 'export'
              ? 'border-indigo-600 text-indigo-600'
              : 'border-transparent text-gray-600 hover:text-gray-900'
          ]"
        >
          📤 Export
        </button>
        <button
          @click="activeTab = 'bulk'"
          :class="[
            'px-4 py-3 font-medium border-b-2 transition',
            activeTab === 'bulk'
              ? 'border-indigo-600 text-indigo-600'
              : 'border-transparent text-gray-600 hover:text-gray-900'
          ]"
        >
          ⚙️ Bulk Operations
        </button>
        <button
          @click="activeTab = 'quality'"
          :class="[
            'px-4 py-3 font-medium border-b-2 transition',
            activeTab === 'quality'
              ? 'border-indigo-600 text-indigo-600'
              : 'border-transparent text-gray-600 hover:text-gray-900'
          ]"
        >
          ✓ Data Quality
        </button>
      </div>

      <!-- Import Tab -->
      <div v-if="activeTab === 'import'" class="space-y-6">
        <div class="bg-white rounded-lg shadow">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Import Data</h2>
          </div>
          <div class="p-6 space-y-6">
            <!-- Table Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Target Table
              </label>
              <input
                v-model="import_.tableName"
                type="text"
                placeholder="Enter table name"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              />
            </div>

            <!-- Format Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Import Format
              </label>
              <div class="flex gap-4">
                <label class="flex items-center">
                  <input
                    v-model="import_.format"
                    type="radio"
                    value="csv"
                    class="mr-2"
                  />
                  <span class="text-gray-700">CSV</span>
                </label>
                <label class="flex items-center">
                  <input
                    v-model="import_.format"
                    type="radio"
                    value="json"
                    class="mr-2"
                  />
                  <span class="text-gray-700">JSON</span>
                </label>
              </div>
            </div>

            <!-- File Upload -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                File Content
              </label>
              <textarea
                v-model="import_.content"
                placeholder="Paste your CSV or JSON content here..."
                rows="10"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent font-mono text-sm"
              />
            </div>

            <!-- CSV Options -->
            <div v-if="import_.format === 'csv'" class="space-y-4">
              <label class="flex items-center">
                <input
                  v-model="import_.options.hasHeader"
                  type="checkbox"
                  class="mr-2"
                />
                <span class="text-gray-700">First row is header</span>
              </label>
              <label class="flex items-center">
                <input
                  v-model="import_.options.skipEmptyRows"
                  type="checkbox"
                  class="mr-2"
                />
                <span class="text-gray-700">Skip empty rows</span>
              </label>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  On Duplicate
                </label>
                <select
                  v-model="import_.options.onDuplicate"
                  class="px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                >
                  <option value="skip">Skip</option>
                  <option value="update">Update</option>
                  <option value="error">Error</option>
                </select>
              </div>
            </div>

            <!-- Validate Button -->
            <div class="flex gap-3">
              <button
                @click="validateImport"
                :disabled="!import_.tableName || !import_.content || isLoading"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
              >
                🔍 Validate
              </button>
              <button
                @click="doImport"
                :disabled="!import_.tableName || !import_.content || isLoading"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
              >
                {{ isLoading ? 'Importing...' : '📥 Import' }}
              </button>
            </div>

            <!-- Validation Results -->
            <div v-if="validationResult" class="p-4 rounded-lg" :class="validationResult.valid ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
              <div class="font-semibold mb-2" :class="validationResult.valid ? 'text-green-800' : 'text-red-800'">
                {{ validationResult.valid ? '✓ Validation Passed' : '✗ Validation Failed' }}
              </div>
              <div v-if="validationResult.errors.length" class="text-red-700 text-sm space-y-1">
                <div v-for="(error, i) in validationResult.errors" :key="i">• {{ error }}</div>
              </div>
              <div v-if="validationResult.warnings.length" class="text-yellow-700 text-sm space-y-1 mt-2">
                <div v-for="(warning, i) in validationResult.warnings" :key="i">⚠ {{ warning }}</div>
              </div>
            </div>

            <!-- Import Results -->
            <div v-if="importResult" class="p-4 rounded-lg" :class="importResult.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
              <div class="font-semibold mb-2" :class="importResult.success ? 'text-green-800' : 'text-red-800'">
                {{ importResult.success ? '✓ Import Complete' : '✗ Import Failed' }}
              </div>
              <div class="text-sm space-y-1">
                <div>Imported: <strong>{{ importResult.rowsImported }}</strong></div>
                <div>Skipped: <strong>{{ importResult.rowsSkipped }}</strong></div>
                <div>Total: <strong>{{ importResult.totalRows }}</strong></div>
              </div>
              <div v-if="importResult.errors.length" class="text-red-700 text-sm space-y-1 mt-2">
                <div v-for="(error, i) in importResult.errors" :key="i">• {{ error }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Export Tab -->
      <div v-if="activeTab === 'export'" class="space-y-6">
        <div class="bg-white rounded-lg shadow">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Export Data</h2>
          </div>
          <div class="p-6 space-y-6">
            <!-- Table Selection -->
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Source Table
              </label>
              <input
                v-model="export_.tableName"
                type="text"
                placeholder="Enter table name"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
              />
            </div>

            <!-- Export Options -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Limit
                </label>
                <input
                  v-model.number="export_.limit"
                  type="number"
                  min="1"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                  Offset
                </label>
                <input
                  v-model.number="export_.offset"
                  type="number"
                  min="0"
                  class="w-full px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />
              </div>
            </div>

            <!-- Export Buttons -->
            <div class="flex flex-wrap gap-3">
              <button
                @click="getTableStats"
                :disabled="!export_.tableName || isLoading"
                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
              >
                📊 Stats
              </button>
              <button
                @click="getTableStructure"
                :disabled="!export_.tableName || isLoading"
                class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
              >
                🏗️ Structure
              </button>
              <button
                @click="doExport('csv')"
                :disabled="!export_.tableName || isLoading"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
              >
                📥 CSV
              </button>
              <button
                @click="doExport('json')"
                :disabled="!export_.tableName || isLoading"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
              >
                📥 JSON
              </button>
              <button
                @click="doExport('jsonl')"
                :disabled="!export_.tableName || isLoading"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
              >
                📥 JSONL
              </button>
              <button
                @click="doExport('excel')"
                :disabled="!export_.tableName || isLoading"
                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed transition"
              >
                📥 Excel
              </button>
            </div>

            <!-- Export Results -->
            <div v-if="tableStats" class="p-4 rounded-lg bg-blue-50 border border-blue-200">
              <div class="font-semibold text-blue-800 mb-2">📊 Table Statistics</div>
              <div class="text-sm text-blue-700 space-y-1">
                <div>Rows: <strong>{{ tableStats.rowCount }}</strong></div>
                <div>Size: <strong>{{ formatBytes(tableStats.estimatedSize) }}</strong></div>
              </div>
            </div>

            <div v-if="tableStructure" class="p-4 rounded-lg bg-blue-50 border border-blue-200">
              <div class="font-semibold text-blue-800 mb-3">🏗️ Table Structure</div>
              <div class="text-sm">
                <div class="mb-3">
                  <strong class="text-blue-700">Columns ({{ tableStructure.columns.length }}):</strong>
                  <div class="mt-1 space-y-1">
                    <div v-for="col in tableStructure.columns" :key="col.name" class="text-blue-700">
                      • <code class="bg-white px-1 rounded">{{ col.name }}</code> - {{ col.type }}{{ col.nullable ? ' (nullable)' : '' }}
                    </div>
                  </div>
                </div>
                <div v-if="tableStructure.indexes.length">
                  <strong class="text-blue-700">Indexes ({{ tableStructure.indexes.length }}):</strong>
                  <div class="mt-1 space-y-1">
                    <div v-for="idx in tableStructure.indexes" :key="idx.name" class="text-blue-700">
                      • <code class="bg-white px-1 rounded">{{ idx.name }}</code> {{ idx.primary ? '(PRIMARY)' : idx.unique ? '(UNIQUE)' : '' }}
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Bulk Operations Tab -->
      <div v-if="activeTab === 'bulk'" class="space-y-6">
        <div class="bg-white rounded-lg shadow">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Bulk Operations</h2>
          </div>
          <div class="p-6 space-y-6">
            <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
              <div class="text-yellow-800 text-sm">
                ⚠️ Bulk operations affect multiple rows. Please review conditions carefully before executing.
              </div>
            </div>

            <!-- Bulk Update -->
            <div class="border-b pb-6">
              <h3 class="text-md font-semibold text-gray-900 mb-4">Update Multiple Rows</h3>
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Table</label>
                  <input
                    v-model="bulk.tableName"
                    type="text"
                    placeholder="Table name"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Conditions (JSON)</label>
                  <textarea
                    v-model="bulk.conditions"
                    placeholder='{"column": "value"}'
                    rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg font-mono text-sm"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Update Data (JSON)</label>
                  <textarea
                    v-model="bulk.updateData"
                    placeholder='{"column": "new_value"}'
                    rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg font-mono text-sm"
                  />
                </div>
                <button
                  @click="doBulkUpdate"
                  :disabled="!bulk.tableName || !bulk.conditions || !bulk.updateData || isLoading"
                  class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 disabled:opacity-50 transition"
                >
                  🔄 Update
                </button>
              </div>
            </div>

            <!-- Bulk Delete -->
            <div>
              <h3 class="text-md font-semibold text-gray-900 mb-4">Delete Multiple Rows</h3>
              <div class="space-y-4">
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Table</label>
                  <input
                    v-model="bulkDelete.tableName"
                    type="text"
                    placeholder="Table name"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg"
                  />
                </div>
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Conditions (JSON)</label>
                  <textarea
                    v-model="bulkDelete.conditions"
                    placeholder='{"column": "value"}'
                    rows="3"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg font-mono text-sm"
                  />
                </div>
                <label class="flex items-center">
                  <input
                    v-model="bulkDelete.confirm"
                    type="checkbox"
                    class="mr-2"
                  />
                  <span class="text-red-700 font-semibold">I understand this will delete rows permanently</span>
                </label>
                <button
                  @click="doBulkDelete"
                  :disabled="!bulkDelete.tableName || !bulkDelete.conditions || !bulkDelete.confirm || isLoading"
                  class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 disabled:opacity-50 transition"
                >
                  🗑️ Delete
                </button>
              </div>
            </div>

            <!-- Operation Results -->
            <div v-if="operationResult" class="p-4 rounded-lg" :class="operationResult.success ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200'">
              <div class="font-semibold mb-2" :class="operationResult.success ? 'text-green-800' : 'text-red-800'">
                {{ operationResult.success ? '✓ Operation Complete' : '✗ Operation Failed' }}
              </div>
              <div class="text-sm" :class="operationResult.success ? 'text-green-700' : 'text-red-700'">
                {{ operationResult.message }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Data Quality Tab -->
      <div v-if="activeTab === 'quality'" class="space-y-6">
        <div class="bg-white rounded-lg shadow">
          <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Data Quality Analysis</h2>
          </div>
          <div class="p-6 space-y-6">
            <div>
              <label class="block text-sm font-medium text-gray-700 mb-2">
                Table to Analyze
              </label>
              <div class="flex gap-3">
                <input
                  v-model="quality.tableName"
                  type="text"
                  placeholder="Enter table name"
                  class="flex-1 px-3 py-2 border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                />
                <button
                  @click="analyzeQuality"
                  :disabled="!quality.tableName || isLoading"
                  class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:opacity-50 transition"
                >
                  {{ isLoading ? 'Analyzing...' : '✓ Analyze' }}
                </button>
              </div>
            </div>

            <!-- Quality Results -->
            <div v-if="qualityMetrics" class="space-y-4">
              <!-- Overview -->
              <div class="grid grid-cols-3 gap-4">
                <div class="p-4 bg-blue-50 border border-blue-200 rounded-lg">
                  <div class="text-sm text-blue-600">Total Rows</div>
                  <div class="text-2xl font-bold text-blue-900">{{ qualityMetrics.totalRows }}</div>
                </div>
                <div class="p-4 bg-green-50 border border-green-200 rounded-lg">
                  <div class="text-sm text-green-600">Columns</div>
                  <div class="text-2xl font-bold text-green-900">{{ qualityMetrics.columns.total }}</div>
                </div>
                <div class="p-4 bg-purple-50 border border-purple-200 rounded-lg">
                  <div class="text-sm text-purple-600">Nullable</div>
                  <div class="text-2xl font-bold text-purple-900">{{ qualityMetrics.columns.nullable }}</div>
                </div>
              </div>

              <!-- Nullability Analysis -->
              <div class="p-4 bg-gray-50 border border-gray-200 rounded-lg">
                <h3 class="font-semibold text-gray-900 mb-3">Nullability Analysis</h3>
                <div class="space-y-2 max-h-96 overflow-y-auto">
                  <div v-for="(nullInfo, column) in qualityMetrics.nullability" :key="column" class="text-sm">
                    <div class="flex justify-between mb-1">
                      <code class="text-gray-700">{{ column }}</code>
                      <span class="text-gray-600">{{ nullInfo.nullPercentage }}%</span>
                    </div>
                    <div class="w-full bg-gray-200 rounded h-2">
                      <div
                        class="bg-orange-500 h-2 rounded"
                        :style="{ width: nullInfo.nullPercentage + '%' }"
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Error Messages -->
      <div v-if="error" class="fixed bottom-4 right-4 bg-red-50 border border-red-200 rounded-lg p-4 shadow-lg">
        <div class="font-semibold text-red-800">❌ Error</div>
        <div class="text-red-700 text-sm">{{ error }}</div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import dataManagement from '@/services/dataManagement';

type TabType = 'import' | 'export' | 'bulk' | 'quality';

const activeTab = ref<TabType>('import');
const isLoading = ref(false);
const error = ref<string | null>(null);

// Import state
const import_ = ref({
  tableName: '',
  format: 'csv' as 'csv' | 'json',
  content: '',
  options: {
    hasHeader: true,
    skipEmptyRows: true,
    onDuplicate: 'skip' as 'skip' | 'update' | 'error',
  },
});

const validationResult = ref<any>(null);
const importResult = ref<any>(null);

// Export state
const export_ = ref({
  tableName: '',
  limit: 10000,
  offset: 0,
});

const tableStats = ref<any>(null);
const tableStructure = ref<any>(null);

// Bulk operations state
const bulk = ref({
  tableName: '',
  conditions: '{}',
  updateData: '{}',
});

const bulkDelete = ref({
  tableName: '',
  conditions: '{}',
  confirm: false,
});

const operationResult = ref<any>(null);

// Quality analysis state
const quality = ref({
  tableName: '',
});

const qualityMetrics = ref<any>(null);

// Methods
async function validateImport() {
  try {
    isLoading.value = true;
    error.value = null;
    validationResult.value = await dataManagement.validateImport(
      import_.value.content,
      import_.value.tableName,
      import_.value.format,
      import_.value.options
    );
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Validation failed';
  } finally {
    isLoading.value = false;
  }
}

async function doImport() {
  try {
    isLoading.value = true;
    error.value = null;
    if (import_.value.format === 'csv') {
      importResult.value = await dataManagement.importCsv(
        import_.value.content,
        import_.value.tableName,
        import_.value.options
      );
    } else {
      importResult.value = await dataManagement.importJson(
        import_.value.content,
        import_.value.tableName,
        import_.value.options
      );
    }
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Import failed';
  } finally {
    isLoading.value = false;
  }
}

async function doExport(format: 'csv' | 'json' | 'jsonl' | 'excel') {
  try {
    isLoading.value = true;
    error.value = null;
    let blob: Blob;
    const filename = `${export_.value.tableName}_${new Date().toISOString()}.${format === 'excel' ? 'csv' : format}`;

    if (format === 'csv') {
      blob = await dataManagement.exportCsv(export_.value.tableName, export_.value.limit, export_.value.offset);
    } else if (format === 'json') {
      blob = await dataManagement.exportJson(export_.value.tableName, export_.value.limit, export_.value.offset);
    } else if (format === 'jsonl') {
      blob = await dataManagement.exportJsonL(export_.value.tableName, export_.value.limit, export_.value.offset);
    } else {
      blob = await dataManagement.exportExcel(export_.value.tableName, export_.value.limit, export_.value.offset);
    }

    dataManagement.downloadFile(blob, filename);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Export failed';
  } finally {
    isLoading.value = false;
  }
}

async function getTableStats() {
  try {
    isLoading.value = true;
    error.value = null;
    tableStats.value = await dataManagement.getTableStats(export_.value.tableName);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to get stats';
  } finally {
    isLoading.value = false;
  }
}

async function getTableStructure() {
  try {
    isLoading.value = true;
    error.value = null;
    tableStructure.value = await dataManagement.getTableStructure(export_.value.tableName);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Failed to get structure';
  } finally {
    isLoading.value = false;
  }
}

async function doBulkUpdate() {
  try {
    isLoading.value = true;
    error.value = null;
    const conditions = JSON.parse(bulk.value.conditions);
    const updateData = JSON.parse(bulk.value.updateData);
    operationResult.value = await dataManagement.bulkUpdate(
      bulk.value.tableName,
      updateData,
      conditions
    );
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Bulk update failed';
  } finally {
    isLoading.value = false;
  }
}

async function doBulkDelete() {
  try {
    isLoading.value = true;
    error.value = null;
    const conditions = JSON.parse(bulkDelete.value.conditions);
    operationResult.value = await dataManagement.bulkDelete(
      bulkDelete.value.tableName,
      conditions,
      bulkDelete.value.confirm
    );
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Bulk delete failed';
  } finally {
    isLoading.value = false;
  }
}

async function analyzeQuality() {
  try {
    isLoading.value = true;
    error.value = null;
    qualityMetrics.value = await dataManagement.analyzeQuality(quality.value.tableName);
  } catch (e) {
    error.value = e instanceof Error ? e.message : 'Quality analysis failed';
  } finally {
    isLoading.value = false;
  }
}

function formatBytes(bytes: number): string {
  if (bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}
</script>
