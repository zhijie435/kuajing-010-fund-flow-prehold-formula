const FormulaView = {
  template: `
    <div class="page-container">
      <div class="page-header">
        <div>
          <div class="page-title">预扣公式配置</div>
          <div style="color: #909399; font-size: 13px; margin-top: 4px;">配置和管理预扣金额计算公式，支持自定义变量和表达式。保存前请务必完成公式校验。</div>
        </div>
        <el-button type="primary" @click="openDialog()">
          <el-icon><Plus /></el-icon>
          新增公式
        </el-button>
      </div>

      <div style="margin-bottom: 16px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
        <el-alert
          v-if="hasValidationWarning"
          title="校验提示"
          type="warning"
          :closable="false"
          style="flex: 1; min-width: 300px;">
          <template #default>
            <div style="font-size: 12px;">
              新增或编辑公式后，系统会自动校验表达式。请确认提示为「公式验证通过」后再保存。
            </div>
          </template>
        </el-alert>
        <el-input v-model="search.keyword" placeholder="搜索公式名称/编码" clearable style="width: 260px;" @keyup.enter="loadList">
          <template #prefix><el-icon><Search /></el-icon></template>
        </el-input>
        <el-select v-model="search.status" placeholder="状态" clearable style="width: 140px;">
          <el-option label="启用" :value="1" />
          <el-option label="停用" :value="0" />
        </el-select>
        <el-button type="primary" plain @click="loadList">查询</el-button>
        <el-button @click="resetSearch">重置</el-button>
      </div>

      <el-table :data="list" stripe v-loading="loading">
        <el-table-column prop="id" label="ID" width="60" />
        <el-table-column prop="name" label="公式名称" width="160" />
        <el-table-column prop="code" label="编码" width="180">
          <template #default="{ row }">
            <span class="formula-badge">{{ row.code }}</span>
          </template>
        </el-table-column>
        <el-table-column prop="formula" label="公式表达式" min-width="300" show-overflow-tooltip>
          <template #default="{ row }">
            <code style="background: #f5f7fa; padding: 2px 6px; border-radius: 4px; color: #409eff;">{{ row.formula }}</code>
          </template>
        </el-table-column>
        <el-table-column label="变量" width="240">
          <template #default="{ row }">
            <span v-for="v in row.variables" :key="v.name" class="variable-tag">
              {{ v.label }} ({{ v.name }})
            </span>
            <span v-if="!row.variables || row.variables.length === 0" style="color: #c0c4cc;">无</span>
          </template>
        </el-table-column>
        <el-table-column prop="status" label="状态" width="80">
          <template #default="{ row }">
            <el-switch
              v-model="row.status"
              :active-value="1"
              :inactive-value="0"
              @change="toggleStatus(row)"
            />
          </template>
        </el-table-column>
        <el-table-column prop="description" label="描述" min-width="160" show-overflow-tooltip />
        <el-table-column prop="created_at" label="创建时间" width="160" />
        <el-table-column label="操作" width="200" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" size="small" @click="openTry(row)">试算</el-button>
            <el-button link type="primary" size="small" @click="openDialog(row)">编辑</el-button>
            <el-popconfirm title="确认删除该公式？删除后无法恢复。" @confirm="removeItem(row)">
              <template #reference>
                <el-button link type="danger" size="small">删除</el-button>
              </template>
            </el-popconfirm>
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

      <el-dialog v-model="dialogVisible" :title="isEdit ? '编辑公式' : '新增公式'" width="760px" destroy-on-close @close="dialogClose">
        <el-form :model="form" :rules="rules" ref="formRef" label-width="110px">
          <el-alert
            title="校验说明"
            type="info"
            :closable="false"
            style="margin-bottom: 20px;">
            <template #default>
              <div style="font-size: 12px; line-height: 1.6;">
                ① 公式编码必须以大写字母开头，只能包含大写字母、数字、下划线（3-100位）<br/>
                ② 变量名必须以小写字母开头，只能包含字母、数字、下划线<br/>
                ③ 公式支持 + - * / () 和三目运算 condition ? a : b，不支持其他函数<br/>
                ④ 请在「公式表达式」输入框失焦后查看下方的实时校验提示
              </div>
            </template>
          </el-alert>

          <el-form-item label="公式名称" prop="name">
            <el-input v-model="form.name" placeholder="请输入公式名称，如：订单金额比例预扣" maxlength="200" show-word-limit />
          </el-form-item>
          <el-form-item label="公式编码" prop="code">
            <el-input v-model="form.code" placeholder="如：ORDER_AMOUNT_RATE，唯一标识" :disabled="isEdit" maxlength="100" show-word-limit />
            <div v-if="codeError" style="color: #f56c6c; font-size: 12px; margin-top: 4px;">
              {{ codeError }}
            </div>
            <div v-else-if="form.code && !isEdit" style="color: #67c23a; font-size: 12px; margin-top: 4px;">
              编码格式正确
            </div>
          </el-form-item>
          <el-form-item label="公式表达式" prop="formula">
            <el-input
              v-model="form.formula"
              type="textarea"
              :rows="3"
              placeholder="如：order_amount * rate，支持 + - * / () 及三目运算 a ? b : c"
              @blur="validateFormulaInput"
              @input="onFormulaInput"
            />
            <div v-if="validateResult.errors && validateResult.errors.length > 0" style="margin-top: 8px;">
              <el-alert
                :title="'公式验证失败（' + validateResult.errors.length + ' 项错误）'"
                type="error"
                :closable="false">
                <ul style="padding-left: 20px; margin: 0;">
                  <li v-for="(err, idx) in validateResult.errors" :key="idx" style="font-size: 12px; line-height: 1.8;">
                    {{ err }}
                  </li>
                </ul>
              </el-alert>
            </div>
            <div v-else-if="validateResult.valid && validateResult.extracted_variables && validateResult.extracted_variables.length > 0"
                 style="margin-top: 8px;">
              <el-alert
                title="公式验证通过"
                type="success"
                :closable="false">
                <template #default>
                  <div style="font-size: 12px;">
                    检测到变量:
                    <el-tag v-for="v in validateResult.extracted_variables" :key="v" size="small" style="margin-left: 6px;">
                      {{ v }}
                    </el-tag>
                    <span style="margin-left: 12px; color: #909399;">请确认下方「变量配置」已包含以上所有变量</span>
                  </div>
                </template>
              </el-alert>
            </div>
            <div v-else-if="form.formula && validateResult.valid"
                 style="color: #67c23a; font-size: 12px; margin-top: 4px;">
              公式格式正确（未检测到变量）
            </div>
          </el-form-item>

          <el-form-item label="变量配置" prop="variables">
            <div style="width: 100%;">
              <div v-for="(v, idx) in form.variables" :key="idx" style="display: flex; gap: 8px; margin-bottom: 10px;">
                <el-input v-model="v.name" placeholder="变量名(英文小写开头)" style="flex: 1;" @blur="validateVariable(v, idx)" />
                <el-input v-model="v.label" placeholder="显示标签" style="flex: 1;" />
                <el-input-number v-model="v.default" :precision="4" :step="0.01" placeholder="默认值" style="width: 140px;" />
                <el-select v-model="v.type" style="width: 100px;">
                  <el-option label="数字" value="number" />
                </el-select>
                <el-button type="danger" link @click="removeVariable(idx)">删除</el-button>
              </div>
              <div v-if="variableErrors.length > 0" style="margin-bottom: 8px;">
                <el-alert
                  :title="'变量配置有误（' + variableErrors.length + ' 项）'"
                  type="error"
                  :closable="false">
                  <ul style="padding-left: 20px; margin: 0;">
                    <li v-for="(err, idx) in variableErrors" :key="idx" style="font-size: 12px; line-height: 1.8;">
                      {{ err }}
                    </li>
                  </ul>
                </el-alert>
              </div>
              <div style="display: flex; gap: 12px; margin-top: 8px;">
                <el-button type="primary" link size="small" @click="addVariable">
                  <el-icon><Plus /></el-icon> 添加变量
                </el-button>
                <el-button size="small" @click="autoExtractVars" :disabled="!form.formula">
                  <el-icon><Refresh /></el-icon> 从公式自动提取变量
                </el-button>
                <span style="color: #909399; font-size: 12px; align-self: center;">
                  已配置 {{ form.variables.length }} 个变量
                </span>
              </div>
            </div>
          </el-form-item>

          <el-form-item label="状态" prop="status">
            <el-switch v-model="form.status" :active-value="1" :inactive-value="0" active-text="启用" inactive-text="停用" />
          </el-form-item>
          <el-form-item label="描述">
            <el-input v-model="form.description" type="textarea" :rows="2" placeholder="公式说明（可选）" maxlength="500" show-word-limit />
          </el-form-item>
        </el-form>
        <template #footer>
          <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
            <div>
              <el-tag v-if="canSave" type="success" effect="plain">
                <el-icon><Check /></el-icon> 校验通过，可以保存
              </el-tag>
              <el-tag v-else type="danger" effect="plain">
                <el-icon><Close /></el-icon> 存在校验错误，无法保存
              </el-tag>
            </div>
            <div>
              <el-button @click="dialogVisible = false">取消</el-button>
              <el-button @click="doValidate" type="info">再次校验</el-button>
              <el-button type="primary" @click="save" :disabled="!canSave">
                {{ isEdit ? '保存修改' : '创建公式' }}
              </el-button>
            </div>
          </div>
        </template>
      </el-dialog>

      <el-dialog v-model="tryDialogVisible" title="公式试算" width="600px" destroy-on-close>
        <div v-if="tryFormula">
          <el-alert title="试算说明" type="info" :closable="false" style="margin-bottom: 16px;">
            <template #default>
              <div style="font-size: 12px;">
                「预览计算」仅显示结果不记录流水；「执行预扣」会同时写入预扣明细和资金流水。
              </div>
            </template>
          </el-alert>
          <div style="margin-bottom: 16px;">
            <strong>{{ tryFormula.name }}</strong>
            <span class="formula-badge" style="margin-left: 8px;">{{ tryFormula.code }}</span>
            <div style="margin-top: 8px; color: #606266;">
              表达式：<code style="background: #f5f7fa; padding: 2px 6px; border-radius: 4px;">{{ tryFormula.formula }}</code>
            </div>
          </div>
          <div class="variables-list">
            <div v-for="v in tryFormula.variables" :key="v.name" class="variable-row">
              <span class="variable-label">{{ v.label }} ({{ v.name }})</span>
              <el-input-number
                v-model="tryVariables[v.name]"
                :precision="4"
                :step="0.01"
                class="variable-input"
                style="width: 100%;"
              />
            </div>
          </div>

          <div style="margin-top: 20px; display: flex; gap: 12px; align-items: center;">
            <el-button type="primary" @click="doPreview" :loading="previewLoading">
              <el-icon><Calculator /></el-icon>
              预览计算
            </el-button>
            <el-button type="success" @click="doCalculate" :loading="calcLoading">
              <el-icon><Check /></el-icon>
              执行预扣（记录流水）
            </el-button>
          </div>

          <div v-if="previewResult" style="margin-top: 20px;">
            <div class="result-preview">
              <div style="font-size: 14px; opacity: 0.9;">{{ previewResult.calculated_at }}</div>
              <div class="amount">¥ {{ formatMoney(previewResult.result) }}</div>
              <div class="formula">{{ tryFormula.formula }}</div>
              <div style="margin-top: 8px; font-size: 12px; opacity: 0.85;">
                代入值:
                <span v-for="(val, key) in previewResult.variables" :key="key" style="margin: 0 4px;">
                  {{ key }}={{ val }}
                </span>
              </div>
              <div v-if="previewResult.detail_id" style="margin-top: 8px; font-size: 13px;">
                <el-tag type="success" effect="dark">已记录 明细ID: {{ previewResult.detail_id }}</el-tag>
              </div>
            </div>
          </div>

          <div v-if="tryError" style="margin-top: 20px;">
            <el-alert :title="tryError" type="error" :closable="false" />
          </div>
        </div>
      </el-dialog>
    </div>
  `,
  data() {
    return {
      loading: false,
      list: [],
      search: { keyword: '', status: null },
      pagination: { page: 1, per_page: 20, total: 0 },
      dialogVisible: false,
      tryDialogVisible: false,
      isEdit: false,
      formRef: null,
      form: {
        id: null, name: '', code: '', formula: '', description: '', variables: [], status: 1
      },
      rules: {
        name: [
          { required: true, message: '请输入公式名称', trigger: 'blur' },
          { min: 2, max: 200, message: '长度在 2 到 200 个字符', trigger: 'blur' }
        ],
        code: [
          { required: true, message: '请输入公式编码', trigger: 'blur' },
          { pattern: /^[A-Z][A-Z0-9_]{2,99}$/, message: '编码格式错误', trigger: 'blur' }
        ],
        formula: [
          { required: true, message: '请输入公式表达式', trigger: 'blur' }
        ],
      },
      validateResult: { valid: false, errors: [], extracted_variables: [] },
      variableErrors: [],
      tryFormula: null,
      tryVariables: {},
      previewResult: null,
      previewLoading: false,
      calcLoading: false,
      tryError: '',
    };
  },
  computed: {
    hasValidationWarning() {
      return true;
    },
    codeError() {
      if (!this.form.code) return '';
      if (!/^[A-Z][A-Z0-9_]{2,99}$/.test(this.form.code)) {
        return '编码必须以大写字母开头，只能包含大写字母、数字、下划线，长度3-100位';
      }
      return '';
    },
    canSave() {
      if (!this.form.name || !this.form.formula) return false;
      if (!this.form.code && !this.isEdit) return false;
      if (this.codeError) return false;
      if (this.validateResult.errors && this.validateResult.errors.length > 0) return false;
      if (this.variableErrors.length > 0) return false;
      if (this.form.variables.length > 0) {
        for (const v of this.form.variables) {
          if (!v.name || !v.label) return false;
        }
      }
      if (this.validateResult.extracted_variables && this.validateResult.extracted_variables.length > 0) {
        const varNames = this.form.variables.map(v => v.name);
        const missing = this.validateResult.extracted_variables.filter(v => !varNames.includes(v));
        if (missing.length > 0) return false;
      }
      return true;
    },
  },
  mounted() {
    this.loadList();
  },
  methods: {
    async loadList() {
      this.loading = true;
      try {
        const params = { page: this.pagination.page, per_page: this.pagination.per_page };
        if (this.search.keyword) params.keyword = this.search.keyword;
        if (this.search.status !== null && this.search.status !== '') params.status = this.search.status;
        const res = await api.formula.list(params);
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
      this.search = { keyword: '', status: null };
      this.pagination.page = 1;
      this.loadList();
    },
    openDialog(row = null) {
      this.isEdit = !!row;
      this.validateResult = { valid: false, errors: [], extracted_variables: [] };
      this.variableErrors = [];
      this.tryError = '';
      if (row) {
        this.form = {
          id: row.id,
          name: row.name,
          code: row.code,
          formula: row.formula,
          description: row.description || '',
          variables: JSON.parse(JSON.stringify(row.variables || [])),
          status: row.status,
        };
        this.validateFormulaInput();
      } else {
        this.form = {
          id: null, name: '', code: '', formula: '', description: '', variables: [], status: 1
        };
      }
      this.dialogVisible = true;
    },
    dialogClose() {
      this.form = { id: null, name: '', code: '', formula: '', description: '', variables: [], status: 1 };
      this.validateResult = { valid: false, errors: [], extracted_variables: [] };
      this.variableErrors = [];
    },
    addVariable() {
      this.form.variables.push({ name: '', label: '', type: 'number', default: 0 });
    },
    removeVariable(idx) {
      this.form.variables.splice(idx, 1);
      this.validateAllVariables();
    },
    onFormulaInput() {
      this.tryError = '';
    },
    validateVariable(v, idx) {
      if (v.name && !/^[a-z][a-zA-Z0-9_]*$/.test(v.name)) {
        ElementPlus.ElMessage.warning(`第${idx + 1}个变量名格式错误：必须以小写字母开头`);
      }
      this.validateAllVariables();
    },
    validateAllVariables() {
      const errors = [];
      const names = new Set();
      this.form.variables.forEach((v, idx) => {
        if (!v.name) {
          errors.push(`第${idx + 1}个变量缺少变量名`);
        } else if (!/^[a-z][a-zA-Z0-9_]*$/.test(v.name)) {
          errors.push(`第${idx + 1}个变量名「${v.name}」格式错误：必须以小写字母开头，只能包含字母、数字、下划线`);
        } else if (names.has(v.name)) {
          errors.push(`第${idx + 1}个变量名「${v.name}」重复`);
        } else {
          names.add(v.name);
        }
        if (!v.label) {
          errors.push(`第${idx + 1}个变量缺少显示标签`);
        }
      });
      if (this.validateResult.extracted_variables && this.validateResult.extracted_variables.length > 0) {
        const varNames = this.form.variables.map(v => v.name);
        const missing = this.validateResult.extracted_variables.filter(v => !varNames.includes(v));
        if (missing.length > 0) {
          errors.push(`公式中检测到变量 ${missing.join('、')}，但未在变量配置中定义`);
        }
      }
      this.variableErrors = errors;
    },
    async validateFormulaInput() {
      if (!this.form.formula) {
        this.validateResult = { valid: false, errors: ['请先输入公式表达式'], extracted_variables: [] };
        return;
      }
      try {
        const res = await api.formula.validate({ formula: this.form.formula, variables: this.form.variables });
        if (res && res.code === 0) {
          this.validateResult = res.data;
        } else {
          this.validateResult = {
            valid: false,
            errors: res && res.message ? [res.message] : ['校验请求失败'],
            extracted_variables: []
          };
        }
      } catch (e) {
        this.validateResult = {
          valid: false,
          errors: ['校验请求异常：' + (e.message || '网络错误')],
          extracted_variables: []
        };
      }
      this.validateAllVariables();
    },
    async doValidate() {
      this.validateAllVariables();
      await this.validateFormulaInput();
      if (this.canSave) {
        ElementPlus.ElMessage.success('所有校验通过！');
      } else {
        const totalErrors = (this.validateResult.errors?.length || 0) + this.variableErrors.length + (this.codeError ? 1 : 0);
        ElementPlus.ElMessage.error(`存在 ${totalErrors} 项校验错误，请修正后再保存`);
      }
    },
    autoExtractVars() {
      if (!this.form.formula) {
        ElementPlus.ElMessage.warning('请先输入公式表达式');
        return;
      }
      const regex = /\b([a-zA-Z_][a-zA-Z0-9_]*)\b/g;
      const match = this.form.formula.match(regex);
      const keywords = ['true', 'false', 'null', 'and', 'or', 'xor', 'if', 'else'];
      const vars = [...new Set((match || []).filter(v => !keywords.includes(v) && isNaN(Number(v))))];
      const existingNames = this.form.variables.map(v => v.name);
      let added = 0;
      vars.forEach(name => {
        if (!existingNames.includes(name)) {
          this.form.variables.push({ name, label: name, type: 'number', default: 0 });
          added++;
        }
      });
      if (added > 0) {
        ElementPlus.ElMessage.success(`已自动添加 ${added} 个变量`);
      } else {
        ElementPlus.ElMessage.info('未检测到新变量');
      }
      this.validateAllVariables();
    },
    async save() {
      if (!this.canSave) {
        const errors = [];
        if (this.codeError) errors.push(this.codeError);
        if (this.validateResult.errors) errors.push(...this.validateResult.errors);
        errors.push(...this.variableErrors);
        ElementPlus.ElMessageBox.alert(
          '<div style="color: #f56c6c;">保存前请先修正以下错误：</div><ul style="padding-left: 20px; margin-top: 10px;"><li>' + errors.join('</li><li>') + '</li></ul>',
          '无法保存',
          { dangerouslyUseHTMLString: true, type: 'error', confirmButtonText: '知道了' }
        );
        return;
      }

      await this.$refs.formRef.validate(async (valid) => {
        if (!valid) {
          ElementPlus.ElMessage.warning('请完善表单必填项');
          return;
        }

        const payload = { ...this.form };
        let res;
        try {
          if (this.isEdit) {
            res = await api.formula.update(this.form.id, payload);
          } else {
            res = await api.formula.create(payload);
          }

          if (res && res.code === 0) {
            ElementPlus.ElMessage.success(this.isEdit ? '更新成功！公式状态已同步刷新。' : '创建成功！');
            this.dialogVisible = false;

            if (res.data && res.data.id) {
              const idx = this.list.findIndex(x => x.id === res.data.id);
              if (idx >= 0) {
                this.list.splice(idx, 1, res.data);
              } else {
                this.list.unshift(res.data);
                this.pagination.total += 1;
              }
            } else {
              this.loadList();
            }
          } else {
            const detailErrors = (res && res.data && res.data.errors) ? res.data.errors : [];
            const msg = res && res.message ? res.message : '保存失败';
            if (detailErrors.length > 0) {
              ElementPlus.ElMessageBox.alert(
                '<div style="color: #f56c6c;">' + msg + '</div><ul style="padding-left: 20px; margin-top: 10px;"><li>' + detailErrors.join('</li><li>') + '</li></ul>',
                '保存失败',
                { dangerouslyUseHTMLString: true, type: 'error', confirmButtonText: '知道了' }
              );
            } else {
              ElementPlus.ElMessage.error(msg);
            }
          }
        } catch (e) {
          ElementPlus.ElMessage.error('保存异常：' + (e.message || '网络错误'));
        }
      });
    },
    async toggleStatus(row) {
      try {
        const res = await api.formula.update(row.id, {
          name: row.name,
          formula: row.formula,
          description: row.description,
          variables: row.variables,
          status: row.status,
        });
        if (res && res.code === 0) {
          ElementPlus.ElMessage.success('状态更新成功，列表已刷新');
          if (res.data && res.data.id) {
            const idx = this.list.findIndex(x => x.id === res.data.id);
            if (idx >= 0) {
              this.list.splice(idx, 1, res.data);
            } else {
              this.loadList();
            }
          } else {
            this.loadList();
          }
        } else {
          ElementPlus.ElMessage.error(res && res.message ? res.message : '状态更新失败');
          row.status = row.status === 1 ? 0 : 1;
        }
      } catch (e) {
        ElementPlus.ElMessage.error('更新异常：' + (e.message || '网络错误'));
        row.status = row.status === 1 ? 0 : 1;
      }
    },
    async removeItem(row) {
      try {
        const res = await api.formula.delete(row.id);
        if (res && res.code === 0) {
          ElementPlus.ElMessage.success('删除成功');
          this.loadList();
        } else {
          ElementPlus.ElMessage.error(res && res.message ? res.message : '删除失败');
        }
      } catch (e) {
        ElementPlus.ElMessage.error('删除异常：' + (e.message || '网络错误'));
      }
    },
    openTry(row) {
      this.tryFormula = JSON.parse(JSON.stringify(row));
      this.tryVariables = {};
      this.tryError = '';
      (row.variables || []).forEach(v => {
        this.tryVariables[v.name] = v.default || 0;
      });
      this.previewResult = null;
      this.tryDialogVisible = true;
    },
    async doPreview() {
      this.tryError = '';
      this.previewLoading = true;
      try {
        const res = await api.withholding.preview({
          formula_code: this.tryFormula.code,
          variables: this.tryVariables
        });
        if (res && res.code === 0) {
          this.previewResult = res.data;
        } else {
          const msg = res && res.message ? res.message : '预览失败';
          const errorCode = res && res.data && res.data.error_code ? res.data.error_code : '';
          const errorDetail = res && res.data && res.data.error_detail
            ? (Array.isArray(res.data.error_detail) ? res.data.error_detail.join(', ') : res.data.error_detail)
            : msg;
          this.tryError = msg;
          await this._showTryPreviewFailureDialog(msg, errorCode, errorDetail);
        }
      } catch (e) {
        const msg = '预览异常：' + (e.message || '网络错误');
        this.tryError = msg;
        await this._showTryPreviewFailureDialog(msg, 'NETWORK_ERROR', e.message || '网络错误');
      } finally {
        this.previewLoading = false;
      }
    },
    async _showTryPreviewFailureDialog(msg, errorCode, errorDetail) {
      const codeTip = errorCode
        ? `<div style="margin-top: 6px; font-size: 12px; color: #909399;">错误代码：${errorCode}</div>`
        : '';
      const detailHtml = errorDetail && errorDetail !== msg
        ? `<div style="margin-top: 10px; padding: 10px; background: #fef0f0; border-radius: 4px; font-size: 12px; color: #f56c6c; font-family: monospace; max-height: 120px; overflow-y: auto;">错误详情：${errorDetail}</div>`
        : '';
      try {
        await ElementPlus.ElMessageBox.alert(
          `<div style="line-height: 1.6;">
            <div style="color: #f56c6c; font-size: 14px; font-weight: 600;">${msg}</div>
            ${codeTip}
            ${detailHtml}
          </div>`,
          '预览失败',
          { dangerouslyUseHTMLString: true, confirmButtonText: '关闭', type: 'error' }
        );
      } catch (e) {}
    },
    async doCalculate() {
      this.tryError = '';
      this.calcLoading = true;
      try {
        const res = await api.withholding.calculate({
          formula_code: this.tryFormula.code,
          variables: this.tryVariables,
          operator: 'admin',
        });
        if (res && res.code === 0) {
          this.previewResult = res.data;
          ElementPlus.ElMessage.success('预扣执行成功，已记录明细和资金流水');
          this.loadList();
          this.tryDialogVisible = false;
        } else {
          const msg = res && res.message ? res.message : '执行失败';
          const rolledBack = res && res.data && res.data.rolled_back;
          const errorCode = res && res.data && res.data.error_code ? res.data.error_code : '';
          const errorDetail = res && res.data && res.data.error_detail ? res.data.error_detail : msg;
          this.tryError = msg;
          await this._showTryCalcFailureDialog(msg, rolledBack, errorCode, errorDetail);
        }
      } catch (e) {
        const msg = '执行异常：' + (e.message || '网络错误');
        this.tryError = msg;
        await this._showTryCalcFailureDialog(msg, true, 'NETWORK_ERROR', e.message || '网络错误');
      } finally {
        this.calcLoading = false;
      }
    },
    async _showTryCalcFailureDialog(msg, rolledBack, errorCode, errorDetail) {
      const rollbackTip = rolledBack
        ? '<div style="color: #e6a23c; margin-top: 8px; font-size: 13px;"><strong>✓ 系统已自动回滚</strong>，未产生任何预扣明细和资金流水记录，数据安全。</div>'
        : '';
      const codeTip = errorCode
        ? `<div style="margin-top: 6px; font-size: 12px; color: #909399;">错误代码：${errorCode}</div>`
        : '';
      const errorHtml = errorDetail && errorDetail !== msg
        ? `<div style="margin-top: 12px; padding: 10px; background: #fef0f0; border-radius: 4px; font-size: 12px; color: #f56c6c; font-family: monospace;">错误详情：${errorDetail}</div>`
        : '';
      try {
        await ElementPlus.ElMessageBox.alert(
          `<div style="line-height: 1.6;">
            <div style="color: #f56c6c; font-size: 14px; font-weight: 600;">${msg}</div>
            ${codeTip}
            ${rollbackTip}
            ${errorHtml}
          </div>`,
          '提交失败',
          {
            dangerouslyUseHTMLString: true,
            confirmButtonText: '关闭',
            showCancelButton: true,
            cancelButtonText: '重试',
            type: 'error',
          }
        );
      } catch (action) {
        if (action === 'cancel') {
          this.doCalculate();
        }
      }
    },
    formatMoney(v) {
      return Number(v || 0).toLocaleString('zh-CN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
  },
};
