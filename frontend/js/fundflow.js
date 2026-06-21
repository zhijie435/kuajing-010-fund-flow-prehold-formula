const FundFlowView = {
  template: `
    <div class="page-container">
      <div class="page-header">
        <div>
          <div class="page-title">资金流水管理</div>
          <div style="color: #909399; font-size: 13px; margin-top: 4px;">实时追踪账户资金流向，查看余额变动和操作历史</div>
        </div>
        <div style="display: flex; gap: 12px;">
          <el-button type="warning" @click="reconcileBalance">
            <el-icon><RefreshRight /></el-icon>
            余额校准
          </el-button>
        </div>
      </div>

      <div class="stats-cards" style="margin-bottom: 20px;">
        <div class="stat-card blue">
          <div class="label">当前账户余额</div>
          <div class="value">¥ {{ formatMoney(stats.current_balance) }}</div>
        </div>
        <div class="stat-card green">
          <div class="label">累计流入</div>
          <div class="value">¥ {{ formatMoney(stats.total_inflow) }}</div>
        </div>
        <div class="stat-card orange">
          <div class="label">累计流出</div>
          <div class="value">¥ {{ formatMoney(stats.total_outflow) }}</div>
        </div>
        <div class="stat-card">
          <div class="label">总流水笔数</div>
          <div class="value">{{ stats.total_count }} 笔</div>
        </div>
      </div>

      <div class="section-card" style="margin-bottom: 20px;">
        <div class="section-title">筛选条件</div>
        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
          <el-select v-model="search.status" placeholder="状态" clearable style="width: 140px;">
            <el-option v-for="(label, value) in statusTypes" :key="value" :label="label" :value="Number(value)" />
          </el-select>
          <el-select v-model="search.flow_type" placeholder="流水类型" clearable style="width: 140px;">
            <el-option label="充值" value="recharge" />
            <el-option label="提现" value="withdraw" />
            <el-option label="预扣" value="withholding" />
            <el-option label="退款" value="refund" />
            <el-option label="调账" value="adjustment" />
            <el-option label="其他" value="other" />
          </el-select>
          <el-select v-model="search.direction" placeholder="方向" clearable style="width: 110px;">
            <el-option label="流入" :value="1" />
            <el-option label="流出" :value="2" />
          </el-select>
          <el-input v-model="search.keyword" placeholder="流水号/关联单号" clearable style="width: 200px;" @keyup.enter="loadList" />
          <el-input-number v-model="search.min_amount" placeholder="最小金额" :precision="2" :step="100" controls-position="right" clearable style="width: 140px;" />
          <el-input-number v-model="search.max_amount" placeholder="最大金额" :precision="2" :step="100" controls-position="right" clearable style="width: 140px;" />
          <el-button type="primary" @click="loadList">
            <el-icon><Search /></el-icon> 查询
          </el-button>
          <el-button @click="resetSearch">
            <el-icon><Refresh /></el-icon> 重置
          </el-button>
        </div>
      </div>

      <div class="section-card">
        <div class="section-title">流水列表</div>
        <el-table :data="list" stripe v-loading="loading">
          <el-table-column prop="id" label="ID" width="60" />
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-tooltip :content="row.status_description" placement="top">
                <el-tag :type="row.status_tag_type" effect="light">{{ row.status_label }}</el-tag>
              </el-tooltip>
            </template>
          </el-table-column>
          <el-table-column prop="flow_no" label="流水号" width="220" show-overflow-tooltip>
            <template #default="{ row }">
              <code style="font-size: 11px; background: #f5f7fa; padding: 2px 4px; border-radius: 3px;">{{ row.flow_no }}</code>
            </template>
          </el-table-column>
          <el-table-column label="方向" width="80">
            <template #default="{ row }">
              <el-tag v-if="row.direction === 1" type="success" effect="dark" size="small">流入</el-tag>
              <el-tag v-else type="danger" effect="dark" size="small">流出</el-tag>
            </template>
          </el-table-column>
          <el-table-column label="金额" width="130">
            <template #default="{ row }">
              <span :class="row.direction === 1 ? 'flow-in' : 'flow-out'" style="font-weight: 600;">
                {{ row.direction === 1 ? '+' : '-' }}¥ {{ formatMoney(row.amount) }}
              </span>
            </template>
          </el-table-column>
          <el-table-column label="变动后余额" width="140">
            <template #default="{ row }">
              <span style="color: #303133; font-weight: 500;">¥ {{ formatMoney(row.balance) }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="flow_type" label="类型" width="100">
            <template #default="{ row }">
              <el-tag size="small">{{ flowTypeMap[row.flow_type] || row.flow_type }}</el-tag>
            </template>
          </el-table-column>
          <el-table-column prop="related_type" label="关联类型" width="100" />
          <el-table-column prop="related_id" label="关联ID" width="90" />
          <el-table-column prop="operator" label="操作人" width="100" />
          <el-table-column prop="created_at" label="创建时间" width="160" />
          <el-table-column label="操作" width="240" fixed="right">
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

      <el-drawer v-model="detailDrawerVisible" title="资金流水详情" size="520px" destroy-on-close @close="closeDetailDrawer">
        <div v-if="detailData">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <div class="page-title" style="font-size: 16px;">基本信息</div>
            <el-tag :type="detailData.status_tag_type" size="large">{{ detailData.status_label }}</el-tag>
          </div>
          
          <el-descriptions :column="1" border size="small">
            <el-descriptions-item label="流水号">
              <code style="font-size: 11px; background: #f5f7fa; padding: 2px 4px; border-radius: 3px;">{{ detailData.flow_no }}</code>
            </el-descriptions-item>
            <el-descriptions-item label="状态说明">{{ detailData.status_description }}</el-descriptions-item>
            <el-descriptions-item label="流水类型">
              <el-tag size="small">{{ flowTypeMap[detailData.flow_type] || detailData.flow_type }}</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="资金方向">
              <el-tag v-if="detailData.direction === 1" type="success" effect="dark" size="small">流入</el-tag>
              <el-tag v-else type="danger" effect="dark" size="small">流出</el-tag>
            </el-descriptions-item>
            <el-descriptions-item label="变动金额">
              <span :class="detailData.direction === 1 ? 'flow-in' : 'flow-out'" style="font-size: 20px; font-weight: 700;">
                {{ detailData.direction === 1 ? '+' : '-' }}¥ {{ formatMoney(detailData.amount) }}
              </span>
            </el-descriptions-item>
            <el-descriptions-item label="变动后余额">
              <span style="font-size: 16px; font-weight: 600; color: #303133;">¥ {{ formatMoney(detailData.balance) }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="关联类型">{{ detailData.related_type || '-' }}</el-descriptions-item>
            <el-descriptions-item label="关联ID">{{ detailData.related_id || '-' }}</el-descriptions-item>
            <el-descriptions-item label="关联单号">{{ detailData.related_no || '-' }}</el-descriptions-item>
            <el-descriptions-item label="操作人">{{ detailData.operator || '-' }}</el-descriptions-item>
            <el-descriptions-item label="备注">{{ detailData.remark || '-' }}</el-descriptions-item>
            <el-descriptions-item label="创建时间">{{ detailData.created_at }}</el-descriptions-item>
          </el-descriptions>

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

      <el-dialog v-model="statusDialogVisible" title="变更状态" width="480px" destroy-on-close @close="closeStatusDialog">
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

      <el-dialog v-model="remarkDialogVisible" title="添加备注" width="420px" destroy-on-close @close="closeRemarkDialog">
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
      statusTypes: {},
      stats: { current_balance: 0, total_inflow: 0, total_outflow: 0, total_count: 0 },
      search: { status: null, flow_type: '', direction: null, keyword: '', min_amount: null, max_amount: null },
      pagination: { page: 1, per_page: 20, total: 0 },
      flowTypeMap: {
        recharge: '充值', withdraw: '提现', withholding: '预扣',
        refund: '退款', adjustment: '调账', other: '其他'
      },
      detailDrawerVisible: false,
      statusDialogVisible: false,
      remarkDialogVisible: false,
      detailData: null,
      selectedRow: null,
      statusForm: { status: null, operator: 'admin', remark: '' },
      availableStatuses: [],
      statusChanging: false,
      statusChangeImpact: '',
      remarkForm: { remark: '', operator: 'admin' },
      remarkAdding: false,
    };
  },
  watch: {
    'statusForm.status'(newVal) {
      if (newVal !== null && newVal !== undefined && this.selectedRow) {
        const oldStatus = this.selectedRow.status;
        const amount = this.selectedRow.amount;
        const direction = this.selectedRow.direction;
        
        if (oldStatus === 1 && newVal !== 1) {
          if (direction === 1) {
            this.statusChangeImpact = `此操作将撤销流入 ¥${this.formatMoney(amount)}，并同步调整后续所有流水的余额。`;
          } else {
            this.statusChangeImpact = `此操作将退回流出 ¥${this.formatMoney(amount)}，并同步调整后续所有流水的余额。`;
          }
        } else if (oldStatus !== 1 && newVal === 1) {
          if (direction === 1) {
            this.statusChangeImpact = `此操作将确认流入 ¥${this.formatMoney(amount)}，并同步调整后续所有流水的余额。`;
          } else {
            this.statusChangeImpact = `此操作将确认流出 ¥${this.formatMoney(amount)}，并同步调整后续所有流水的余额。`;
          }
        } else {
          this.statusChangeImpact = '此操作将更新状态并记录操作日志，但不影响账户余额。';
        }
      } else {
        this.statusChangeImpact = '';
      }
    }
  },
  mounted() {
    this.loadList();
    this.loadStats();
    this.loadTypes();
  },
  methods: {
    async loadTypes() {
      try {
        const res = await api.fundflow.types();
        if (res && res.code === 0 && res.data.status_types) {
          this.statusTypes = res.data.status_types;
        }
      } catch (e) {
        console.error('加载类型失败:', e);
      }
    },
    async loadStats() {
      try {
        const res = await api.fundflow.stats();
        if (res && res.code === 0) {
          this.stats = res.data;
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
        if (this.search.flow_type) params.flow_type = this.search.flow_type;
        if (this.search.direction !== null) params.direction = this.search.direction;
        if (this.search.keyword) params.keyword = this.search.keyword;
        if (this.search.min_amount !== null) params.min_amount = this.search.min_amount;
        if (this.search.max_amount !== null) params.max_amount = this.search.max_amount;
        const res = await api.fundflow.list(params);
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
      this.search = { status: null, flow_type: '', direction: null, keyword: '', min_amount: null, max_amount: null };
      this.pagination.page = 1;
      this.loadList();
      this.loadStats();
    },
    async reconcileBalance() {
      try {
        await ElementPlus.ElMessageBox.confirm(
          '余额校准将重新计算所有流水的余额。确定要继续吗？',
          '确认操作',
          { type: 'warning' }
        );
        ElementPlus.ElMessage.info('余额校准功能请在后台执行');
      } catch (e) {}
    },
    async showDetail(row) {
      try {
        const res = await api.fundflow.detail(row.id);
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
        1: [4],
        2: [0, 3],
        3: [],
        4: []
      };
      const labels = { 0: '待处理', 1: '已完成', 2: '失败', 3: '已取消', 4: '已冲正' };
      const descs = { 
        0: '资金变动待确认',
        1: '资金变动已生效',
        2: '资金变动处理失败',
        3: '资金变动已取消',
        4: '原流水已被冲正撤销'
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
        const res = await api.fundflow.changeStatus(this.selectedRow.id, {
          status: this.statusForm.status,
          operator: this.statusForm.operator,
          remark: this.statusForm.remark,
        });
        if (res && res.code === 0) {
          ElementPlus.ElMessage.success('状态变更成功');
          this.statusDialogVisible = false;
          await this.loadList();
          await this.loadStats();
          if (this.detailDrawerVisible) {
            await this.showDetail(this.selectedRow);
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
    closeStatusDialog() {
      this.statusDialogVisible = false;
      this.statusForm = { status: null, operator: 'admin', remark: '' };
      this.statusChangeImpact = '';
    },
    closeRemarkDialog() {
      this.remarkDialogVisible = false;
      this.remarkForm = { remark: '', operator: 'admin' };
    },
    closeDetailDrawer() {
      this.detailDrawerVisible = false;
      this.detailData = null;
      this.selectedRow = null;
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
        const res = await api.fundflow.addRemark(this.selectedRow.id, {
          remark: this.remarkForm.remark,
          operator: this.remarkForm.operator,
        });
        if (res && res.code === 0) {
          ElementPlus.ElMessage.success('备注添加成功');
          this.remarkDialogVisible = false;
          await this.loadList();
          if (this.detailDrawerVisible) {
            await this.showDetail(this.selectedRow);
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
