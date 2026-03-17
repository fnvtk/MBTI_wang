<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h2>用户数据</h2>
        <p class="subtitle">按企业用户池分类查看和管理用户数据</p>
      </div>
      <div class="header-actions">
        <el-button variant="outline" size="small" @click="exportData">
          <el-icon class="mr-1"><Download /></el-icon>导出数据
        </el-button>
      </div>
    </div>

    <!-- 用户数据概览卡片 -->
    <div class="user-overview-grid">
      <div
        v-for="(card, index) in userCards"
        :key="card.name + (card.enterpriseId ?? '')"
        :class="['user-card', { active: selectedCard === index }]"
        @click="onSelectCard(index)"
      >
        <div class="card-header">
          <div class="card-icon">
            <el-icon v-if="card.type === 'all'"><UserFilled /></el-icon>
            <el-icon v-else-if="card.type === 'individual'"><User /></el-icon>
            <el-icon v-else><OfficeBuilding /></el-icon>
          </div>
          <div class="card-name">{{ card.name }}</div>
        </div>
        <div class="card-stats">
          <div class="stat-main">
            <span class="stat-value-large">{{ card.total }}</span>
            <span class="stat-label-large">人</span>
          </div>
          <div class="stat-sub">
            <span class="stat-active">{{ card.active }}活跃</span>
            <span class="stat-tested">{{ card.tested }}已测试</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 汇总指标 -->
    <div class="summary-metrics-grid">
      <div class="summary-card">
        <div class="summary-label">池内用户总数</div>
        <div class="summary-value">{{ summary.totalUsers }}</div>
      </div>
      <div class="summary-card">
        <div class="summary-label">活跃用户</div>
        <div class="summary-value">
          {{ summary.activeUsers }} <span class="summary-percent">({{ summary.activePercent }}%)</span>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-label">已完成测试</div>
        <div class="summary-value">
          {{ summary.testedUsers }} <span class="summary-percent">({{ summary.testedPercent }}%)</span>
        </div>
      </div>
    </div>

    <!-- MBTI类型分布 -->
    <div class="mbti-distribution-card">
      <div class="mbti-header">
        <el-icon class="mbti-icon"><DataLine /></el-icon>
        <span class="mbti-title">MBTI 类型分布</span>
      </div>
      <div class="mbti-tags">
        <div
          v-for="(type, _index) in mbtiTypes"
          :key="type.type"
          :class="['mbti-tag', { active: selectedMbti === type.type }]"
          @click="onSelectMbti(type.type)"
        >
          {{ type.type }} {{ type.count }}人
        </div>
      </div>
    </div>

    <!-- 搜索栏 -->
    <div class="search-section">
      <el-input
        v-model="searchTerm"
        placeholder="搜索昵称、手机号、地区、MBTI..."
        clearable
        class="search-input"
        @clear="loadUsers"
        @keyup.enter="loadUsers"
      >
        <template #prefix>
          <el-icon><Search /></el-icon>
        </template>
      </el-input>
      <el-button type="primary" @click="loadUsers">搜索</el-button>
    </div>

    <!-- 用户列表表格 -->
    <div class="table-card">
      <el-table :data="users" style="width: 100%" v-loading="loading" class="user-table">
        <el-table-column label="用户信息" min-width="220">
          <template #default="{ row }">
            <div class="user-info-cell">
              <img
                v-if="row.avatar"
                :src="row.avatar"
                class="user-avatar user-avatar-img"
                referrerpolicy="no-referrer"
              />
              <div
                v-else
                class="user-avatar user-avatar-letter"
                :style="{ backgroundColor: avatarBgColor(row) }"
              >
                {{ avatarLetter(row) }}
              </div>
              <div class="user-details">
                <div class="username">{{ row.username || '未设置昵称' }}</div>
                <div class="openid-line">{{ row.openid || '—' }}</div>
                <div class="phone-line">{{ row.phone}}</div>
              </div>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="人脸分析" width="120" align="center">
          <template #default="{ row }">
            <div class="test-results">
              <template v-if="row.faceMbtiType || row.faceDiscType || row.facePdpType">
                <el-tag
                  v-if="row.faceMbtiType"
                  size="small"
                  class="result-tag"
                  type="success"
                  @click.stop="handleClickTestTag(row, 'face')"
                >
                  {{ row.faceMbtiType }}
                </el-tag>
                <el-tag
                  v-if="row.faceDiscType"
                  size="small"
                  class="result-tag"
                  type="info"
                  @click.stop="handleClickTestTag(row, 'face')"
                >
                  {{ row.faceDiscType }}
                </el-tag>
                <el-tag
                  v-if="row.facePdpType"
                  size="small"
                  class="result-tag"
                  type="warning"
                  @click.stop="handleClickTestTag(row, 'face')"
                >
                  {{ row.facePdpType }}
                </el-tag>
              </template>
              <span v-else class="no-test">—</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="MBTI" width="120" align="center">
          <template #default="{ row }">
            <div class="test-results">
              <template v-if="row.mbtiType">
                <el-tag size="small" class="result-tag" @click.stop="handleClickTestTag(row, 'mbti')">
                  {{ row.mbtiType }}
                </el-tag>
              </template>
              <span v-else class="no-test">—</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="PDP" width="120" align="center">
          <template #default="{ row }">
            <div class="test-results">
              <template v-if="row.pdpType">
                <el-tag
                  size="small"
                  class="result-tag"
                  type="warning"
                  @click.stop="handleClickTestTag(row, 'pdp')"
                >
                  {{ row.pdpType }}
                </el-tag>
              </template>
              <span v-else class="no-test">—</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="DISC" width="120" align="center">
          <template #default="{ row }">
            <div class="test-results">
              <template v-if="row.discType">
                <el-tag
                  size="small"
                  class="result-tag"
                  type="info"
                  @click.stop="handleClickTestTag(row, 'disc')"
                >
                  {{ row.discType }}
                </el-tag>
              </template>
              <span v-else class="no-test">—</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="测试次数" width="120" align="center">
          <template #default="{ row }">
            <span class="count-cell">{{ row.testCount ?? 0 }}</span>
          </template>
        </el-table-column>

        <el-table-column label="所属企业" min-width="160">
          <template #default="{ row }">
            <div class="enterprise-cell">
              <el-icon class="enterprise-icon"><OfficeBuilding /></el-icon>
              <span class="enterprise-name">{{ row.enterprise || '个人用户(无企业)' }}</span>
            </div>
          </template>
        </el-table-column>

        <el-table-column label="状态" width="100" align="center">
          <template #default="{ row: _row }">
            <el-tag type="success" size="small">正常</el-tag>
          </template>
        </el-table-column>

        <el-table-column label="注册时间" width="150">
          <template #default="{ row }">
            <span class="time-cell">{{ formatDate(row.createdAt) }}</span>
          </template>
        </el-table-column>

        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-button link @click="handleView(row)">
              <el-icon><View /></el-icon>
              <span>查看</span>
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <div class="empty-state" v-if="users.length === 0 && !loading">
        <span>暂无用户数据</span>
      </div>

      <div class="pagination-container" v-if="total > 0">
        <el-pagination
          v-model:current-page="currentPage"
          :page-size="pageSize"
          :total="total"
          layout="prev, pager, next"
          @current-change="handlePageChange"
        />
      </div>
    </div>

    <!-- 用户详情对话框 -->
    <el-dialog
      v-model="showDetailDialog"
      title="测试记录"
      width="55%"
      destroy-on-close
      v-loading="detailLoading"
    >
      <template v-if="detailUser">
        <div class="detail-section" v-if="testTableData.length">
          <el-table :data="paginatedTests" size="small" max-height="500" class="test-table">
            <el-table-column prop="createdAt" label="时间" width="140">
              <template #default="{ row }">{{ formatDate(row.createdAt) }}</template>
            </el-table-column>
            <el-table-column prop="testType" label="类型" width="120">
              <template #default="{ row }">{{ formatTestType(row.testType) }}</template>
            </el-table-column>
            <el-table-column prop="summary" label="结果摘要" min-width="160" show-overflow-tooltip />
            <el-table-column label="需付费" width="100" align="center">
              <template #default="{ row }">
                <el-tag size="small" :type="row.requiresPayment ? 'warning' : 'info'">
                  {{ row.requiresPayment ? '是' : '否' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="已付费" width="100" align="center">
              <template #default="{ row }">
                <el-tag size="small" :type="row.isPaid ? 'success' : 'info'">
                  {{ row.isPaid ? '是' : '否' }}
                </el-tag>
              </template>
            </el-table-column>
            <el-table-column label="付款金额" width="110" align="center">
              <template #default="{ row }">
                <span class="payment-cell">
                  {{ row.isPaid ? formatAmount(row.paidAmount) : '-' }}
                </span>
              </template>
            </el-table-column>
            <el-table-column label="付款时间" width="140" align="center">
              <template #default="{ row }">
                <span class="time-cell">
                  {{ row.isPaid ? formatDate(row.paidAt) : '-' }}
                </span>
              </template>
            </el-table-column>
            <el-table-column label="操作" width="100">
              <template #default="{ row }">
                <el-button link type="primary" @click="handleViewTest(row)">查看</el-button>
              </template>
            </el-table-column>
          </el-table>
          <div class="test-pagination" v-if="testTableData.length > testPageSize">
            <el-pagination
              v-model:current-page="testPage"
              :page-size="testPageSize"
              :total="testTableData.length"
              layout="prev, pager, next, total"
              @current-change="handleTestPageChange"
            />
          </div>
          </div>
      </template>
    </el-dialog>

    <!-- 单次测试详情对话框 -->
    <el-dialog
      v-model="showTestDetailDialog"
      title="测试详情"
      width="760px"
      destroy-on-close
      v-loading="testDetailLoading"
    >
      <template v-if="currentTest">
        <!-- 通用头部信息 -->
        <div class="detail-section test-detail-header">
          <div class="test-detail-header-main">
            <div class="test-type-tag">{{ formatTestType(currentTest.testType) }}</div>
            <div class="test-type-summary">
              <div class="test-type-title">{{ currentTestSummary || '-' }}</div>
              <div class="test-type-meta">
                <span>测试时间：{{ formatDate(currentTest.createdAt) }}</span>
          </div>
          </div>
          </div>
          </div>

        <!-- MBTI 图文详情 -->
        <div v-if="currentTestType === 'mbti' && mbtiDetail" class="detail-section test-detail-mbti">
          <div class="test-detail-main-card">
            <div class="test-detail-main-left">
              <div class="mbti-type-badge">{{ mbtiDetail.type }}</div>
              <div class="mbti-name">{{ mbtiDetail.name }}</div>
              <p class="mbti-desc">{{ mbtiDetail.description }}</p>
        </div>
          </div>
          <div class="test-detail-grid">
            <div class="test-detail-block" v-if="mbtiDetail.strengths?.length">
              <h4 class="detail-subtitle">性格优势</h4>
              <ul class="tag-list">
                <li v-for="item in mbtiDetail.strengths" :key="item">{{ item }}</li>
              </ul>
            </div>
            <div class="test-detail-block" v-if="mbtiDetail.weaknesses?.length">
              <h4 class="detail-subtitle">潜在短板</h4>
              <ul class="tag-list tag-list-warning">
                <li v-for="item in mbtiDetail.weaknesses" :key="item">{{ item }}</li>
              </ul>
            </div>
            <div class="test-detail-block" v-if="mbtiDetail.careers?.length">
              <h4 class="detail-subtitle">适合职业</h4>
              <ul class="tag-list tag-list-info">
                <li v-for="item in mbtiDetail.careers" :key="item">{{ item }}</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- DISC 图文详情 -->
        <div v-else-if="currentTestType === 'disc' && discDetail" class="detail-section test-detail-disc">
          <div class="test-detail-main-card">
            <div class="test-detail-main-left">
              <div class="disc-type-badge">{{ discDetail.type }}</div>
              <div class="disc-title">{{ discDetail.title }}</div>
              <p class="disc-desc">{{ discDetail.description }}</p>
            </div>
            <div class="test-detail-main-right" v-if="discDetail.percentages">
              <h4 class="detail-subtitle">四维占比</h4>
              <div class="disc-bars">
                <div
                  v-for="key in (['D','I','S','C'] as Array<'D' | 'I' | 'S' | 'C'>)"
                  :key="key"
                  class="disc-bar-row"
                >
                  <span class="disc-bar-label">{{ key }}</span>
                  <div class="disc-bar-track">
                    <div
                      class="disc-bar-fill"
                      :class="'disc-bar-fill-' + key.toLowerCase()"
                      :style="{ width: (discDetail.percentages[key] || 0) + '%' }"
                    ></div>
                  </div>
                  <span class="disc-bar-value">{{ discDetail.percentages[key] || 0 }}%</span>
                </div>
              </div>
            </div>
          </div>
          <div class="test-detail-grid">
            <div class="test-detail-block" v-if="discDetail.strengths?.length">
              <h4 class="detail-subtitle">主要优势</h4>
              <ul class="tag-list">
                <li v-for="item in discDetail.strengths" :key="item">{{ item }}</li>
              </ul>
            </div>
            <div class="test-detail-block" v-if="discDetail.weaknesses?.length">
              <h4 class="detail-subtitle">注意事项</h4>
              <ul class="tag-list tag-list-warning">
                <li v-for="item in discDetail.weaknesses" :key="item">{{ item }}</li>
              </ul>
            </div>
            <div class="test-detail-block" v-if="discDetail.careers?.length">
              <h4 class="detail-subtitle">适合角色 / 职业</h4>
              <ul class="tag-list tag-list-info">
                <li v-for="item in discDetail.careers" :key="item">{{ item }}</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- PDP 图文详情 -->
        <div v-else-if="currentTestType === 'pdp' && pdpDetail" class="detail-section test-detail-pdp">
          <div class="test-detail-main-card">
            <div class="test-detail-main-left">
              <div class="pdp-emoji" v-if="pdpDetail.emoji">{{ pdpDetail.emoji }}</div>
              <div class="pdp-type-line">
                <span class="pdp-type">{{ pdpDetail.type }}</span>
                <span class="pdp-title" v-if="pdpDetail.title">· {{ pdpDetail.title }}</span>
              </div>
              <p class="pdp-desc">{{ pdpDetail.description }}</p>
            </div>
          </div>
          <div class="test-detail-grid">
            <div class="test-detail-block" v-if="pdpDetail.teamRole">
              <h4 class="detail-subtitle">团队角色定位</h4>
              <p class="test-desc">{{ pdpDetail.teamRole }}</p>
            </div>
            <div class="test-detail-block" v-if="pdpDetail.strengths?.length">
              <h4 class="detail-subtitle">核心优势</h4>
              <ul class="tag-list">
                <li v-for="item in pdpDetail.strengths" :key="item">{{ item }}</li>
              </ul>
            </div>
            <div class="test-detail-block" v-if="pdpDetail.weaknesses?.length">
              <h4 class="detail-subtitle">风险点</h4>
              <ul class="tag-list tag-list-warning">
                <li v-for="item in pdpDetail.weaknesses" :key="item">{{ item }}</li>
              </ul>
            </div>
            <div class="test-detail-block" v-if="pdpDetail.careers?.length">
              <h4 class="detail-subtitle">适合职业</h4>
              <ul class="tag-list tag-list-info">
                <li v-for="item in pdpDetail.careers" :key="item">{{ item }}</li>
              </ul>
            </div>
          </div>
        </div>

        <!-- AI 人脸分析 图文详情 -->
        <div v-else-if="(currentTestType === 'face' || currentTestType === 'ai') && faceDetail" class="detail-section test-detail-face">
          <div class="face-layout">
            <div class="face-left" v-if="faceDetail.photos && faceDetail.photos.length">
              <div class="face-photos">
                <el-image
                  v-for="(url, idx) in faceDetail.photos"
                  :key="url + idx"
                  :src="url"
                  fit="cover"
                  :preview-src-list="faceDetail.photos"
                />
              </div>
            </div>
            <div class="face-right">
              <div class="test-detail-grid">
                <div class="test-detail-block" v-if="faceDetail.mbti">
                  <h4 class="detail-subtitle">AI 识别 MBTI</h4>
                  <p class="test-desc">
                    <strong>{{ faceDetail.mbti.type }}</strong>
                    <span v-if="faceDetail.mbti.title"> · {{ faceDetail.mbti.title }}</span>
                  </p>
                </div>
                <div class="test-detail-block" v-if="faceDetail.disc">
                  <h4 class="detail-subtitle">AI 识别 DISC</h4>
                  <p class="test-desc">
                    主类型：<strong>{{ faceDetail.disc.primary }}</strong>
                    <span v-if="faceDetail.disc.secondary">，次类型：{{ faceDetail.disc.secondary }}</span>
                  </p>
                </div>
                <div class="test-detail-block" v-if="faceDetail.pdp">
                  <h4 class="detail-subtitle">AI 识别 PDP</h4>
                  <p class="test-desc">
                    主类型：<strong>{{ faceDetail.pdp.primary }}</strong>
                    <span v-if="faceDetail.pdp.secondary">，次类型：{{ faceDetail.pdp.secondary }}</span>
                  </p>
                </div>
                <div class="test-detail-block" v-if="faceDetail.overview">
                  <h4 class="detail-subtitle">整体气质概览</h4>
                  <p class="test-desc">{{ faceDetail.overview }}</p>
                </div>
                <div class="test-detail-block" v-if="faceDetail.boneAnalysis">
                  <h4 class="detail-subtitle">骨相特征</h4>
                  <p class="test-desc">{{ faceDetail.boneAnalysis }}</p>
                </div>
                <div class="test-detail-block" v-if="faceDetail.personalitySummary">
                  <h4 class="detail-subtitle">性格总结</h4>
                  <p class="test-desc">{{ faceDetail.personalitySummary }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- 简历综合分析 v2 结构化详情 -->
        <template v-else-if="currentTestType === 'resume' && resumeDetail">
          <div v-if="resumeDetail.bossView" class="detail-section resume-boss-section">
            <div class="resume-headline">{{ resumeDetail.bossView.headline }}</div>
            <div class="resume-metrics-row">
              <div
                v-for="m in (resumeDetail.bossView.metrics || [])"
                :key="m.label"
                :class="['resume-metric-card', 'metric-' + (m.level || 'medium')]"
              >
                <div class="resume-metric-value">{{ m.value }}</div>
                <div class="resume-metric-label">{{ m.label }}</div>
              </div>
            </div>
            <div v-if="resumeDetail.bossView.costInsight" class="resume-cost-insight">
              {{ resumeDetail.bossView.costInsight }}
            </div>
          </div>

          <div v-if="resumeDetail.portrait" class="detail-section">
            <div class="test-detail-grid">
              <div class="test-detail-block" v-if="resumeDetail.portrait.coreStrengths?.length">
                <h4 class="detail-subtitle">核心优势</h4>
                <ul class="tag-list">
                  <li v-for="s in resumeDetail.portrait.coreStrengths" :key="s">{{ s }}</li>
                </ul>
              </div>
              <div class="test-detail-block" v-if="resumeDetail.portrait.coreRisks?.length">
                <h4 class="detail-subtitle">潜在风险</h4>
                <ul class="tag-list tag-list-warning">
                  <li v-for="r in resumeDetail.portrait.coreRisks" :key="r">{{ r }}</li>
                </ul>
              </div>
              <div class="test-detail-block" v-if="resumeDetail.portrait.workStyle">
                <h4 class="detail-subtitle">工作风格</h4>
                <p class="test-desc">{{ resumeDetail.portrait.workStyle }}</p>
              </div>
            </div>
          </div>

          <div v-if="resumeDetail.hrView" class="detail-section">
            <div class="test-detail-grid">
              <div class="test-detail-block" v-if="resumeDetail.hrView.roleRecommend?.bestFit?.length">
                <h4 class="detail-subtitle">推荐岗位</h4>
                <ul class="tag-list tag-list-info">
                  <li v-for="r in resumeDetail.hrView.roleRecommend.bestFit" :key="r">{{ r }}</li>
                </ul>
              </div>
              <div class="test-detail-block" v-if="resumeDetail.hrView.roleRecommend?.notSuitable?.length">
                <h4 class="detail-subtitle">不适合场景</h4>
                <ul class="tag-list tag-list-warning">
                  <li v-for="r in resumeDetail.hrView.roleRecommend.notSuitable" :key="r">{{ r }}</li>
                </ul>
              </div>
            </div>
            <div class="resume-lifecycle" v-if="resumeDetail.hrView.lifecycle">
              <h4 class="detail-subtitle">员工全生命周期预测</h4>
              <div class="lifecycle-grid">
                <div class="lifecycle-item" v-if="resumeDetail.hrView.lifecycle.onboarding">
                  <span class="lifecycle-label">入职</span>
                  <span class="lifecycle-text">{{ resumeDetail.hrView.lifecycle.onboarding }}</span>
                </div>
                <div class="lifecycle-item" v-if="resumeDetail.hrView.lifecycle.probation">
                  <span class="lifecycle-label">试用期</span>
                  <span class="lifecycle-text">{{ resumeDetail.hrView.lifecycle.probation }}</span>
                </div>
                <div class="lifecycle-item" v-if="resumeDetail.hrView.lifecycle.growth">
                  <span class="lifecycle-label">成长期</span>
                  <span class="lifecycle-text">{{ resumeDetail.hrView.lifecycle.growth }}</span>
                </div>
                <div class="lifecycle-item" v-if="resumeDetail.hrView.lifecycle.retention">
                  <span class="lifecycle-label">留存</span>
                  <span class="lifecycle-text">{{ resumeDetail.hrView.lifecycle.retention }}</span>
                </div>
              </div>
            </div>
            <div class="test-detail-grid" style="margin-top:12px">
              <div class="test-detail-block" v-if="resumeDetail.hrView.performance">
                <h4 class="detail-subtitle">绩效潜力</h4>
                <p class="test-desc"><strong>{{ resumeDetail.hrView.performance.potential }}</strong></p>
                <ul class="tag-list" v-if="resumeDetail.hrView.performance.drivers?.length">
                  <li v-for="d in resumeDetail.hrView.performance.drivers" :key="d">{{ d }}</li>
                </ul>
                <ul class="tag-list tag-list-warning" v-if="resumeDetail.hrView.performance.risks?.length">
                  <li v-for="r in resumeDetail.hrView.performance.risks" :key="r">{{ r }}</li>
                </ul>
              </div>
              <div class="test-detail-block" v-if="resumeDetail.hrView.teamFit">
                <h4 class="detail-subtitle">团队匹配 & 管理建议</h4>
                <p class="test-desc" v-if="resumeDetail.hrView.teamFit.bestTeam">
                  <strong>最佳团队：</strong>{{ resumeDetail.hrView.teamFit.bestTeam }}
                </p>
                <p class="test-desc" v-if="resumeDetail.hrView.teamFit.manageAdvice">
                  <strong>管理建议：</strong>{{ resumeDetail.hrView.teamFit.manageAdvice }}
                </p>
              </div>
              <div class="test-detail-block" v-if="resumeDetail.hrView.complianceRisk">
                <h4 class="detail-subtitle">合规风险</h4>
                <p class="test-desc">风险等级：<strong>{{ resumeDetail.hrView.complianceRisk.level }}</strong></p>
                <p class="test-desc" v-if="resumeDetail.hrView.complianceRisk.notes">{{ resumeDetail.hrView.complianceRisk.notes }}</p>
              </div>
            </div>
          </div>

          <div class="detail-section" v-if="resumeDetail.resumeHighlights">
            <h4 class="detail-subtitle">简历要点</h4>
            <p class="test-desc">{{ resumeDetail.resumeHighlights }}</p>
          </div>
        </template>

        <!-- 简历旧版纯文本兼容 -->
        <div v-else-if="currentTestType === 'resume' && currentTestDescription" class="detail-section test-detail-resume">
          <h4 class="detail-subtitle">分析报告</h4>
          <div class="resume-content">{{ currentTestDescription }}</div>
        </div>

        <!-- 通用补充说明 -->
        <div class="detail-section" v-if="currentTestType !== 'resume' && currentTestDescription">
          <h4 class="detail-subtitle">补充说明</h4>
          <p class="test-desc">{{ currentTestDescription }}</p>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Download, Search, View, UserFilled, User, OfficeBuilding, DataLine } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { request } from '@/utils/request'

const loading = ref(false)
const overviewLoading = ref(false)
const detailLoading = ref(false)
const testDetailLoading = ref(false)
const total = ref(0)
const currentPage = ref(1)
const pageSize = 10
const searchTerm = ref('')
const selectedCard = ref(0)
const selectedMbti = ref('')
const showDetailDialog = ref(false)
const detailUser = ref<Record<string, any> | null>(null)

const testPage = ref(1)
const testPageSize = 10
const showTestDetailDialog = ref(false)
const currentTest = ref<any | null>(null)

const rawTests = computed<any[]>(() => {
  const u: any = detailUser.value
  if (!u) return []
  // 详情接口只需要完整测试列表，不做去重或截断
  return (u.testList || []) as any[]
})

const testTableData = computed(() =>
  rawTests.value.map(t => ({
    ...t,
    summary: extractTestSummary(t)
  }))
)

const paginatedTests = computed(() => {
  const start = (testPage.value - 1) * testPageSize
  return testTableData.value.slice(start, start + testPageSize)
})

const currentTestType = computed(() => (currentTest.value?.testType || '').toLowerCase())

const userCards = ref<Array<{ type: string; name: string; total: number; active: number; tested: number; enterpriseId?: number }>>([
  { type: 'all', name: '全部用户', total: 0, active: 0, tested: 0 }
])
const mbtiTypes = ref<Array<{ type: string; count: number }>>([])
const users = ref<any[]>([])

const summary = computed(() => {
  const card = userCards.value[selectedCard.value]
  if (!card) return { totalUsers: 0, activeUsers: 0, activePercent: 0, testedUsers: 0, testedPercent: 0 }
  const activePercent = card.total > 0 ? Math.round((card.active / card.total) * 100) : 0
  const testedPercent = card.total > 0 ? Math.round((card.tested / card.total) * 100) : 0
  return {
    totalUsers: card.total,
    activeUsers: card.active,
    activePercent,
    testedUsers: card.tested,
    testedPercent
  }
})

async function loadOverview() {
  overviewLoading.value = true
  try {
    const res: any = await request.get('/superadmin/app-users/overview')
    const data = res.data ?? res
    userCards.value = data.userCards ?? [{ type: 'all', name: '全部用户', total: 0, active: 0, tested: 0 }]
    mbtiTypes.value = data.mbtiDistribution ?? []
  } catch {
    userCards.value = [{ type: 'all', name: '全部用户', total: 0, active: 0, tested: 0 }]
    mbtiTypes.value = []
  } finally {
    overviewLoading.value = false
  }
}

async function loadUsers() {
  loading.value = true
  try {
    const card = userCards.value[selectedCard.value]
    const params: Record<string, any> = {
      page: currentPage.value,
      pageSize,
      keyword: searchTerm.value
    }
    if (card) {
      params.pool = card.type === 'all' ? 'all' : card.type === 'individual' ? 'individual' : 'enterprise'
      if (card.type === 'enterprise' && card.enterpriseId != null) params.enterpriseId = card.enterpriseId
    }
    if (selectedMbti.value) params.mbti = selectedMbti.value

    const res: any = await request.get('/superadmin/app-users', { params })
    const list = res.data?.list ?? res?.list ?? []
    users.value = list.map((row: any) => ({
      ...row,
      username: row.username ?? row.nickname ?? ('用户' + row.id)
    }))
    total.value = res.data?.total ?? res?.total ?? 0
  } catch {
    users.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

function onSelectCard(index: number) {
  selectedCard.value = index
  currentPage.value = 1
  loadUsers()
}

function onSelectMbti(type: string) {
  selectedMbti.value = selectedMbti.value === type ? '' : type
  currentPage.value = 1
  loadUsers()
}

function formatPhone(phone: string) {
  if (!phone) return '-'
  if (phone.length === 11) return phone.substring(0, 3) + '****' + phone.substring(7)
  return phone
}

/** 文字头像：根据昵称取首字 */
function avatarLetter(row: { username?: string; nickname?: string }) {
  const name = (row?.username || row?.nickname || '?').trim()
  return (name.charAt(0) || '?').toUpperCase()
}

const AVATAR_PALETTE = ['#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#14b8a6', '#0ea5e9', '#3b82f6', '#eab308']
/** 文字头像：根据昵称生成固定背景色（同一昵称同色） */
function avatarBgColor(row: { username?: string; nickname?: string }) {
  const name = (row?.username || row?.nickname || '?').trim()
  let hash = 0
  for (let i = 0; i < name.length; i++) hash += name.charCodeAt(i)
  return AVATAR_PALETTE[Math.abs(hash) % AVATAR_PALETTE.length]
}

function formatDate(date: number | string | null | undefined) {
  if (date == null) return '-'
  if (typeof date === 'number') {
    const d = new Date(date * 1000)
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0')
  }
  return String(date)
}

function formatAmount(amountFen: number | string | null | undefined) {
  if (amountFen == null) return '¥0.00'
  const num = typeof amountFen === 'number' ? amountFen : Number(amountFen)
  if (!Number.isFinite(num) || num <= 0) return '¥0.00'
  return '¥' + (num / 100).toFixed(2)
}

function formatTestType(testType: string) {
  const t = (testType || '').toLowerCase()
  if (!t) return '-'
  if (t === 'mbti') return 'MBTI'
  if (t === 'disc') return 'DISC'
  if (t === 'pdp') return 'PDP'
  if (t === 'face' || t === 'ai') return 'AI 人脸分析'
  if (t === 'resume') return '简历综合分析'
  return testType
}

function extractTestSummary(test: any): string {
  const raw = test?.result
  if (typeof raw !== 'string' || !raw) return ''
  let data: any
  try {
    data = JSON.parse(raw)
  } catch {
    return raw
  }
  if (!data || typeof data !== 'object') return raw

  const type = (test?.testType || '').toLowerCase()

  if (type === 'mbti') {
    return String(data.mbtiType ?? data.type ?? data.result ?? '')
  }

  if (type === 'disc') {
    const desc = data.description?.type
    if (typeof desc === 'string' && desc) return desc
    if (data.dominantType) return String(data.dominantType) + '型'
    return String(data.disc ?? '')
  }

  if (type === 'pdp') {
    const desc = data.description?.type
    if (typeof desc === 'string' && desc) return desc
    if (data.dominantType) return String(data.dominantType)
    return String(data.pdp ?? '')
  }

  if (type === 'face' || type === 'ai') {
    return '人脸分析'
  }

  if (type === 'resume') {
    const c = String(data.content ?? '')
    return c ? c.substring(0, 30).replace(/\n/g, ' ') + (c.length > 30 ? '...' : '') : '简历综合分析'
  }

  return String(data.type ?? data.result ?? '')
}

const currentTestParsed = computed<any | null>(() => parseTestResult(currentTest.value))
const currentTestSummary = computed(() =>
  currentTest.value ? extractTestSummary(currentTest.value) : ''
)
const currentTestDescription = computed(() =>
  extractTestDescription(currentTestParsed.value, currentTest.value?.testType ?? '')
)

const mbtiDetail = computed(() => {
  const parsed = currentTestParsed.value
  if (!parsed || currentTestType.value !== 'mbti') return null
  const desc = parsed.description || {}
  return {
    type: parsed.mbtiType ?? desc.type ?? currentTestSummary.value,
    name: desc.name ?? '',
    description: desc.description ?? '',
    strengths: Array.isArray(desc.strengths) ? desc.strengths : [],
    weaknesses: Array.isArray(desc.weaknesses) ? desc.weaknesses : [],
    careers: Array.isArray(desc.careers) ? desc.careers : []
  }
})

const discDetail = computed(() => {
  const parsed = currentTestParsed.value
  if (!parsed || currentTestType.value !== 'disc') return null
  const desc = parsed.description || {}
  const percentages = parsed.percentages || {}
  return {
    type: desc.type ?? currentTestSummary.value,
    title: desc.title ?? '',
    description: desc.description ?? '',
    strengths: Array.isArray(desc.strengths) ? desc.strengths : [],
    weaknesses: Array.isArray(desc.weaknesses) ? desc.weaknesses : [],
    careers: Array.isArray(desc.careers) ? desc.careers : [],
    percentages: {
      D: percentages.D ?? percentages.d ?? 0,
      I: percentages.I ?? percentages.i ?? 0,
      S: percentages.S ?? percentages.s ?? 0,
      C: percentages.C ?? percentages.c ?? 0
    }
  }
})

const pdpDetail = computed(() => {
  const parsed = currentTestParsed.value
  if (!parsed || currentTestType.value !== 'pdp') return null
  const desc = parsed.description || {}
  return {
    type: desc.type ?? currentTestSummary.value,
    title: desc.title ?? '',
    emoji: desc.emoji ?? '',
    description: desc.description ?? '',
    teamRole: desc.teamRole ?? '',
    strengths: Array.isArray(desc.strengths) ? desc.strengths : [],
    weaknesses: Array.isArray(desc.weaknesses) ? desc.weaknesses : [],
    careers: Array.isArray(desc.careers) ? desc.careers : []
  }
})

const faceDetail = computed(() => {
  const parsed = currentTestParsed.value
  if (!parsed || (currentTestType.value !== 'face' && currentTestType.value !== 'ai')) return null
  return {
    mbti: parsed.mbti || null,
    disc: parsed.disc || null,
    pdp: parsed.pdp || null,
    overview: parsed.overview ?? '',
    boneAnalysis: parsed.boneAnalysis ?? '',
    personalitySummary: parsed.personalitySummary ?? '',
    photos: Array.isArray(parsed.photoUrls) ? parsed.photoUrls : []
  }
})

const resumeDetail = computed(() => {
  const parsed = currentTestParsed.value
  if (!parsed || currentTestType.value !== 'resume') return null
  if (parsed.version === 2 || parsed.overview) return parsed
  return null
})

function parseTestResult(test: any): any {
  const raw = test?.result
  if (typeof raw !== 'string' || !raw) return null
  try {
    const data = JSON.parse(raw)
    if (!data || typeof data !== 'object') return null
    return data
  } catch {
    return null
  }
}

function extractTestDescription(parsed: any, testType: string): string {
  if (!parsed || typeof parsed !== 'object') return ''
  const t = (testType || '').toLowerCase()

  if (t === 'mbti') {
    return String(parsed.description?.description ?? '')
  }

  if (t === 'disc' || t === 'pdp') {
    return String(parsed.description?.description ?? '')
  }

  if (t === 'face' || t === 'ai') {
    return String(parsed.overview ?? parsed.personalitySummary ?? '')
  }

  if (t === 'resume') {
    if (parsed.version === 2 || parsed.overview) {
      return String(parsed.overview ?? '')
    }
    return String(parsed.content ?? '')
  }

  return ''
}

// 保留 parse 能力供上方图文解析使用

function handlePageChange() {
  loadUsers()
}

function handleTestPageChange(page: number) {
  testPage.value = page
}

function exportData() {
  ElMessage.info('导出功能开发中')
}

function normalizeDetailUser(payload: any) {
  const data = payload ?? {}

  // 如果是 { user: {...}, tests: [...] } 这种旧结构，做一次扁平化
  if (data.user) {
    const user = data.user || {}
    const extra = { ...data }
    delete extra.user

    const merged: any = {
      ...user,
      ...extra
    }

    // 兼容不同测试列表字段
    merged.testList =
      data.testList ?? data.tests ?? user.testList ?? user.tests ?? []

    // 兼容可能存在的类型字段
    merged.mbtiType = merged.mbtiType ?? user.mbtiType
    merged.pdpType = merged.pdpType ?? user.pdpType
    merged.discType = merged.discType ?? user.discType
    merged.faceType = merged.faceType ?? user.faceType

    return merged
  }

  return data
}

async function loadDetailUser(userId: number) {
  try {
    detailLoading.value = true
    const res: any = await request.get(`/superadmin/app-users/${userId}`)
    const raw = res.data ?? res
    const normalized = normalizeDetailUser(raw)
    detailUser.value = normalized
  } catch {
    ElMessage.error('获取用户详情失败')
    throw new Error('load detail failed')
  } finally {
    detailLoading.value = false
  }
}

async function handleView(row: any) {
  testPage.value = 1
  detailUser.value = null
  showDetailDialog.value = true

  try {
    await loadDetailUser(row.id)
  } catch {
    showDetailDialog.value = false
  }
}

function handleViewTest(row: any) {
  currentTest.value = row
  showTestDetailDialog.value = true
}

async function handleClickTestTag(row: any, testType: string) {
  // 仅加载用户测试记录，弹出单次测试详情对话框并显示加载动画
  testPage.value = 1
  currentTest.value = null
  showTestDetailDialog.value = true
  testDetailLoading.value = true

  try {
    if (!detailUser.value || detailUser.value.id !== row.id) {
      await loadDetailUser(row.id)
    }

    const target = (testType || '').toLowerCase()
    const tests = rawTests.value
    if (!tests.length) return

    // 人脸分析相关的标签都定位到最近一次 face 测试
    const realType = target === 'face' ? 'face' : target
    const found = tests.find(t => (t.testType || '').toLowerCase() === realType)
    if (!found) return

    currentTest.value = found
  } catch {
    showTestDetailDialog.value = false
  } finally {
    testDetailLoading.value = false
  }
}

onMounted(() => {
  loadOverview().then(() => loadUsers())
})
</script>

<style scoped lang="scss">
.page-container {
  padding: 24px;
  min-height: calc(100vh - 64px);
}

.page-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 24px;

  .header-left {
    h2 {
      font-size: 24px;
      font-weight: 700;
      color: #111827;
      margin: 0 0 4px 0;
    }

    .subtitle {
      font-size: 14px;
      color: #6b7280;
      margin: 0;
    }
  }

  .header-actions {
    display: flex;
    gap: 12px;
  }
}

.user-overview-grid {
  display: flex;
  gap: 16px;
  margin-bottom: 24px;
  overflow-x: auto;
  overflow-y: hidden;
  padding-bottom: 8px;
  scrollbar-width: thin;
  scrollbar-color: #d1d5db #f3f4f6;

  &::-webkit-scrollbar {
    height: 6px;
  }

  &::-webkit-scrollbar-track {
    background: #f3f4f6;
    border-radius: 3px;
  }

  &::-webkit-scrollbar-thumb {
    background: #d1d5db;
    border-radius: 3px;

    &:hover {
      background: #9ca3af;
    }
  }

  .user-card {
    flex-shrink: 0;
    min-width: 180px;
    width: 180px;
  }
}

.user-card {
  background: #fff;
  border-radius: 8px;
  padding: 14px;
  border: 2px solid #f3f4f6;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  display: flex;
  flex-direction: column;
  gap: 12px;

  &:hover {
    border-color: #e5e7eb;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }

  &.active {
    border-color: #3b82f6;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.2);
  }

  .card-header {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 10px;

    .card-icon {
      width: 20px;
      height: 20px;
      border-radius: 4px;
      background-color: #f3f4f6;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #6b7280;
      font-size: 12px;
      flex-shrink: 0;
    }

    .card-name {
      font-size: 12px;
      font-weight: 600;
      color: #111827;
      line-height: 1.4;
      flex: 1;
    }
  }

  .card-stats {
    display: flex;
    flex-direction: column;
    gap: 8px;

    .stat-main {
      display: flex;
      align-items: baseline;
      gap: 3px;

      .stat-value-large {
        font-size: 24px;
        font-weight: 700;
        color: #111827;
        line-height: 1;
      }

      .stat-label-large {
        font-size: 14px;
        color: #6b7280;
        font-weight: 500;
      }
    }

    .stat-sub {
      display: flex;
      gap: 12px;
      align-items: center;

      .stat-active {
        font-size: 12px;
        font-weight: 600;
        color: #22c55e;
      }

      .stat-tested {
        font-size: 12px;
        font-weight: 600;
        color: #a855f7;
      }
    }
  }
}

.summary-metrics-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 16px;
  margin-bottom: 24px;

  @media (max-width: 768px) {
    grid-template-columns: 1fr;
  }

  .summary-card {
    background: #fff;
    border-radius: 8px;
    padding: 16px;
    border: 1px solid #f3f4f6;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    display: flex;
    flex-direction: column;
    gap: 6px;

    .summary-label {
      font-size: 12px;
      color: #6b7280;
    }

    .summary-value {
      font-size: 18px;
      font-weight: 700;
      color: #111827;

      .summary-percent {
        font-size: 13px;
        font-weight: 400;
        color: #9ca3af;
        margin-left: 4px;
      }
    }
  }
}

.mbti-distribution-card {
  background: #fff;
  border-radius: 8px;
  padding: 16px;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  margin-bottom: 20px;

  .mbti-header {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 12px;

    .mbti-icon {
      font-size: 14px;
      color: #6b7280;
    }

    .mbti-title {
      font-size: 13px;
      font-weight: 600;
      color: #111827;
    }
  }

  .mbti-tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;

    .mbti-tag {
      padding: 6px 12px;
      font-size: 13px;
      color: #7c3aed;
      background-color: #f3e8ff;
      border: 1px solid #e9d5ff;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.2s;
      font-weight: 500;

      &:hover {
        background-color: #e9d5ff;
        border-color: #d8b4fe;
      }

      &.active {
        background-color: #ddd6fe;
        border-color: #c4b5fd;
        color: #6d28d9;
        font-weight: 600;
      }
    }
  }
}

.search-section {
  margin-bottom: 20px;
  display: flex;
  gap: 12px;
  align-items: center;

  .search-input {
    flex: 1;
    max-width: 400px;
    :deep(.el-input__wrapper) {
      border-radius: 8px;
      background-color: #fff;
      box-shadow: 0 0 0 1px #e5e7eb inset;
      padding: 10px 16px;
      transition: all 0.2s;

      &.is-focus {
        box-shadow: 0 0 0 1px #a855f7 inset, 0 0 0 3px rgba(168, 85, 247, 0.1);
      }

      &:hover {
        box-shadow: 0 0 0 1px #d1d5db inset;
      }
    }

    :deep(.el-input__inner) {
      font-size: 14px;
      color: #111827;

      &::placeholder {
        color: #9ca3af;
      }
    }
  }
}

.table-card {
  background: #fff;
  border-radius: 10px;
  border: 1px solid #f3f4f6;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
  overflow: hidden;
}

.user-table {
  :deep(.el-table__header) {
    th {
      background-color: #f9fafb;
      color: #6b7280;
      font-weight: 500;
      font-size: 13px;
      padding: 12px 16px;
    }
  }

  :deep(.el-table__body) {
    td {
      padding: 12px 16px;
    }
  }

  .user-info-cell {
    display: flex;
    align-items: center;
    gap: 12px;

    .user-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      font-weight: 600;
      flex-shrink: 0;
    }

    .user-avatar-img {
      object-fit: cover;
    }

    .user-avatar-letter {
      color: #fff;
    }

    .user-details {
      .username {
        font-weight: 600;
        color: #111827;
        font-size: 14px;
        margin-bottom: 2px;
      }

      .role-label {
        font-size: 12px;
        color: #9ca3af;
      }
    }
  }

  .openid-line {
    font-size: 11px;
    color: #6b7280;
    font-family: ui-monospace, monospace;
    word-break: break-all;
    margin-bottom: 2px;
  }

  .contact-cell {
    .phone {
      color: #111827;
      font-size: 13px;
      margin-bottom: 2px;
    }

    .email {
      color: #9ca3af;
      font-size: 12px;
    }
  }

  .test-results {
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
    align-items: center;

    .result-tag {
      font-size: 12px;
    }

    .no-test {
      font-size: 13px;
      color: #9ca3af;
    }
  }

  .enterprise-cell {
    display: flex;
    align-items: center;
    gap: 6px;

    .enterprise-icon {
      font-size: 14px;
      color: #9ca3af;
    }

    .enterprise-name {
      font-size: 13px;
      color: #374151;
    }
  }

  .time-cell {
    font-size: 13px;
    color: #374151;
  }

  .count-cell {
    font-size: 13px;
    color: #111827;
    font-weight: 500;
  }

  .payment-cell {
    font-size: 13px;
    color: #111827;
  }

  .el-button {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 13px;
    color: #6b7280;

    &:hover {
      color: #a855f7;
    }
  }
}

.empty-state {
  padding: 60px;
  text-align: center;
  color: #9ca3af;
  font-size: 14px;
}

.test-table {
  :deep(.el-table__header) {
    th {
      background-color: #f9fafb;
      color: #6b7280;
      font-weight: 500;
      font-size: 13px;
      padding: 12px 16px;
    }
  }

  :deep(.el-table__body) {
    td {
      padding: 10px 16px;
      font-size: 13px;
      color: #111827;
    }
  }
}

.pagination-container {
  padding: 20px;
  display: flex;
  justify-content: center;
}

.detail-section {
  margin-bottom: 16px;
}
.detail-row {
  display: flex;
  margin-bottom: 10px;
  font-size: 14px;
}
.detail-label {
  width: 90px;
  color: #6b7280;
  flex-shrink: 0;
}
.detail-subtitle {
  font-size: 14px;
  color: #374151;
  margin: 12px 0 8px 0;
}

.mr-1 {
  margin-right: 4px;
}

.test-detail-header {
  padding-bottom: 8px;
  border-bottom: 1px solid #f3f4f6;
}

.test-detail-header-main {
  display: flex;
  align-items: center;
  gap: 12px;
}

.test-type-tag {
  padding: 4px 10px;
  border-radius: 999px;
  background-color: #eef2ff;
  color: #4f46e5;
  font-size: 12px;
  font-weight: 600;
}

.test-type-title {
  font-size: 18px;
  font-weight: 600;
  color: #111827;
}

.test-type-meta {
  margin-top: 4px;
  font-size: 12px;
  color: #6b7280;
}

.test-detail-main-card {
  background-color: #f9fafb;
  border-radius: 10px;
  padding: 14px 16px;
  display: flex;
  justify-content: space-between;
  gap: 16px;
  margin-top: 8px;
}

.test-detail-main-left {
  flex: 1;
}

.test-detail-main-right {
  width: 260px;
  flex-shrink: 0;
}

.mbti-type-badge {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  padding: 4px 10px;
  border-radius: 999px;
  background-color: #eef2ff;
  color: #4f46e5;
  font-weight: 700;
  font-size: 14px;
  margin-bottom: 4px;
}

.mbti-name {
  font-size: 16px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 4px;
}

.mbti-desc {
  font-size: 13px;
  color: #4b5563;
  margin: 0;
}

.disc-type-badge {
  @extend .mbti-type-badge;
  background-color: #fee2e2;
  color: #b91c1c;
}

.disc-title {
  font-size: 15px;
  font-weight: 600;
  color: #111827;
  margin-bottom: 4px;
}

.disc-desc {
  font-size: 13px;
  color: #4b5563;
  margin: 0;
}

.disc-bars {
  margin-top: 6px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.disc-bar-row {
  display: flex;
  align-items: center;
  gap: 6px;
}

.disc-bar-label {
  width: 16px;
  font-size: 12px;
  font-weight: 600;
  color: #4b5563;
}

.disc-bar-track {
  flex: 1;
  height: 6px;
  border-radius: 999px;
  background-color: #e5e7eb;
  overflow: hidden;
}

.disc-bar-fill {
  height: 100%;
  border-radius: 999px;
}

.disc-bar-fill-d {
  background-color: #f97373;
}

.disc-bar-fill-i {
  background-color: #facc15;
}

.disc-bar-fill-s {
  background-color: #22c55e;
}

.disc-bar-fill-c {
  background-color: #3b82f6;
}

.disc-bar-value {
  width: 42px;
  font-size: 12px;
  color: #4b5563;
  text-align: right;
}

.pdp-emoji {
  font-size: 28px;
  margin-bottom: 4px;
}

.pdp-type-line {
  display: flex;
  align-items: baseline;
  gap: 4px;
  margin-bottom: 4px;
}

.pdp-type {
  font-size: 16px;
  font-weight: 700;
  color: #b45309;
}

.pdp-title {
  font-size: 14px;
  color: #92400e;
}

.pdp-desc {
  font-size: 13px;
  color: #4b5563;
  margin: 0;
}

.test-detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  margin-top: 12px;
}

.test-detail-block {
  background-color: #f9fafb;
  border-radius: 8px;
  padding: 10px 12px;
}

.tag-list {
  list-style: none;
  padding: 0;
  margin: 4px 0 0 0;
  display: flex;
  flex-wrap: wrap;
  gap: 6px;

  li {
    padding: 3px 8px;
    border-radius: 999px;
    background-color: #eef2ff;
    color: #4f46e5;
    font-size: 12px;
  }
}

.tag-list-warning li {
  background-color: #fef3c7;
  color: #92400e;
}

.tag-list-info li {
  background-color: #e0f2fe;
  color: #0369a1;
}

.face-layout {
  display: flex;
  gap: 16px;
  margin-top: 8px;
}

.face-left {
  width: 220px;
  flex-shrink: 0;
}

.face-photos {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 6px;

  :deep(.el-image) {
    width: 100%;
    height: 100px;
    border-radius: 8px;
    overflow: hidden;
  }
}

.face-right {
  flex: 1;
}

.test-desc {
  font-size: 13px;
  color: #4b5563;
  margin: 4px 0 0 0;
  line-height: 1.6;
}

.resume-content {
  white-space: pre-wrap;
  word-break: break-word;
  line-height: 1.8;
  font-size: 14px;
  color: #374151;
  background: #f9fafb;
  border-radius: 8px;
  padding: 16px;
  max-height: 480px;
  overflow-y: auto;
}

.resume-boss-section {
  background: linear-gradient(135deg, #ede9fe 0%, #e0f2fe 100%);
  border-radius: 12px;
  padding: 16px 20px;
  margin-bottom: 4px;
}

.resume-headline {
  font-size: 15px;
  font-weight: 600;
  color: #1e1b4b;
  margin-bottom: 12px;
}

.resume-metrics-row {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
  margin-bottom: 10px;
}

.resume-metric-card {
  flex: 1;
  min-width: 80px;
  background: rgba(255,255,255,0.7);
  border-radius: 10px;
  padding: 10px 12px;
  text-align: center;
  backdrop-filter: blur(6px);
  border: 1px solid rgba(255,255,255,0.9);
}

.resume-metric-value {
  font-size: 18px;
  font-weight: 700;
  line-height: 1.2;
}

.resume-metric-label {
  font-size: 11px;
  color: #6b7280;
  margin-top: 3px;
}

.metric-high .resume-metric-value { color: #059669; }
.metric-medium .resume-metric-value { color: #d97706; }
.metric-low .resume-metric-value { color: #dc2626; }

.resume-cost-insight {
  font-size: 12px;
  color: #4338ca;
  background: rgba(255,255,255,0.5);
  border-radius: 6px;
  padding: 6px 10px;
}

.resume-lifecycle { margin-top: 12px; }

.lifecycle-grid {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 8px;
}

.lifecycle-item {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  font-size: 13px;
}

.lifecycle-label {
  flex-shrink: 0;
  width: 44px;
  background: #e0e7ff;
  color: #3730a3;
  border-radius: 4px;
  padding: 2px 6px;
  font-size: 11px;
  font-weight: 600;
  text-align: center;
  margin-top: 1px;
}

.lifecycle-text {
  color: #374151;
  line-height: 1.6;
}

.json-block {
  max-height: 260px;
  overflow: auto;
  background-color: #0f172a;
  color: #e5e7eb;
  padding: 10px 12px;
  border-radius: 8px;
  font-size: 12px;
  line-height: 1.4;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
}
</style>
