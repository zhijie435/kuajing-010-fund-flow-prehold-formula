const { createApp, ref, computed } = Vue;

const app = createApp({
  setup() {
    const menus = [
      { key: 'dashboard', label: '数据概览', icon: 'DataAnalysis' },
      { key: 'formula', label: '预扣公式管理', icon: 'Calculator' },
      { key: 'withholding', label: '预扣明细管理', icon: 'Tickets' },
      { key: 'fundflow', label: '资金流水管理', icon: 'Wallet' },
    ];

    const currentMenu = ref('withholding');

    const currentMenuLabel = computed(() => {
      const menu = menus.find(m => m.key === currentMenu.value);
      return menu ? menu.label : '';
    });

    return {
      menus,
      currentMenu,
      currentMenuLabel,
    };
  },
});

app.component('withholding-view', WithholdingView);
app.component('fundflow-view', FundFlowView);

if (typeof FormulaView !== 'undefined') {
  app.component('formula-view', FormulaView);
} else {
  app.component('formula-view', {
    template: `
      <div class="page-container">
        <div class="page-header">
          <div class="page-title">预扣公式管理</div>
        </div>
        <el-alert title="模块加载中" type="info" :closable="false">
          <template #default>
            请确认 formula.js 文件已正确加载。
          </template>
        </el-alert>
      </div>
    `
  });
}

if (typeof DashboardView !== 'undefined') {
  app.component('dashboard-view', DashboardView);
} else {
  app.component('dashboard-view', {
    template: `
      <div class="page-container">
        <div class="page-header">
          <div class="page-title">数据概览</div>
        </div>
        <el-alert title="模块加载中" type="info" :closable="false">
          <template #default>
            请确认 dashboard.js 文件已正确加载。
          </template>
        </el-alert>
      </div>
    `
  });
}

for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
  app.component(key, component);
}

app.use(ElementPlus);
app.mount('#app');
