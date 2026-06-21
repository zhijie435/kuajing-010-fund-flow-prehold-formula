const DashboardView = {
  template: `
    <div>
      <div class="stats-cards">
        <div class="stat-card blue">
          <div class="label">
            <el-icon><Operation /></el-icon>
            启用公式数
          </div>
          <div class="value">{{ cards.formula_count || 0 }}</div>
        </div>
        <div class="stat-card orange">
          <div class="label">
            <el-icon><Tickets /></el-icon>
            预扣明细数
          </div>
          <div class="value">{{ cards.detail_count || 0 }}</div>
        </div>
        <div class="stat-card green">
          <div class="label">
            <el-icon><Wallet /></el-icon>
            资金流水数
          </div>
          <div class="value">{{ cards.flow_count || 0 }}</div>
        </div>
        <div class="stat-card red">
          <div class="label">
            <el-icon><Coin /></el-icon>
            当前账户余额
          </div>
          <div class="value">¥ {{ formatMoney(cards.current_balance) }}</div>
        </div>
      </div>

      <div class="stats-cards" style="grid-template-columns: repeat(3, 1fr);">
        <div class="stat-card">
          <div class="label">今日预扣金额</div>
          <div class="value" style="color: #e6a23c;">¥ {{ formatMoney(today.withholding_amount) }}</div>
        </div>
        <div class="stat-card">
          <div class="label">今日资金流入</div>
          <div class="value" style="color: #67c23a;">¥ {{ formatMoney(today.fund_in) }}</div>
        </div>
        <div class="stat-card">
          <div class="label">今日资金流出</div>
          <div class="value" style="color: #f56c6c;">¥ {{ formatMoney(today.fund_out) }}</div>
        </div>
      </div>

      <div class="two-col">
        <div class="section-card">
          <div class="section-title">
            <el-icon style="margin-right: 6px; color: #409eff;"><Tickets /></el-icon>
            最近预扣明细
          </div>
          <el-table :data="recentDetails" stripe size="small" style="width: 100%">
            <el-table-column prop="id" label="ID" width="60" />
            <el-table-column prop="formula_name" label="公式名称" width="140" />
            <el-table-column prop="formula_code" label="编码" width="140">
              <template #default="{ row }">
                <span class="formula-badge">{{ row.formula_code }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="result" label="预扣金额" width="120">
              <template #default="{ row }">
                <span style="color: #f56c6c; font-weight: 600;">¥ {{ formatMoney(row.result) }}</span>
              </template>
            </el-table-column>
            <el-table-column prop="order_no" label="订单号" width="140" show-overflow-tooltip />
            <el-table-column prop="created_at" label="时间" width="160" />
          </el-table>
          <div v-if="recentDetails.length === 0" style="text-align: center; padding: 40px; color: #909399;">
            暂无数据
          </div>
        </div>

        <div class="section-card">
          <div class="section-title">
            <el-icon style="margin-right: 6px; color: #67c23a;"><Wallet /></el-icon>
            最近资金流水
          </div>
          <el-table :data="recentFlows" stripe size="small" style="width: 100%">
            <el-table-column prop="flow_no" label="流水号" width="180" show-overflow-tooltip />
            <el-table-column prop="flow_type" label="类型" width="80">
              <template #default="{ row }">
                <el-tag size="small" :type="flowTypeTag(row.flow_type)">
                  {{ flowTypeLabel(row.flow_type) }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column prop="amount" label="金额" width="120">
              <template #default="{ row }">
                <span :class="row.direction === 1 ? 'flow-in' : 'flow-out'">
                  {{ row.direction === 1 ? '+' : '-' }}¥ {{ formatMoney(row.amount) }}
                </span>
              </template>
            </el-table-column>
            <el-table-column prop="balance" label="余额" width="120">
              <template #default="{ row }">¥ {{ formatMoney(row.balance) }}</template>
            </el-table-column>
            <el-table-column prop="created_at" label="时间" width="160" />
          </el-table>
          <div v-if="recentFlows.length === 0" style="text-align: center; padding: 40px; color: #909399;">
            暂无数据
          </div>
        </div>
      </div>

      <div class="section-card" style="margin-top: 20px;">
        <div class="section-title">
          <el-icon style="margin-right: 6px; color: #e6a23c;"><DataLine /></el-icon>
          公式使用统计 TOP 5
        </div>
        <el-table :data="formulaStats" stripe size="small" style="width: 100%">
          <el-table-column prop="name" label="公式名称" width="200" />
          <el-table-column prop="code" label="编码" width="180">
            <template #default="{ row }">
              <span class="formula-badge">{{ row.code }}</span>
            </template>
          </el-table-column>
          <el-table-column prop="usage_count" label="使用次数" width="120" />
          <el-table-column prop="total_amount" label="累计预扣金额">
            <template #default="{ row }">
              <span style="color: #f56c6c; font-weight: 600;">¥ {{ formatMoney(row.total_amount) }}</span>
            </template>
          </el-table-column>
        </el-table>
        <div v-if="formulaStats.length === 0" style="text-align: center; padding: 40px; color: #909399;">
          暂无数据
        </div>
      </div>
    </div>
  `,
  data() {
    return {
      cards: {},
      today: {},
      recentDetails: [],
      recentFlows: [],
      formulaStats: [],
    };
  },
  mounted() {
    this.loadData();
  },
  methods: {
    async loadData() {
      try {
        const res = await api.dashboard.index();
        if (res && res.code === 0) {
          this.cards = res.data.cards || {};
          this.today = res.data.today || {};
          this.recentDetails = res.data.recent_details || [];
          this.recentFlows = res.data.recent_flows || [];
          this.formulaStats = res.data.formula_stats || [];
        } else if (res && res.message) {
          ElementPlus.ElMessage.error('加载失败: ' + res.message);
        } else {
          ElementPlus.ElMessage.error('加载失败：网络或服务器异常');
        }
      } catch (e) {
        ElementPlus.ElMessage.error('加载失败：' + (e.message || '未知错误'));
      }
    },
    formatMoney(v) {
      return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    flowTypeLabel(t) {
      const map = { withholding: '预扣', refund: '退款', settlement: '结算', adjust: '调整' };
      return map[t] || t;
    },
    flowTypeTag(t) {
      const map = { withholding: 'warning', refund: 'danger', settlement: 'success', adjust: 'info' };
      return map[t] || '';
    },
  },
};
