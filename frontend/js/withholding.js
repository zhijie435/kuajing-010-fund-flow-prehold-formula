const WithholdingView = {
  template: `
    <div class="page-container">
      <div class="page-header">
        <div>
          <div class="page-title">预扣计算与明细</div>
          <div style="color: #909399; font-size: 13px; margin-top: 4px;">执行预扣金额计算，查看历史预扣明细记录及关联的资金流水</div>
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

      <el-alert
        title="预扣计算说明"
        type="warning"
        :closable="false"
        style="margin-bottom: 16px;">
        <template #default>
          <div style="font-size: 12px; line-height: 1.6;">
            ① 「执行预扣」会同时写入预扣明细表和资金流水表，不可逆，请确认参数无误后操作<br/>
            ② 执行前建议先点击「预览」查看计算结果<br/>
            ③ 可关联订单号，方便后续按订单追溯预扣记录
          </div>
        </template>
      </el-alert>

      <div style="margin-bottom: 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <el-select v-model="search.formula_id" placeholder="公式" clearable style="width: 160px;">
          <el-option v-for="f in formulaList" :key="f.id" :label="f.name" :value="f.id" />
        </el-select>
        <el-input v-model="search.order_no" placeholder="订单号" clearable style="width: 200px;" @keyup.enter="loadList" />
        <el-date-picker
          v-model="search.dateRange"
          type="daterange"
          range-separator="至"
          start-placeholder="开始日期"
          end-placeholder="结束日期"
          value-format="YYYY-MM-DD"
          style="width: 280px;"
        />
        <el-button type="primary" plain @click="loadList">查询</el-button>
        <el-button @click="resetSearch">重置</el-button>
      </div>

      <el-table :data="list" stripe v-loading="loading">
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="formula_name" label="公式名称" width="140" />
        <el-table-column prop="formula_code" label="编码" width="170">
          <template #default="{ row }">
            <span class="formula-badge">{{ row.formula_code }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="formula" label="表达式" min-width="220" show-overflow-tooltip>
          <template #default="{ row }">
            <code style="background: #f5f7fa; padding: 2px 6px; border-radius: 4px; color: #409eff; font-size: 12px;">{{ row.formula }}</code>
          </template>
        </el-table-column>
        <el-table-column label="变量" width="220" show-overflow-tooltip>
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
        <el-table-column prop="remark" label="备注" min-width="140" show-overflow-tooltip />
        <el-table-column prop="created_at" label="创建时间" width="160" />
        <el-table-column label="操作" width="120" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="showDetail(row)">详情</el-button>
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

      <el-dialog v-model="calcDialogVisible" title="执行预扣计算" width="580px" destroy-on-close @close="calcDialogClose">
        <el-form :model="calcForm" :rules="calcRules" ref="calcFormRef" label-width="110px">
          <el-form-item label="选择公式" prop="formula_code">
            <el-select v-model="calcForm.formula_code" placeholder="请选择预扣公式" style="width: 100%;" @change="onFormulaChange" filterable>
              <el-option v-for="f in activeFormulas" :key="f.code" :label="f.name + ' (' + f.code + ')'" :value="f.code">
                <span>{{ f.name }}</span>
                <span style="float: right; color: #8492a6; font-size: 12px;">{{ f.code }}</span>
              </el-option>
            </el-select>
            <div v-if="calcError" style="color: #f56c6c; font-size: 12px; margin-top: 4px;">
              {{ calcError }}
            </div>
            <div v-if="selectedFormula" style="margin-top: 8px;">
              <el-alert title="公式说明" type="info" :closable="false">
                <template #default>
                  <div style="font-size: 12px;">
                    <div><strong>表达式：</strong><code style="background: #f5f7fa; padding: 2px 6px; border-radius: 4px;">{{ selectedFormula.formula }}</code></div>
                    <div v-if="selectedFormula.description" style="margin-top: 4px;"><strong>说明：</strong>{{ selectedFormula.description }}</div>
                  </div>
                </template>
              </el-alert>
            </div>
          </el-form-item>

          <el-form-item label="订单号">
            <el-input v-model="calcForm.order_no" placeholder="关联订单号（可选，用于追溯）" maxlength="100" show-word-limit />
          </el-form-item>

          <el-form-item v-if="selectedFormula && selectedFormula.variables && selectedFormula.variables.length > 0" label="变量参数" prop="variables">
            <div class="variables-list" style="width: 100%;">
              <div v-for="v in selectedFormula.variables" :key="v.name" class="variable-row">
                <span class="variable-label">
                  {{ v.label }} ({{ v.name }})
                  <el-tooltip v-if="v.default !== undefined" content="默认值" placement="top">
                    <el-icon style="color: #909399; margin-left: 4px;"><QuestionFilled /></el-icon>
                  </el-tooltip>
                </span>
                <div style="flex: 1; display: flex; align-items: center; gap: 8px;">
                  <el-input-number
                    v-model="calcForm.variables[v.name]"
                    :precision="4"
                    :step="0.01"
                    :min="0"
                    style="width: 100%;"
                  />
                  <span style="color: #909399; font-size: 12px; white-space: nowrap;">默认: {{ v.default }}</span>
                </div>
              </div>
            </div>
            <div v-if="variableMissing.length > 0" style="color: #f56c6c; font-size: 12px; margin-top: 8px;">
              <el-icon><Warning /></el-icon>
              缺少变量值：{{ variableMissing.join('、') }}
            </div>
          </el-form-item>

          <el-form-item label="操作人" prop="operator">
            <el-input v-model="calcForm.operator" placeholder="操作人标识" maxlength="100" />
          </el-form-item>

          <el-form-item label="备注">
            <el-input v-model="calcForm.remark" type="textarea" :rows="2" placeholder="备注说明（可选）" maxlength="500" show-word-limit />
          </el-form-item>
        </el-form>

        <div v-if="calcResult" style="margin: 16px 0;">
          <el-alert title="计算结果" type="success" :closable="false">
            <template #default>
              <div style="text-align: center; padding: 8px 0;">
                <div style="font-size: 12px; color: #909399;">{{ calcResult.calculated_at }}</div>
                <div style="font-size: 36px; font-weight: 700; color: #f56c6c; margin: 8px 0;">
                  ¥ {{ formatMoney(calcResult.result) }}
                </div>
                <div style="font-size: 12px; color: #606266; font-family: monospace;">
                  {{ calcResult.formula }}
                </div>
                <div style="margin-top: 8px; font-size: 12px; color: #606266;">
                  代入值:
                  <el-tag v-for="(val, key) in calcResult.variables" :key="key" size="small" style="margin: 0 4px;">
                    {{ key }} = {{ val }}
                  </el-tag>
                </div>
                <div v-if="calcResult.detail_id" style="margin-top: 12px;">
                  <el-tag type="success" effect="dark">
                    <el-icon><Check /></el-icon>
                    已记录 明细ID: {{ calcResult.detail_id }}
                  </el-tag>
                </div>
              </div>
            </template>
          </el-alert>
        </div>

        <template #footer>
          <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <div>
              <el-tag v-if="canCalculate" type="success" effect="plain">
                <el-icon><Check /></el-icon> 参数完整，可以执行
              </el-tag>
              <el-tag v-else type="danger" effect="plain">
                <el-icon><Close /></el-icon> 请完善参数
              </el-tag>
            </div>
            <div>
              <el-button @click="calcDialogVisible = false">关闭</el-button>
              <el-button @click="clearCalcResult">清除结果</el-button>
              <el-button @click="doPreview" :loading="previewLoading" :disabled="!canCalculate">
                <el-icon><View /></el-icon> 预览
              </el-button>
              <el-button type="primary" @click="doCalculate" :loading="calcLoading" :disabled="!canCalculate">
                <el-icon><Check /></el-icon> 执行并记录
              </el-button>
            </div>
          </div>
        </template>
      </el-dialog>

      <el-dialog v-model="batchDialogVisible" title="批量执行预扣" width="720px" destroy-on-close @close="batchDialogClose">
        <el-alert
          title="批量预扣说明"
          type="warning"
          :closable="false"
          style="margin-bottom: 16px;">
          <template #default>
            <div style="font-size: 12px;">
              批量执行采用事务处理：所有条目在同一个数据库事务中执行，部分条目失败不影响其他条目。系统会返回每条的执行结果。
            </div>
          </template>
        </el-alert>
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
          <el-table-column label="变量参数 (JSON)">
            <template #default="{ row }">
              <el-input
                v-model="row.variablesJson"
                type="textarea"
                :rows="2"
                placeholder='{"order_amount":1000,"rate":0.05}'
                size="small"
              />
              <div v-if="row.parseError" style="color: #f56c6c; font-size: 12px; margin-top: 4px;">
                {{ row.parseError }}
              </div>
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

        <div v-if="batchResult" style="margin-top: 16px;">
          <el-alert
            :title="'批量执行结果：成功 ' + batchResult.summary.success + ' 条，失败 ' + batchResult.summary.failed + ' 条'"
            :type="batchResult.summary.failed > 0 ? 'warning' : 'success'"
            show-icon
            :closable="false">
            <div style="margin-top: 8px;">
              <el-table :data="batchResult.results" size="small" style="width: 100%;" max-height="180">
                <el-table-column label="#" width="50" prop="index" />
                <el-table-column label="状态" width="80">
                  <template #default="{ row }">
                    <el-tag :type="row.success ? 'success' : 'danger'" size="small">
                      {{ row.success ? '成功' : '失败' }}
                    </el-tag>
                  </template>
                </el-table-column>
                <el-table-column label="结果">
                  <template #default="{ row }">
                    <span v-if="row.success" style="color: #67c23a;">
                      预扣金额: ¥{{ formatMoney(row.data.result) }}，明细ID: {{ row.data.detail_id }}
                    </span>
                    <span v-else style="color: #f56c6c;">
                      {{ row.error }}
                    </span>
                  </template>
                </el-table-column>
              </el-table>
            </div>
          </el-alert>
        </div>

        <template #footer>
          <el-button @click="batchDialogVisible = false">关闭</el-button>
          <el-button type="primary" @click="doBatch" :loading="batchLoading" :disabled="batchItems.length === 0">
            <el-icon><Play /></el-icon> 执行批量预扣
          </el-button>
        </template>
      </el-dialog>

      <el-drawer v-model="detailDrawerVisible" title="预扣明细详情" size="480px" destroy-on-close>
        <div v-if="detailData">
          <el-descriptions :column="1" border size="small">
            <el-descriptions-item label="ID">{{ detailData.id }}</el-descriptions-item>
            <el-descriptions-item label="公式名称">{{ detailData.formula_name }}</el-descriptions-item>
            <el-descriptions-item label="公式编码">
              <span class="formula-badge">{{ detailData.formula_code }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="表达式">
              <code style="background: #f5f7fa; padding: 2px 6px; border-radius: 4px;">{{ detailData.formula }}</code>
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
          <div style="font-weight: 600; margin-bottom: 12px;">关联资金流水</div>
          <el-table :data="detailData.fund_flows || []" size="small" stripe>
            <el-table-column prop="flow_no" label="流水号" width="180" show-overflow-tooltip />
            <el-table-column prop="flow_type" label="类型" width="70" />
            <el-table-column prop="amount" label="金额" width="100">
              <template #default="{ row }">
                <span :class="row.direction === 1 ? 'flow-in' : 'flow-out'">
                  {{ row.direction === 1 ? '+' : '-' }}¥ {{ formatMoney(row.amount) }}
                </span>
              </template>
            </el-table-column>
            <el-table-column prop="created_at" label="时间" />
          </el-table>
          <div v-if="!detailData.fund_flows || detailData.fund_flows.length === 0" style="text-align: center; padding: 20px; color: #909399;">
            暂无关联流水
          </div>
        </div>
      </el-drawer>
    </div>
  `,
  data() {
    return {
      loading: false,
      list: [],
      formulaList: [],
      activeFormulas: [],
      search: { formula_id: null, order_no: '', dateRange: [] },
      pagination: { page: 1, per_page: 20, total: 0 },
      calcDialogVisible: false,
      batchDialogVisible: false,
      detailDrawerVisible: false,
      detailData: null,
      calcFormRef: null,
      calcForm: {
        formula_code: '', order_no: '', variables: {}, operator: 'admin', remark: ''
      },
      calcRules: {
        formula_code: [{ required: true, message: '请选择公式', trigger: 'change' }],
        operator: [{ required: true, message: '请输入操作人', trigger: 'blur' }],
      },
      selectedFormula: null,
      calcResult: null,
      calcError: '',
      previewLoading: false,
      calcLoading: false,
      batchItems: [],
      batchResult: null,
      batchLoading: false,
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
  mounted() {
    this.loadList();
    this.loadFormulas();
  },
  methods: {
    async loadList() {
      this.loading = true;
      try {
        const params = { page: this.pagination.page, per_page: this.pagination.per_page };
        if (this.search.formula_id) params.formula_id = this.search.formula_id;
        if (this.search.order_no) params.order_no = this.search.order_no;
        const res = await api.withholding.details(params);
        if (res && res.code === 0) {
          this.list = res.data.data;
          this.pagination.total = res.data.total;
        } else if (res && res.message) {
          ElementPlus.ElMessage.error('加载失败: ' + res.message);
        } else {
          ElementPlus.ElMessage.error('加载失败：服务器返回异常');
        }
      } catch (e) {
        ElementPlus.ElMessage.error('加载失败：' + (e.message || '网络错误'));
      } finally {
        this.loading = false;
      }
    },
    resetSearch() {
      this.search = { formula_id: null, order_no: '', dateRange: [] };
      this.pagination.page = 1;
      this.loadList();
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
      this.calcForm = { formula_code: '', order_no: '', variables: {}, operator: 'admin', remark: '' };
      this.selectedFormula = null;
      this.calcResult = null;
      this.calcError = '';
      this.calcDialogVisible = true;
    },
    calcDialogClose() {
      this.calcResult = null;
      this.calcError = '';
    },
    onFormulaChange(code) {
      this.calcError = '';
      this.calcResult = null;
      this.selectedFormula = this.activeFormulas.find(f => f.code === code) || null;
      this.calcForm.variables = {};
      if (this.selectedFormula) {
        (this.selectedFormula.variables || []).forEach(v => {
          this.calcForm.variables[v.name] = v.default !== undefined ? v.default : 0;
        });
      }
    },
    clearCalcResult() {
      this.calcResult = null;
    },
    async doPreview() {
      if (!this.canCalculate) {
        ElementPlus.ElMessage.warning('请先完善所有必填参数');
        return;
      }
      this.calcError = '';
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
          this.calcError = msg;
          ElementPlus.ElMessage.error(msg);
        }
      } catch (e) {
        const msg = '预览异常：' + (e.message || '网络错误');
        this.calcError = msg;
        ElementPlus.ElMessage.error(msg);
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
        {
          confirmButtonText: '确认执行',
          cancelButtonText: '取消',
          type: 'warning',
        }
      ).then(async () => {
        this.calcError = '';
        this.calcLoading = true;
        try {
          const res = await api.withholding.calculate({
            formula_code: this.calcForm.formula_code,
            variables: this.calcForm.variables,
            order_no: this.calcForm.order_no,
            operator: this.calcForm.operator,
            remark: this.calcForm.remark,
          });
          if (res && res.code === 0) {
            this.calcResult = res.data;
            ElementPlus.ElMessage.success('预扣执行成功，已记录明细和资金流水');
            this.loadList();
          } else {
            const msg = res && res.message ? res.message : '执行失败';
            const detailErrors = (res && res.data && res.data.errors) ? res.data.errors : [];
            if (detailErrors.length > 0) {
              ElementPlus.ElMessageBox.alert(
                '<div style="color: #f56c6c;">' + msg + '</div><ul style="padding-left: 20px; margin-top: 10px;"><li>' + detailErrors.join('</li><li>') + '</li></ul>',
                '执行失败',
                { dangerouslyUseHTMLString: true, type: 'error' }
              );
            } else {
              this.calcError = msg;
              ElementPlus.ElMessage.error(msg);
            }
          }
        } catch (e) {
          const msg = '执行异常：' + (e.message || '网络错误');
          this.calcError = msg;
          ElementPlus.ElMessage.error(msg);
        } finally {
          this.calcLoading = false;
        }
      }).catch(() => {});
    },
    openBatchDialog() {
      this.batchItems = [];
      this.batchResult = null;
      this.addBatchItem();
      this.batchDialogVisible = true;
    },
    batchDialogClose() {
      this.batchResult = null;
    },
    addBatchItem() {
      const defaultFormula = this.activeFormulas[0] || null;
      const item = {
        formula_code: defaultFormula ? defaultFormula.code : '',
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
      let hasError = false;

      for (let i = 0; i < this.batchItems.length; i++) {
        const it = this.batchItems[i];
        let variables = {};
        it.parseError = '';

        if (!it.formula_code) {
          it.parseError = '请选择公式';
          hasError = true;
          continue;
        }

        if (!it.variablesJson) {
          it.parseError = '请输入变量参数JSON';
          hasError = true;
          continue;
        }

        try {
          variables = JSON.parse(it.variablesJson);
        } catch (e) {
          it.parseError = 'JSON格式错误: ' + e.message;
          hasError = true;
          continue;
        }

        items.push({
          formula_code: it.formula_code,
          variables,
          order_no: it.order_no || null,
          operator: 'admin',
        });
      }

      if (hasError) {
        ElementPlus.ElMessage.warning('请修正红色错误提示后再执行');
        return;
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
            this.batchResult = res.data;
            if (res.data.summary.failed > 0) {
              ElementPlus.ElMessage.warning(
                `批量执行完成：成功 ${res.data.summary.success} 条，失败 ${res.data.summary.failed} 条`
              );
            } else {
              ElementPlus.ElMessage.success(`批量执行成功：${res.data.summary.success} 条`);
            }
            this.loadList();
          } else {
            const msg = res && res.message ? res.message : '批量执行失败';
            ElementPlus.ElMessage.error(msg);
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
    formatMoney(v) {
      return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
  },
};
