const WithholdingView = {
  template: `
    <div class="page-container">
      <div class="page-header">
        <div>
          <div class="page-title">预扣明细管理</div>
          <div style="color: #909399; font-size: 13px; margin-top: 4px;">管理预扣明细记录，查看状态变更历史和操作记录</div>
        </div>
        <div style="display: flex; gap: 12px;">
          <el-button type="warning" @click="openBatchDialog">
            <el-icon><DocumentCopy /></el-icon>
            批量计算
          </el-button>
          <el-button type="primary" @click="openCalcDialog">
            <el-icon><Plus /></el-icon>
            执行预扣
          </el-button>
        </div>
      </div>

      <div class="stats-cards" style="margin-bottom: 20px;">
        <div class="stat-card">
          <div class="label">总预扣笔数</div>
          <div class="value">{{ stats.total_count || 0 }}</div>
        </div>
        <div class="stat-card orange">
          <div class="label">总预扣金额</div>
          <div class="value">¥ {{ formatMoney(stats.total_amount) }}</div>
        </div>
        <div class="stat-card green">
          <div class="label">已完成</div>
          <div class="value">{{ stats.completed_count || 0 }} 笔</div>
        </div>
        <div class="stat-card blue">
          <div class="label">待处理</div>
          <div class="value">{{ stats.pending_count || 0 }} 笔</div>
        </div>
      </div>

      <div class="section-card" style="margin-bottom: 20px;">
        <div class="section-title">筛选条件</div>
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
          <el-select v-model="search.status" placeholder="状态" clearable style="width: 140px;">
            <el-option v-for="(label, value) in statusTypes" :key="value" :label="label" :value="Number(value)" />
          </el-select>
          <el-input v-model="search.order_no" placeholder="订单号" clearable style="width: 200px;" @keyup.enter="loadList" />
          <el-input v-model="search.formula_code" placeholder="公式编码" clearable style="width: 180px;" @keyup.enter="loadList" />
          <el-input-number v-model="search.formula_id" placeholder="公式ID" :min="1" controls-position="right" clearable style="width: 140px;" />
          <el-button type="primary" @click="loadList">
            <el-icon><Search /></el-icon> 查询
          </el-button>
          <el-button @click="resetSearch">
            <el-icon><Refresh /></el-icon> 重置
          </el-button>
        </div>
      </div>

      <div class="section-card">
        <div class="section-title">预扣明细列表</div>
        <el-table :data="list" stripe v-loading="loading">
          <el-table-column prop="id" label="ID" width="60" />
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-tooltip :content="row.status_description" placement="top">
                <el-tag :type="row.status_tag_type" effect="light">{{ row.status_label }}</el-tag>
              </el-tooltip>
            </template>
          </el-table-column>
          <el-table-column prop="formula_name" label="公式名称" width="140" />
          <el-table-column prop="formula_code" label="编码" width="150">
            <template #default="{ row }">
              <span class="formula-badge">{{ row.formula_code }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="formula" label="表达式" min-width="200" show-overflow-tooltip>
            <template #default="{ row }">
              <code style="background: #f5f7fa; padding: 2px 6px; border-radius: 4px; color: #409eff; font-size: 12px;">{{ row.formula }}</code>
            </template>
          </el-table-column>
          <el-table-column label="变量" width="200" show-overflow-tooltip>
            <template #default="{ row }">
              <span v-for="(val, key) in row.variables" :key="key" class="variable-tag">
                {{ key }}: {{ val }}
              </span>
              <span v-if="!row.variables || Object.keys(row.variables).length === 0" style="color: #c0c4cc;">无</span>
            </template>
          </el-table-column>
          <el-table-column prop="result" label="预扣金额" width="120">
            <template #default="{ row }">
              <span style="color: #f56c6c; font-weight: 600;">¥ {{ formatMoney(row.result) }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="order_no" label="订单号" width="160" show-overflow-tooltip />
          <el-table-column prop="operator" label="操作人" width="100" />
          <el-table-column prop="created_at" label="创建时间" width="160" />
          <el-table-column label="操作" width="280" fixed="right">
            <template #default="{ row }">
              <el-button link type="primary" size="small" @click="showDetail(row)">详情</el-button>
              <el-button 
                link 
                type="warning" 
                size="small" 
                @click="openStatusDialog(row)"
                :disabled="row.available_statuses && row.available_statuses.length === 0">
                变更状态
              </el-button>
              <el-button link type="success" size="small" @click="openRemarkDialog(row)">备注</el-button>
            </template>
          </el-table-column>
        </el-table>

        <div style="margin-top: 16px; display: flex; justify-content: flex-end;">
          <el-pagination
            v-model:current-page="pagination.page"
            v-model:page-size="pagination.per_page"
            :total="pagination.total"
            :page-sizes="[10, 20, 50, 100]"
            layout="total, sizes, prev, pager, next, jumper"
            @size-change="loadList"
            @current-change="loadList"
          />
        </div>
      </div>

      <el-dialog v-model="calcDialogVisible" title="执行预扣计算" width="580px" destroy-on-close @close="calcDialogClose">
        <el-form :model="calcForm" :rules="calcRules" ref="calcFormRef" label-width="110px">
          <el-form-item label="选择公式" prop="formula_code">
            <el-select v-model="calcForm.formula_code" placeholder="请选择预扣公式" style="width: 100%;" @change="onFormulaChange" filterable>
              <el-option v-for="f in activeFormulas" :key="f.code" :label="f.name + ' (' + f.code + ')'" :value="f.code">
                <span>{{ f.name }}</span>
                <span style="float: right; color: #8492a6; font-size: 12px;">{{ f.code }}</span>
              </el-option>
            </el-select>
          </el-form-item>

          <el-form-item label="初始状态">
            <el-radio-group v-model="calcForm.initial_status">
              <el-radio :value="1">已完成</el-radio>
              <el-radio :value="0">待处理</el-radio>
            </el-radio-group>
          </el-form-item>

          <el-form-item label="订单号">
            <el-input v-model="calcForm.order_no" placeholder="关联订单号（可选，用于追溯）" maxlength="100" />
          </el-form-item>

          <el-form-item v-if="selectedFormula && selectedFormula.variables && selectedFormula.variables.length > 0" label="变量参数" prop="variables">
            <div class="variables-list" style="width: 100%;">
              <div v-for="v in selectedFormula.variables" :key="v.name" class="variable-row">
                <span class="variable-label">{{ v.label }} ({{ v.name }})</span>
                <div style="flex: 1; display: flex; align-items: center; gap: 8px;">
                  <el-input-number v-model="calcForm.variables[v.name]" :precision="4" :step="0.01" :min="0" style="width: 100%;" />
                  <span style="color: #909399; font-size: 12px; white-space: nowrap;">默认: {{ v.default }}</span>
                </div>
              </div>
            </div>
          </el-form-item>

          <el-form-item label="操作人" prop="operator">
            <el-input v-model="calcForm.operator" placeholder="操作人标识" maxlength="100" />
          </el-form-item>

          <el-form-item label="备注">
            <el-input v-model="calcForm.remark" type="textarea" :rows="2" placeholder="备注说明（可选）" maxlength="500" />
          </el-form-item>
        </el-form>

        <div v-if="calcResult" style="margin: 16px 0;">
          <el-alert title="计算结果" type="success" :closable="false">
            <div style="text-align: center; padding: 8px 0;">
              <div style="font-size: 36px; font-weight: 700; color: #f56c6c; margin: 8px 0;">
                ¥ {{ formatMoney(calcResult.result) }}
              </div>
              <div style="font-size: 12px; color: #606266; font-family: monospace;">{{ calcResult.formula }}</div>
            </div>
          </el-alert>
        </div>

        <template #footer>
          <div style="display: flex; justify-content: flex-end; gap: 8px;">
            <el-button @click="calcDialogVisible = false">关闭</el-button>
            <el-button @click="doPreview" :loading="previewLoading" :disabled="!canCalculate">预览</el-button>
            <el-button type="primary" @click="doCalculate" :loading="calcLoading" :disabled="!canCalculate">执行并记录</el-button>
          </div>
        </template>
      </el-dialog>

      <el-dialog v-model="batchDialogVisible" title="批量执行预扣" width="720px" destroy-on-close>
        <div style="margin-bottom: 12px; display: flex; justify-content: space-between; align-items: center;">
          <el-button size="small" type="primary" link @click="addBatchItem">
            <el-icon><Plus /></el-icon> 添加条目
          </el-button>
          <span style="color: #909399; font-size: 12px;">共 {{ batchItems.length }} 条</span>
        </div>
        <el-table :data="batchItems" border size="small" style="width: 100%;" max-height="320">
          <el-table-column label="#" width="50" type="index" />
          <el-table-column label="公式" width="200">
            <template #default="{ row }">
              <el-select v-model="row.formula_code" placeholder="选择公式" style="width: 100%;" size="small" filterable>
                <el-option v-for="f in activeFormulas" :key="f.code" :label="f.name" :value="f.code" />
              </el-select>
            </template>
          </el-table-column>
          <el-table-column label="初始状态" width="110">
            <template #default="{ row }">
              <el-radio-group v-model="row.initial_status" size="small">
                <el-radio :value="1">完成</el-radio>
                <el-radio :value="0">待处理</el-radio>
              </el-radio-group>
            </template>
          </el-table-column>
          <el-table-column label="变量参数 (JSON)">
            <template #default="{ row }">
              <el-input v-model="row.variablesJson" type="textarea" :rows="2" placeholder='{"order_amount":1000}' size="small" />
            </template>
          </el-table-column>
          <el-table-column label="订单号" width="140">
            <template #default="{ row }">
              <el-input v-model="row.order_no" placeholder="可选" size="small" />
            </template>
          </el-table-column>
          <el-table-column label="操作" width="60">
            <template #default="{ $index }">
              <el-button link type="danger" size="small" @click="batchItems.splice($index, 1)">删</el-button>
            </template>
          </el-table-column>
        </el-table>

        <template #footer>
          <el-button @click="batchDialogVisible = false">关闭</el-button>
          <el-button type="primary" @click="doBatch" :loading="batchLoading" :disabled="batchItems.length === 0">执行批量预扣</el-button>
        </template>
      </el-dialog>

      <el-drawer v-model="detailDrawerVisible" title="预扣明细详情" size="520px" destroy-on-close>
        <div v-if="detailData">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div class="page-title" style="font-size: 16px;">基本信息</div>
            <el-tag :type="detailData.status_tag_type" size="large">{{ detailData.status_label }}</el-tag>
          </div>
          
          <el-descriptions :column="1" border size="small">
            <el-descriptions-item label="ID">{{ detailData.id }}</el-descriptions-item>
            <el-descriptions-item label="状态说明">{{ detailData.status_description }}</el-descriptions-item>
            <el-descriptions-item label="公式名称">{{ detailData.formula_name }}</el-descriptions-item>
            <el-descriptions-item label="公式编码">
              <span class="formula-badge">{{ detailData.formula_code }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="预扣金额">
              <span style="color: #f56c6c; font-weight: 600; font-size: 16px;">¥ {{ formatMoney(detailData.result) }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="变量参数">
              <div>
                <el-tag v-for="(val, key) in detailData.variables" :key="key" size="small" style="margin: 2px;">
                  {{ key }}: {{ val }}
                </el-tag>
              </div>
            </el-descriptions-item>
            <el-descriptions-item label="订单号">{{ detailData.order_no || '-' }}</el-descriptions-item>
            <el-descriptions-item label="操作人">{{ detailData.operator || '-' }}</el-descriptions-item>
            <el-descriptions-item label="备注">{{ detailData.remark || '-' }}</el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ detailData.created_at }}</el-descriptions-item>
          </el-descriptions>

          <el-divider />
          <div class="section-title" style="font-size: 14px; margin-bottom: 12px;">关联资金流水</div>
          <el-table :data="detailData.fund_flows || []" size="small" stripe>
            <el-table-column prop="flow_no" label="流水号" width="180" show-overflow-tooltip />
            <el-table-column label="状态" width="90">
              <template #default="{ row }">
                <el-tag :type="row.status_tag_type" size="small">{{ row.status_label }}</el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="flow_type" label="类型" width="70" />
            <el-table-column prop="amount" label="金额" width="100">
              <template #default="{ row }">
                <span :class="row.direction === 1 ? 'flow-in' : 'flow-out'">
                  {{ row.direction === 1 ? '+' : '-' }}¥ {{ formatMoney(row.amount) }}
                </span>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="时间" width="160" />
          </el-table>

          <el-divider />
          <div class="section-title" style="font-size: 14px; margin-bottom: 12px;">操作记录</div>
          <el-timeline>
            <el-timeline-item
              v-for="log in detailData.operation_logs || []"
              :key="log.id"
              :timestamp="log.created_at"
              placement="top">
              <el-card shadow="never" style="margin-bottom: 0;">
                <div style="display: flex; justify-content: space-between; align-items: center;">
                  <div>
                    <el-tag :type="log.action === 'status_change' ? 'warning' : 'info'" size="small">{{ log.action_label }}</el-tag>
                    <span style="margin-left: 8px; color: #606266;">{{ log.remark }}</span>
                  </div>
                  <span style="color: #909399; font-size: 12px;">操作人: {{ log.operator }}</span>
                </div>
                <div v-if="log.old_value || log.new_value" style="margin-top: 8px; font-size: 12px; color: #909399; font-family: monospace;">
                  <span v-if="log.old_value">原值: {{ JSON.stringify(log.old_value) }}</span>
                  <span v-if="log.old_value && log.new_value"> → </span>
                  <span v-if="log.new_value">新值: {{ JSON.stringify(log.new_value) }}</span>
                </div>
              </el-card>
            </el-timeline-item>
          </el-timeline>
          <div v-if="!detailData.operation_logs || detailData.operation_logs.length === 0" style="text-align: center; padding: 20px; color: #909399;">
            暂无操作记录
          </div>
        </div>

        <template #footer>
          <div style="display: flex; justify-content: flex-end; gap: 8px;">
            <el-button @click="detailDrawerVisible = false">关闭</el-button>
            <el-button 
              type="warning" 
              @click="openStatusDialog(detailData)"
              :disabled="detailData.available_statuses && detailData.available_statuses.length === 0">
              变更状态
            </el-button>
            <el-button type="success" @click="openRemarkDialog(detailData)">添加备注</el-button>
          </div>
        </template>
      </el-drawer>

      <el-dialog v-model="statusDialogVisible" title="变更状态" width="480px" destroy-on-close>
        <div v-if="selectedRow">
          <el-alert title="当前状态" :type="selectedRow.status_tag_type" :closable="false" style="margin-bottom: 16px;">
            <div style="display: flex; align-items: center; gap: 12px;">
              <el-tag :type="selectedRow.status_tag_type" size="large">{{ selectedRow.status_label }}</el-tag>
              <span>{{ selectedRow.status_description }}</span>
            </div>
          </el-alert>

          <el-form :model="statusForm" label-width="100px">
            <el-form-item label="变更为" required>
              <el-select v-model="statusForm.status" placeholder="请选择目标状态" style="width: 100%;">
                <el-option 
                  v-for="status in availableStatuses" 
                  :key="status.value" 
                  :label="status.label + ' - ' + status.description" 
                  :value="status.value" />
              </el-select>
            </el-form-item>
            <el-form-item label="操作人">
              <el-input v-model="statusForm.operator" placeholder="操作人标识" maxlength="100" />
            </el-form-item>
            <el-form-item label="变更原因">
              <el-input v-model="statusForm.remark" type="textarea" :rows="3" placeholder="请说明变更原因" maxlength="500" />
            </el-form-item>
          </el-form>

          <el-alert 
            v-if="statusChangeImpact" 
            title="影响说明" 
            type="warning" 
            :closable="false"
            style="margin-top: 16px;">
            <div style="font-size: 12px; line-height: 1.6;">
              {{ statusChangeImpact }}
            </div>
          </el-alert>
        </div>

        <template #footer>
          <el-button @click="statusDialogVisible = false">取消</el-button>
          <el-button type="primary" @click="doStatusChange" :loading="statusChanging" :disabled="!statusForm.status">确认变更</el-button>
        </template>
      </el-dialog>

      <el-dialog v-model="remarkDialogVisible" title="添加备注" width="420px" destroy-on-close>
        <el-form :model="remarkForm" label-width="100px">
          <el-form-item label="备注内容" required>
            <el-input v-model="remarkForm.remark" type="textarea" :rows="4" placeholder="请输入备注内容" maxlength="500" show-word-limit />
          </el-form-item>
          <el-form-item label="操作人">
            <el-input v-model="remarkForm.operator" placeholder="操作人标识" maxlength="100" />
          </el-form-item>
        </el-form>

        <template #footer>
          <el-button @click="remarkDialogVisible = false">取消</el-button>
          <el-button type="primary" @click="doAddRemark" :loading="remarkAdding" :disabled="!remarkForm.remark">确认添加</el-button>
        </template>
      </el-dialog>
    </div>
  `,
  data() {
    return {
      loading: false,
      list: [],
      formulaList: [],
      activeFormulas: [],
      statusTypes: {},
      stats: { total_count: 0, total_amount: 0, completed_count: 0, pending_count: 0 },
      search: { status: null, order_no: '', formula_code: '', formula_id: null },
      pagination: { page: 1, per_page: 20, total: 0 },
      calcDialogVisible: false,
      batchDialogVisible: false,
      detailDrawerVisible: false,
      statusDialogVisible: false,
      remarkDialogVisible: false,
      detailData: null,
      selectedRow: null,
      calcFormRef: null,
      calcForm: {
        formula_code: '', order_no: '', variables: {}, operator: 'admin', remark: '', initial_status: 1
      },
      calcRules: {
        formula_code: [{ required: true, message: '请选择公式', trigger: 'change' }],
        operator: [{ required: true, message: '请输入操作人', trigger: 'blur' }],
      },
      selectedFormula: null,
      calcResult: null,
      previewLoading: false,
      calcLoading: false,
      batchItems: [],
      batchLoading: false,
      statusForm: { status: null, operator: 'admin', remark: '' },
      availableStatuses: [],
      statusChanging: false,
      statusChangeImpact: '',
      remarkForm: { remark: '', operator: 'admin' },
      remarkAdding: false,
    };
  },
  computed: {
    variableMissing() {
      if (!this.selectedFormula || !this.selectedFormula.variables) return [];
      const missing = [];
      this.selectedFormula.variables.forEach(v => {
        if (this.calcForm.variables[v.name] === undefined || this.calcForm.variables[v.name] === null || this.calcForm.variables[v.name] === '') {
          missing.push(v.name);
        }
      });
      return missing;
    },
    canCalculate() {
      if (!this.calcForm.formula_code) return false;
      if (!this.calcForm.operator) return false;
      if (this.variableMissing.length > 0) return false;
      return true;
    },
  },
  watch: {
    'statusForm.status'(newVal) {
      if (newVal !== null && newVal !== undefined) {
        if (this.selectedRow && this.selectedRow.result) {
          const amount = this.selectedRow.result;
          if (this.selectedRow.status === 1 && newVal !== 1) {
            this.statusChangeImpact = `此操作将把预扣金额 ¥${this.formatMoney(amount)} 从账户余额中退回，并同步更新所有关联资金流水的状态和后续余额。`;
          } else if (this.selectedRow.status !== 1 && newVal === 1) {
            this.statusChangeImpact = `此操作将从账户余额中扣除 ¥${this.formatMoney(amount)}，并同步更新所有关联资金流水的状态和后续余额。`;
          } else {
            this.statusChangeImpact = '此操作将同步更新所有关联资金流水的状态，但不影响账户余额。';
          }
        }
      } else {
        this.statusChangeImpact = '';
      }
    }
  },
  mounted() {
    this.loadList();
    this.loadFormulas();
    this.loadStatusTypes();
    this.loadStats();
  },
  methods: {
    async loadStatusTypes() {
      try {
        const res = await api.withholding.statusTypes();
        if (res && res.code === 0) {
          this.statusTypes = res.data.status_types;
        }
      } catch (e) {
        console.error('加载状态类型失败:', e);
      }
    },
    async loadStats() {
      try {
        const res = await api.withholding.details({ per_page: 1000 });
        if (res && res.code === 0) {
          const data = res.data.data;
          this.stats.total_count = data.length;
          this.stats.total_amount = data.reduce((sum, item) => sum + (Number(item.result) || 0), 0);
          this.stats.completed_count = data.filter(item => item.status === 1).length;
          this.stats.pending_count = data.filter(item => item.status === 0).length;
        }
      } catch (e) {
        console.error('加载统计失败:', e);
      }
    },
    async loadList() {
      this.loading = true;
      try {
        const params = { page: this.pagination.page, per_page: this.pagination.per_page };
        if (this.search.status !== null && this.search.status !== '') params.status = this.search.status;
        if (this.search.order_no) params.order_no = this.search.order_no;
        if (this.search.formula_code) params.formula_code = this.search.formula_code;
        if (this.search.formula_id) params.formula_id = this.search.formula_id;
        const res = await api.withholding.details(params);
        if (res && res.code === 0) {
          this.list = res.data.data;
          this.pagination.total = res.data.total;
          if (res.data.status_types) {
            this.statusTypes = res.data.status_types;
          }
        } else if (res && res.message) {
          ElementPlus.ElMessage.error('加载失败: ' + res.message);
        }
      } catch (e) {
        ElementPlus.ElMessage.error('加载失败：' + (e.message || '网络错误'));
      } finally {
        this.loading = false;
      }
    },
    resetSearch() {
      this.search = { status: null, order_no: '', formula_code: '', formula_id: null };
      this.pagination.page = 1;
      this.loadList();
      this.loadStats();
    },
    async loadFormulas() {
      try {
        const res = await api.formula.active();
        if (res && res.code === 0) {
          this.activeFormulas = res.data;
        }
        const allRes = await api.formula.list({ per_page: 200 });
        if (allRes && allRes.code === 0) {
          this.formulaList = allRes.data.data;
        }
      } catch (e) {
        console.error('加载公式失败:', e);
      }
    },
    openCalcDialog() {
      this.calcForm = { formula_code: '', order_no: '', variables: {}, operator: 'admin', remark: '', initial_status: 1 };
      this.selectedFormula = null;
      this.calcResult = null;
      this.calcDialogVisible = true;
    },
    calcDialogClose() {
      this.calcResult = null;
    },
    onFormulaChange(code) {
      this.calcResult = null;
      this.selectedFormula = this.activeFormulas.find(f => f.code === code) || null;
      this.calcForm.variables = {};
      if (this.selectedFormula) {
        (this.selectedFormula.variables || []).forEach(v => {
          this.calcForm.variables[v.name] = v.default !== undefined ? v.default : 0;
        });
      }
    },
    async doPreview() {
      if (!this.canCalculate) {
        ElementPlus.ElMessage.warning('请先完善所有必填参数');
        return;
      }
      this.previewLoading = true;
      try {
        const res = await api.withholding.preview({
          formula_code: this.calcForm.formula_code,
          variables: this.calcForm.variables,
        });
        if (res && res.code === 0) {
          this.calcResult = res.data;
        } else {
          const msg = res && res.message ? res.message : '预览失败';
          ElementPlus.ElMessage.error(msg);
        }
      } catch (e) {
        ElementPlus.ElMessage.error('预览异常：' + (e.message || '网络错误'));
      } finally {
        this.previewLoading = false;
      }
    },
    async doCalculate() {
      if (!this.canCalculate) {
        ElementPlus.ElMessage.warning('请先完善所有必填参数');
        return;
      }
      ElementPlus.ElMessageBox.confirm(
        '确定执行预扣？执行后将同时写入预扣明细和资金流水，不可逆。',
        '确认执行',
        { confirmButtonText: '确认执行', cancelButtonText: '取消', type: 'warning' }
      ).then(async () => {
        this.calcLoading = true;
        try {
          const res = await api.withholding.calculate({
            formula_code: this.calcForm.formula_code,
            variables: this.calcForm.variables,
            order_no: this.calcForm.order_no,
            operator: this.calcForm.operator,
            remark: this.calcForm.remark,
            initial_status: this.calcForm.initial_status,
          });
          if (res && res.code === 0) {
            this.calcResult = res.data;
            ElementPlus.ElMessage.success('预扣执行成功，已记录明细和资金流水');
            this.loadList();
            this.loadStats();
          } else {
            const msg = res && res.message ? res.message : '执行失败';
            ElementPlus.ElMessage.error(msg);
          }
        } catch (e) {
          ElementPlus.ElMessage.error('执行异常：' + (e.message || '网络错误'));
        } finally {
          this.calcLoading = false;
        }
      }).catch(() => {});
    },
    openBatchDialog() {
      this.batchItems = [];
      this.addBatchItem();
      this.batchDialogVisible = true;
    },
    addBatchItem() {
      const defaultFormula = this.activeFormulas[0] || null;
      const item = {
        formula_code: defaultFormula ? defaultFormula.code : '',
        initial_status: 1,
        variablesJson: '',
        order_no: '',
        parseError: '',
      };
      if (defaultFormula && defaultFormula.variables) {
        const defaults = {};
        defaultFormula.variables.forEach(v => {
          defaults[v.name] = v.default !== undefined ? v.default : 0;
        });
        item.variablesJson = JSON.stringify(defaults, null, 2);
      }
      this.batchItems.push(item);
    },
    async doBatch() {
      const items = [];
      for (let i = 0; i < this.batchItems.length; i++) {
        const it = this.batchItems[i];
        let variables = {};
        try {
          variables = JSON.parse(it.variablesJson);
        } catch (e) {
          ElementPlus.ElMessage.error(`第 ${i + 1} 条JSON格式错误: ${e.message}`);
          return;
        }
        items.push({
          formula_code: it.formula_code,
          variables,
          order_no: it.order_no || null,
          operator: 'admin',
          initial_status: it.initial_status,
        });
      }
      ElementPlus.ElMessageBox.confirm(
        `确定执行 ${items.length} 条预扣？执行后将同时写入预扣明细和资金流水。`,
        '确认批量执行',
        { confirmButtonText: '确认执行', cancelButtonText: '取消', type: 'warning' }
      ).then(async () => {
        this.batchLoading = true;
        try {
          const res = await api.withholding.batchCalculate({ items });
          if (res && res.code === 0) {
            ElementPlus.ElMessage.success(`批量执行成功：${res.data.summary.success} 条`);
            this.loadList();
            this.loadStats();
          } else {
            ElementPlus.ElMessage.error(res && res.message ? res.message : '批量执行失败');
          }
        } catch (e) {
          ElementPlus.ElMessage.error('执行异常：' + (e.message || '网络错误'));
        } finally {
          this.batchLoading = false;
        }
      }).catch(() => {});
    },
    async showDetail(row) {
      try {
        const res = await api.withholding.detail(row.id);
        if (res && res.code === 0) {
          this.detailData = res.data;
          this.detailDrawerVisible = true;
        } else {
          ElementPlus.ElMessage.error(res && res.message ? res.message : '加载详情失败');
        }
      } catch (e) {
        ElementPlus.ElMessage.error('加载异常：' + (e.message || '网络错误'));
      }
    },
    openStatusDialog(row) {
      this.selectedRow = row;
      this.statusForm = { status: null, operator: 'admin', remark: '' };
      this.statusChangeImpact = '';
      if (row.available_statuses && row.available_statuses.length > 0) {
        this.availableStatuses = row.available_statuses;
      } else {
        this.availableStatuses = this.getAvailableStatuses(row.status);
      }
      this.statusDialogVisible = true;
    },
    getAvailableStatuses(currentStatus) {
      const statuses = [];
      const transitions = {
        0: [1, 2, 3],
        1: [4, 5],
        2: [0, 3],
        3: [],
        4: [],
        5: [4]
      };
      const labels = { 0: '待处理', 1: '已完成', 2: '失败', 3: '已取消', 4: '已冲正', 5: '已结算' };
      const descs = { 
        0: '预扣计算完成，等待关联资金流水确认',
        1: '预扣已完成，关联资金流水已到账',
        2: '预扣计算或关联流水处理失败',
        3: '预扣已被手动取消',
        4: '原预扣已被冲正撤销',
        5: '预扣金额已完成最终结算'
      };
      (transitions[currentStatus] || []).forEach(val => {
        statuses.push({ value: val, label: labels[val], description: descs[val] });
      });
      return statuses;
    },
    async doStatusChange() {
      if (!this.selectedRow || this.statusForm.status === null) return;
      this.statusChanging = true;
      try {
        const res = await api.withholding.changeStatus(this.selectedRow.id, {
          status: this.statusForm.status,
          operator: this.statusForm.operator,
          remark: this.statusForm.remark,
        });
        if (res && res.code === 0) {
          ElementPlus.ElMessage.success('状态变更成功');
          this.statusDialogVisible = false;
          this.loadList();
          this.loadStats();
          if (this.detailDrawerVisible) {
            this.showDetail(this.selectedRow);
          }
        } else {
          ElementPlus.ElMessage.error(res && res.message ? res.message : '状态变更失败');
        }
      } catch (e) {
        ElementPlus.ElMessage.error('状态变更异常：' + (e.message || '网络错误'));
      } finally {
        this.statusChanging = false;
      }
    },
    openRemarkDialog(row) {
      this.selectedRow = row;
      this.remarkForm = { remark: '', operator: 'admin' };
      this.remarkDialogVisible = true;
    },
    async doAddRemark() {
      if (!this.selectedRow || !this.remarkForm.remark) return;
      this.remarkAdding = true;
      try {
        const res = await api.withholding.addRemark(this.selectedRow.id, {
          remark: this.remarkForm.remark,
          operator: this.remarkForm.operator,
        });
        if (res && res.code === 0) {
          ElementPlus.ElMessage.success('备注添加成功');
          this.remarkDialogVisible = false;
          this.loadList();
          if (this.detailDrawerVisible) {
            this.showDetail(this.selectedRow);
          }
        } else {
          ElementPlus.ElMessage.error(res && res.message ? res.message : '备注添加失败');
        }
      } catch (e) {
        ElementPlus.ElMessage.error('备注添加异常：' + (e.message || '网络错误'));
      } finally {
        this.remarkAdding = false;
      }
    },
    formatMoney(v) {
      return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
  },
};
