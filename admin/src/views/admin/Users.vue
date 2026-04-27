<template>
  <div class="page-container">
    <div class="page-header">
      <div class="header-left">
        <h2>用户档案管理</h2>
        <p class="subtitle">以用户为核心 · 联系方式、简历档案、侧脸分析、合作意向一览，点击用户查看完整旅程</p>
      </div>
      <div class="header-actions">
        <el-button variant="outline" size="small" @click="exportData">
          <el-icon class="mr-1"><Download /></el-icon>导出数据
        </el-button>
      </div>
    </div>

    <div class="profile-summary profile-summary--six">
      <div class="summary-card">
        <div class="summary-ic ic-blue">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/><circle cx="9" cy="7" r="4" stroke="currentColor" stroke-width="1.75"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
        </div>
        <div class="summary-body">
          <div class="summary-label">全部用户</div>
          <div class="summary-value">{{ total.toLocaleString() }}</div>
          <div class="summary-foot">本页显示 <strong>{{ pageUserCount }}</strong> 人</div>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-ic ic-teal">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="5" stroke="currentColor" stroke-width="1.75"/><path d="M3 21s1-4 9-4 9 4 9 4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
        </div>
        <div class="summary-body">
          <div class="summary-label">有侧脸档案</div>
          <div class="summary-value">
            {{ profileStats.faceCount }}
            <span class="summary-sub">/ {{ total }}</span>
          </div>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-ic ic-indigo">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="3" stroke="currentColor" stroke-width="1.75"/><path d="M8 12h8M8 8h8M8 16h5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
        </div>
        <div class="summary-body">
          <div class="summary-label">有简历</div>
          <div class="summary-value">
            {{ profileStats.resumeCount }}
            <span class="summary-sub">/ {{ total }}</span>
          </div>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-ic ic-violet">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M9 11l3 3L22 4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/><path d="M21 12v7a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h11" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
        </div>
        <div class="summary-body">
          <div class="summary-label">完成测评</div>
          <div class="summary-value">
            {{ profileStats.anyTestCount }}
            <span class="summary-sub">/ {{ total }}</span>
          </div>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-ic ic-amber">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.8 19.79 19.79 0 01.13 1.18 2 2 0 012.11 0h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L6.09 7.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z" stroke="currentColor" stroke-width="1.75" stroke-linejoin="round"/></svg>
        </div>
        <div class="summary-body">
          <div class="summary-label">有联系方式</div>
          <div class="summary-value">
            {{ profileStats.phoneCount }}
            <span class="summary-sub">/ {{ total }}</span>
          </div>
        </div>
      </div>
      <div class="summary-card">
        <div class="summary-ic ic-coop">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M12 7a4 4 0 110-8M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/></svg>
        </div>
        <div class="summary-body">
          <div class="summary-label">已选合作意向</div>
          <div class="summary-value">
            {{ profileStats.cooperationCount }}
            <span class="summary-sub">/ {{ total }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- 搜索栏 -->
    <div class="search-section admin-filter-bar">
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
        <el-select
          v-model="coldFaceFilter"
          multiple
          collapse-tags
          collapse-tags-tooltip
          placeholder="冷脸分析"
          class="coldface-select"
          @change="() => { currentPage = 1; loadUsers() }"
        >
          <el-option label="暖" value="warm" />
          <el-option label="中" value="neutral" />
          <el-option label="冷" value="cold" />
        </el-select>
        <el-button type="primary" @click="loadUsers">搜索</el-button>
      </div>

    <!-- 用户列表表格 -->
    <div class="table-card">
      <el-table :data="users" style="width: 100%" v-loading="loading" class="user-table" row-key="id" @row-click="(row: any) => handleView(row)">
        <!-- 用户基本信息 -->
        <el-table-column label="用户" min-width="180">
          <template #default="{ row }">
            <div class="user-cell">
              <div class="user-avatar-wrap">
                <div class="user-avatar">
                  <img v-if="displayAvatarUrl(row.avatar)" :src="displayAvatarUrl(row.avatar)" referrerpolicy="no-referrer" alt="" />
                  <span v-else :style="{ backgroundColor: avatarBgColor(row), width: '100%', height: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', color: '#fff' }">
                    {{ avatarLetter(row) }}
                  </span>
                </div>
                <!-- 分销商标识 -->
                <span v-if="row.isDistributor" class="distributor-dot" title="分销商"></span>
              </div>
              <div class="user-meta">
                <div class="user-name-row">
                  <span class="user-name">{{ row.username || '未设置昵称' }}</span>
                  <span v-if="row.isDistributor" class="dist-badge">分销</span>
                </div>
                <div class="user-id">ID {{ row.id }}</div>
              </div>
            </div>
          </template>
        </el-table-column>

        <!-- 联系方式 -->
        <el-table-column label="联系方式" min-width="140">
          <template #default="{ row }">
            <div class="contact-cell">
              <div class="phone">{{ row.phone || '—' }}</div>
              <div v-if="row.openid" class="openid-line" :title="row.openid">
                {{ String(row.openid).length > 16 ? String(row.openid).slice(0, 16) + '…' : row.openid }}
              </div>
            </div>
          </template>
        </el-table-column>

        <!-- 简历 -->
        <el-table-column label="简历" width="80" align="center">
          <template #default="{ row }">
            <span v-if="row.hasResume || (row.phone && row.testCount > 0)" class="col-dot col-dot--yes" title="有简历">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M20 6L9 17l-5-5" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </span>
            <span v-else class="col-dot col-dot--no">—</span>
          </template>
        </el-table-column>

        <!-- 侧脸 -->
        <el-table-column label="侧脸" width="100" align="center">
          <template #default="{ row }">
            <span v-if="row.coldFaceLevel" :class="['coldface-tag', 'coldface-' + row.coldFaceLevel]" :title="coldFaceTooltip(row)">
              {{ coldFaceLabel(row.coldFaceLevel) }}<template v-if="row.coldFaceScore != null"> · {{ row.coldFaceScore }}</template>
            </span>
            <span v-else class="col-dot col-dot--no">—</span>
          </template>
        </el-table-column>

        <!-- 测评结果 -->
        <el-table-column label="测评" min-width="180">
          <template #default="{ row }">
            <div class="test-chips">
              <span v-if="row.mbtiType" class="chip chip--mbti">MBTI · {{ row.mbtiType }}</span>
              <span v-if="row.sbtiType" class="chip chip--sbti">SBTI · {{ row.sbtiType }}</span>
              <span v-if="row.discType" class="chip chip--disc">DISC · {{ row.discType }}</span>
              <span v-if="row.pdpType"  class="chip chip--pdp">PDP · {{ row.pdpType }}</span>
              <span v-if="!row.mbtiType && !row.sbtiType && !row.discType && !row.pdpType" class="col-dot col-dot--no">—</span>
            </div>
          </template>
        </el-table-column>

        <!-- 注册时间 -->
        <el-table-column label="注册时间" width="130">
          <template #default="{ row }">
            <span class="time-cell">{{ formatDate(row.createdAt) }}</span>
          </template>
        </el-table-column>

        <!-- 操作 -->
        <el-table-column label="操作" width="80" fixed="right">
          <template #default="{ row }">
            <el-button link type="primary" @click.stop="handleView(row)">查看</el-button>
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
          layout="total, prev, pager, next"
          @current-change="handlePageChange"
        />
      </div>
    </div>

    <UserDetailDialog
      v-model="showDetailDialog"
      :user="detailUser"
      :loading="detailLoading"
      :show-enterprise-match="false"
      @view-test="handleViewTest"
    />

    <!-- 单次测试详情对话框 -->
    <el-dialog
      v-model="showTestDetailDialog"
      title="测试详情"
      width="760px"
      destroy-on-close
      v-loading="testDetailLoading"
    >
      <template v-if="currentTest">
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
                  <span class="disc-bar-label">
                    <span class="disc-bar-letter" :class="'disc-bar-letter-' + key.toLowerCase()">{{ key }}</span>
                    <span class="disc-bar-cn">{{ discStyleName(key) }}</span>
                  </span>
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

        <!-- SBTI 图文详情 -->
        <div v-else-if="currentTestType === 'sbti' && sbtiDetail" class="detail-section test-detail-sbti">
          <div class="test-detail-main-card test-detail-main-card--sbti">
            <div class="test-detail-main-left">
              <div class="sbti-type-line">
                <span class="sbti-code-badge">{{ sbtiDetail.code || '—' }}</span>
                <span v-if="sbtiDetail.cn" class="sbti-cn">· {{ sbtiDetail.cn }}</span>
              </div>
              <p v-if="sbtiDetail.intro" class="sbti-intro">{{ sbtiDetail.intro }}</p>
              <p v-if="sbtiDetail.desc" class="sbti-long-desc">{{ sbtiDetail.desc }}</p>
            </div>
            <div v-if="sbtiDetail.imageUrl" class="test-detail-sbti-right">
              <el-image
                class="sbti-result-img"
                :src="sbtiDetail.imageUrl"
                fit="contain"
                referrerpolicy="no-referrer"
              >
                <template #error>
                  <div class="sbti-img-fallback">{{ sbtiDetail.summaryLine }}</div>
                </template>
              </el-image>
            </div>
          </div>
          <div v-if="sbtiDetail.levelsList?.length" class="test-detail-grid">
            <div class="test-detail-block">
              <h4 class="detail-subtitle">15 维等级</h4>
              <div class="sbti-level-tags">
                <el-tag v-for="row in sbtiDetail.levelsList" :key="row.key" size="small" class="sbti-level-tag">
                  {{ row.key }}：{{ row.val }}<template v-if="row.raw != null"> · {{ row.raw }}分</template>
                </el-tag>
              </div>
            </div>
          </div>
        </div>

        <!-- AI 人脸分析 图文详情 -->
        <div v-else-if="(currentTestType === 'face' || currentTestType === 'ai') && faceDetail" class="detail-section test-detail-face">
          <div class="face-layout">
            <div class="face-left" v-if="faceDetail.photos && faceDetail.photos.length">
              <div
                class="face-photos"
                :class="'face-photos--' + Math.min(faceDetail.photos.length, 3)"
              >
                <el-image
                  v-for="(url, idx) in faceDetail.photos"
                  :key="url + idx"
                  :src="url"
                  fit="cover"
                  :preview-src-list="faceDetail.photos"
                  :initial-index="idx"
                  preview-teleported
                  class="face-photo-thumb"
                />
              </div>
            </div>
            <div class="face-right">
              <!-- 第一组：核心性格标签 (MBTI, DISC, PDP) -->
              <div class="test-detail-row compact-row">
                <div class="test-detail-block flex-1" v-if="faceDetail.mbti">
                  <h4 class="detail-subtitle">AI 识别 MBTI</h4>
                  <p class="test-desc">
                    <strong class="text-primary">{{ faceDetail.mbti.type }}</strong>
                    <span v-if="faceDetail.mbti.title" class="text-secondary"> · {{ faceDetail.mbti.title }}</span>
                  </p>
                </div>
                <div class="test-detail-block flex-1" v-if="faceDetail.disc">
                  <h4 class="detail-subtitle">AI 识别 DISC</h4>
                  <p class="test-desc">
                    主类型：<strong class="text-primary">{{ faceDiscDisplayLabel(faceDetail.disc.primary) }}</strong>
                    <span v-if="faceDetail.disc.secondary" class="text-secondary">，次：{{ faceDiscDisplayLabel(faceDetail.disc.secondary) }}</span>
                  </p>
                </div>
                <div class="test-detail-block flex-1" v-if="faceDetail.pdp">
                  <h4 class="detail-subtitle">AI 识别 PDP</h4>
                  <p class="test-desc">
                    主类型：<strong class="text-primary">{{ facePdpDisplayLabel(faceDetail.pdp.primary) }}</strong>
                    <span v-if="faceDetail.pdp.secondary" class="text-secondary">，次：{{ facePdpDisplayLabel(faceDetail.pdp.secondary) }}</span>
                  </p>
                </div>
              </div>

              <!-- 第二组：核心优势 (主要优势, 盖洛普) -->
              <div class="test-detail-grid">
                <div class="test-detail-block" v-if="faceDetail.advantages?.length">
                  <h4 class="detail-subtitle">主要优势</h4>
                  <ul class="tag-list">
                    <li v-for="item in faceDetail.advantages" :key="item">{{ item }}</li>
                  </ul>
                </div>
                <div class="test-detail-block" v-if="faceDetail.gallupTop3?.length">
                  <h4 class="detail-subtitle">盖洛普前三大优势</h4>
                  <ol class="face-gallup-list">
                    <li v-for="(g, gi) in faceDetail.gallupTop3" :key="gi">{{ g }}</li>
                  </ol>
                </div>
              </div>

              <!-- 第三组：综述 -->
              <div class="test-detail-grid">
                <div class="test-detail-block" v-if="faceDetail.overview">
                  <h4 class="detail-subtitle">整体气质概览</h4>
                  <p class="test-desc">{{ faceDetail.overview }}</p>
                </div>
                <div class="test-detail-block" v-if="faceDetail.personalitySummary">
                  <h4 class="detail-subtitle">性格总结</h4>
                  <p class="test-desc">{{ faceDetail.personalitySummary }}</p>
                </div>
              </div>

              <!-- 第四组：面相与骨相 -->
              <div class="test-detail-grid">
                <div class="test-detail-block" v-if="faceDetail.faceFeatures?.length">
                  <h4 class="detail-subtitle">面部特征分析</h4>
                  <ul class="face-feature-list">
                    <li v-for="(f, fi) in faceDetail.faceFeatures" :key="fi">
                      <strong v-if="f.label">{{ f.label }}：</strong>
                      <span>{{ f.description }}</span>
                    </li>
                  </ul>
                </div>
                <div class="test-detail-block" v-else-if="faceDetail.faceAnalysisText">
                  <h4 class="detail-subtitle">面相分析</h4>
                  <p class="test-desc">{{ faceDetail.faceAnalysisText }}</p>
                </div>

                <div class="test-detail-block" v-if="faceDetail.boneIceSummary && (faceDetail.boneIceSummary.elementType || faceDetail.boneIceSummary.boneFleshRelation)">
                  <h4 class="detail-subtitle">骨相分析（《冰鉴》）</h4>
                  <p v-if="faceDetail.boneIceSummary.elementType" class="test-desc">
                    <strong>五行形相：</strong>{{ faceDetail.boneIceSummary.elementType }}
                  </p>
                  <p v-if="faceDetail.boneIceSummary.boneFleshRelation" class="test-desc">
                    <strong>骨肉关系：</strong>{{ faceDetail.boneIceSummary.boneFleshRelation }}
                  </p>
                </div>
                <div class="test-detail-block" v-if="faceDetail.boneAnalysisText">
                  <h4 class="detail-subtitle">骨相特征</h4>
                  <p class="test-desc">{{ faceDetail.boneAnalysisText }}</p>
                </div>
              </div>

              <!-- 第五组：人际与发展 -->
              <div class="test-detail-grid">
                <div class="test-detail-block" v-if="faceDetail.relationship">
                  <h4 class="detail-subtitle">人际关系与团队合作</h4>
                  <p class="test-desc">{{ faceDetail.relationship }}</p>
                </div>
                <div class="test-detail-block" v-if="faceDetail.careers?.length">
                  <h4 class="detail-subtitle">职业方向参考</h4>
                  <ul class="tag-list tag-list-info">
                    <li v-for="c in faceDetail.careers" :key="c">{{ c }}</li>
                  </ul>
                </div>
              </div>

              <!-- 第六组：生活场景 -->
              <div class="test-detail-grid">
                <div class="test-detail-block" v-if="faceDetail.careerDevelopment">
                  <h4 class="detail-subtitle">职业发展方向</h4>
                  <p class="test-desc">{{ faceDetail.careerDevelopment }}</p>
                </div>
                <div class="test-detail-block" v-if="faceDetail.familyParenting">
                  <h4 class="detail-subtitle">家庭亲子关系</h4>
                  <p class="test-desc">{{ faceDetail.familyParenting }}</p>
                </div>
                <div class="test-detail-block" v-if="faceDetail.partnerCofounder">
                  <h4 class="detail-subtitle">寻找合伙人</h4>
                  <p class="test-desc">{{ faceDetail.partnerCofounder }}</p>
                </div>
              </div>

              <!-- 第七组：深度职场洞察 (Portrait, Boss, HR, Resume) -->
              <div class="test-detail-grid" v-if="faceDetail.portrait || faceDetail.bossView || faceDetail.hrView || faceDetail.resumeHighlights">
                <template v-if="faceDetail.portrait">
                  <div class="test-detail-block" v-if="faceDetail.portrait.coreStrengths?.length">
                    <h4 class="detail-subtitle">深度·核心优势</h4>
                    <ul class="tag-list">
                      <li v-for="s in faceDetail.portrait.coreStrengths" :key="s">{{ s }}</li>
                    </ul>
                  </div>
                  <div class="test-detail-block" v-if="faceDetail.portrait.coreRisks?.length">
                    <h4 class="detail-subtitle">深度·潜在风险</h4>
                    <ul class="tag-list tag-list-warning">
                      <li v-for="r in faceDetail.portrait.coreRisks" :key="r">{{ r }}</li>
                    </ul>
                  </div>
                  <div class="test-detail-block" v-if="faceDetail.portrait.workStyle">
                    <h4 class="detail-subtitle">深度·工作风格</h4>
                    <p class="test-desc">{{ faceDetail.portrait.workStyle }}</p>
                  </div>
                </template>
                <div class="test-detail-block" v-if="faceDetail.resumeHighlights">
                  <h4 class="detail-subtitle">简历要点</h4>
                  <p class="test-desc">{{ faceDetail.resumeHighlights }}</p>
                </div>
                <template v-if="faceDetail.bossView">
                  <div class="test-detail-block" v-if="faceDetail.bossView.headline">
                    <h4 class="detail-subtitle">老板视角·一句话</h4>
                    <p class="test-desc">{{ faceDetail.bossView.headline }}</p>
                  </div>
                  <div class="test-detail-block" v-if="faceDetail.bossView.costInsight">
                    <h4 class="detail-subtitle">老板视角·成本洞察</h4>
                    <p class="test-desc">{{ faceDetail.bossView.costInsight }}</p>
                  </div>
                </template>
                <template v-if="faceDetail.hrView">
                  <div class="test-detail-block" v-if="faceDetail.hrView.roleRecommend?.bestFit?.length">
                    <h4 class="detail-subtitle">HR·推荐岗位</h4>
                    <ul class="tag-list tag-list-info">
                      <li v-for="r in faceDetail.hrView.roleRecommend.bestFit" :key="r">{{ r }}</li>
                    </ul>
                  </div>
                  <div class="test-detail-block" v-if="faceDetail.hrView.roleRecommend?.notSuitable?.length">
                    <h4 class="detail-subtitle">HR·不适合场景</h4>
                    <ul class="tag-list tag-list-warning">
                      <li v-for="r in faceDetail.hrView.roleRecommend.notSuitable" :key="r">{{ r }}</li>
                    </ul>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </div>

        <!-- 简历综合分析 v2 结构化详情 -->
        <template v-else-if="currentTestType === 'resume' && resumeDetail">
          <!-- 老板视角核心指标 -->
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

          <!-- 人才画像 -->
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

          <!-- HR视角：岗位推荐 + 员工生命周期 -->
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
                <p class="test-desc">
                  风险等级：<strong>{{ resumeDetail.hrView.complianceRisk.level }}</strong>
                </p>
                <p class="test-desc" v-if="resumeDetail.hrView.complianceRisk.notes">
                  {{ resumeDetail.hrView.complianceRisk.notes }}
                </p>
              </div>
            </div>
          </div>

          <!-- 简历摘要 -->
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

        <div
          class="detail-section"
          v-if="
            currentTestType !== 'resume' &&
            currentTestType !== 'face' &&
            currentTestType !== 'ai' &&
            currentTestType !== 'sbti' &&
            supplementaryNote
          "
        >
          <h4 class="detail-subtitle">补充说明</h4>
          <p class="test-desc">{{ supplementaryNote }}</p>
        </div>
      </template>
    </el-dialog>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Download, Search, View } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { request } from '@/utils/request'
import { buildFaceDetailFromParsed, faceDiscDisplayLabel, facePdpDisplayLabel } from '@/utils/faceResultDetail'
import { parseTestResultPayload } from '@/utils/testResultParse'
import { discTopTwoLabel, discStyleSubtitle, discStyleName } from '@/utils/discDisplay'
import { formatSbtiSummary, getSbtiCode, getSbtiCn, sbtiTypeImageUrl } from '@/utils/sbtiDisplay'
import UserDetailDialog from '@/components/UserDetailDialog.vue'

const loading = ref(false)
const detailLoading = ref(false)
const testDetailLoading = ref(false)
const total = ref(0)
const currentPage = ref(1)
const pageSize = 10
const searchTerm = ref('')
const coldFaceFilter = ref<string[]>([])
const showDetailDialog = ref(false)
const detailUser = ref<Record<string, any> | null>(null)

const showTestDetailDialog = ref(false)
const currentTest = ref<any | null>(null)

const rawTests = computed<any[]>(() => {
  const u: any = detailUser.value
  if (!u) return []
  return (u.testList || []) as any[]
})

const currentTestType = computed(() => (currentTest.value?.testType || '').toLowerCase())

const users = ref<any[]>([])

const pageUserCount = computed(() => (users.value || []).length)

/**
 * 画像汇总：遍历当前页用户，分母用全量 total，分子按当前页估算。
 * 后端如提供全量统计接口，可将此 computed 替换为接口数据。
 */
const profileStats = computed(() => {
  const list = users.value || []
  let faceCount = 0
  let resumeCount = 0
  let phoneCount = 0
  let anyTestCount = 0
  let cooperationCount = 0
  for (const u of list) {
    const hasFace = !!(u.faceMbtiType || u.faceDiscType || u.facePdpType || u.faceType || u.coldFaceLevel)
    const hasTest = !!(u.mbtiType || u.sbtiType || u.discType || u.pdpType || hasFace)
    const hasPhone = !!u.phone
    const hasResume = hasPhone && (u.testCount > 0)
    if (hasFace)     faceCount++
    if (hasResume)   resumeCount++
    if (hasPhone)    phoneCount++
    if (hasTest)     anyTestCount++
    if (u.cooperationModeCode || u.cooperationModeTitle || u.cooperationChosenAt) cooperationCount++
  }
  // 按当前页比例外推到全量（如后端提供接口可替换）
  const ratio = list.length > 0 ? (total.value / list.length) : 1
  return {
    faceCount:        Math.round(faceCount    * ratio),
    resumeCount:      Math.round(resumeCount  * ratio),
    phoneCount:       Math.round(phoneCount   * ratio),
    anyTestCount:     Math.round(anyTestCount * ratio),
    cooperationCount: Math.round(cooperationCount * ratio),
  }
})

// 分销商 userId 集合（用于标注）
const distributorIds = ref<Set<number | string>>(new Set())

async function loadDistributorIds() {
  try {
    const res: any = await request.get('/admin/distribution/distributors', { params: { pageSize: 1000 } })
    const list: any[] = res.data?.list ?? res?.list ?? []
    distributorIds.value = new Set(list.map((d: any) => d.userId ?? d.id))
  } catch {
    // 接口失败时忽略，不影响用户列表加载
  }
}

async function loadUsers() {
  loading.value = true
  try {
    const params: Record<string, any> = {
      page: currentPage.value,
      pageSize,
      keyword: searchTerm.value
    }
    if (coldFaceFilter.value.length > 0) {
      params.coldFaceLevel = coldFaceFilter.value.join(',')
    }
    const res: any = await request.get('/admin/app-users', { params })
    const payload = res.data ?? res
    const list = Array.isArray(payload?.list) ? payload.list : Array.isArray(payload) ? payload : []
    users.value = list.map((row: any) => ({
      ...row,
      username: row.username ?? row.nickname ?? ('用户' + row.id),
      isDistributor: row.isDistributor ?? distributorIds.value.has(row.id) ?? false
    }))
    total.value = Number(payload?.total ?? 0) || 0
  } catch {
    users.value = []
    total.value = 0
  } finally {
    loading.value = false
  }
}

/** 有效头像地址（接口字段 avatar，过滤空白） */
function displayAvatarUrl(avatar: string | null | undefined) {
  const u = avatar != null ? String(avatar).trim() : ''
  return u || ''
}

/** 无头像时文字占位：昵称首字 */
function avatarLetter(row: { username?: string; nickname?: string }) {
  const name = (row?.username || row?.nickname || '?').trim()
  const ch = name.charAt(0) || '?'
  return /[a-zA-Z]/.test(ch) ? ch.toUpperCase() : ch
}

const AVATAR_PALETTE = ['#6366f1', '#8b5cf6', '#ec4899', '#f43f5e', '#14b8a6', '#0ea5e9', '#3b82f6', '#eab308']
function avatarBgColor(row: { username?: string; nickname?: string }) {
  const name = (row?.username || row?.nickname || '?').trim()
  let hash = 0
  for (let i = 0; i < name.length; i++) hash += name.charCodeAt(i)
  return AVATAR_PALETTE[Math.abs(hash) % AVATAR_PALETTE.length]
}

function coldFaceLabel(level: string | null | undefined) {
  if (level === 'cold') return '冷'
  if (level === 'warm') return '暖'
  if (level === 'neutral') return '中'
  return ''
}

function coldFaceTooltip(row: any) {
  const parts: string[] = []
  if (row?.coldFaceScore != null) parts.push(`冷脸分：${row.coldFaceScore}`)
  if (row?.coldFaceUpdatedAt) parts.push(`更新于 ${formatDate(row.coldFaceUpdatedAt)}`)
  return parts.join(' · ') || '尚未完成面相分析'
}

function formatPhone(phone: string) {
  if (!phone) return '-'
  if (phone.length === 11) return phone.substring(0, 3) + '****' + phone.substring(7)
  return phone
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
  if (t === 'sbti') return 'SBTI'
  return testType
}

function extractTestSummary(test: any): string {
  const data = parseTestResultPayload(test?.result ?? test?.resultData)
  if (!data) {
    const raw = test?.result
    return typeof raw === 'string' ? raw : ''
  }

  const type = (test?.testType || '').toLowerCase()

  if (type === 'mbti') {
    return String(data.mbtiType ?? data.type ?? data.result ?? '')
  }

  if (type === 'disc') {
    const two = discTopTwoLabel(data)
    if (two) return two
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

  if (type === 'sbti') {
    const line = formatSbtiSummary(data)
    if (line) return line
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
  const typeLine = discTopTwoLabel(parsed) || String(desc.type ?? currentTestSummary.value ?? '')
  const titleLine = discStyleSubtitle(parsed) || String(desc.title ?? '')
  return {
    type: typeLine,
    title: titleLine,
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

const sbtiDetail = computed(() => {
  const parsed = currentTestParsed.value
  if (!parsed || currentTestType.value !== 'sbti') return null
  const code = getSbtiCode(parsed)
  const cn = getSbtiCn(parsed)
  const ft = parsed.finalType && typeof parsed.finalType === 'object' ? parsed.finalType : null
  const intro = String(parsed.intro ?? ft?.intro ?? '')
  const desc = String(parsed.desc ?? ft?.desc ?? '')
  const levels = parsed.levels && typeof parsed.levels === 'object' ? parsed.levels : {}
  const rawScores = parsed.rawScores && typeof parsed.rawScores === 'object' ? parsed.rawScores : {}
  const levelsList = Object.entries(levels).map(([key, val]) => {
    const r = (rawScores as Record<string, unknown>)[key]
    let raw: number | null = null
    if (r != null && r !== '') {
      const n = Number(r)
      if (Number.isFinite(n)) raw = n
    }
    return { key, val: String(val), raw }
  })
  const imageUrl = sbtiTypeImageUrl(code)
  return {
    code,
    cn,
    intro,
    desc,
    levelsList,
    imageUrl,
    summaryLine: formatSbtiSummary(parsed)
  }
})

const supplementaryNote = computed(() => {
  const t = String(currentTestDescription.value || '').trim()
  if (!t) return ''
  const typ = (currentTestType.value || '').toLowerCase()
  if (typ === 'disc' && discDetail.value) {
    const main = String(discDetail.value.description || '').trim()
    if (main && main === t) return ''
  }
  if (typ === 'mbti' && mbtiDetail.value) {
    const main = String(mbtiDetail.value.description || '').trim()
    if (main && main === t) return ''
  }
  if (typ === 'pdp' && pdpDetail.value) {
    const main = String(pdpDetail.value.description || '').trim()
    if (main && main === t) return ''
  }
  if (typ === 'sbti' && sbtiDetail.value) {
    const main = String(sbtiDetail.value.desc || '').trim()
    if (main && main === t) return ''
  }
  return t
})

const faceDetail = computed(() => {
  const parsed = currentTestParsed.value
  if (!parsed || (currentTestType.value !== 'face' && currentTestType.value !== 'ai')) return null
  return buildFaceDetailFromParsed(parsed)
})

const resumeDetail = computed(() => {
  const parsed = currentTestParsed.value
  if (!parsed || currentTestType.value !== 'resume') return null
  // v2 结构化 JSON
  if (parsed.version === 2 || parsed.overview) return parsed
  return null
})

function parseTestResult(test: any): any {
  if (!test) return null
  return parseTestResultPayload(test.result ?? test.resultData)
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

  if (t === 'sbti') {
    const ft = parsed.finalType && typeof parsed.finalType === 'object' ? parsed.finalType : null
    return String(parsed.desc ?? ft?.desc ?? '')
  }

  if (t === 'face' || t === 'ai') {
    const parts = [
      parsed.relationship,
      parsed.faceAnalysis && typeof parsed.faceAnalysis === 'string' ? parsed.faceAnalysis : '',
      parsed.boneAnalysis && typeof parsed.boneAnalysis === 'string' ? parsed.boneAnalysis : ''
    ]
      .map((x) => String(x || '').trim())
      .filter(Boolean)
    const extra = parts.length ? `\n\n${parts.join('\n\n')}` : ''
    return String(parsed.overview ?? parsed.personalitySummary ?? '') + extra
  }

  if (t === 'resume') {
    // v2 结构化 JSON
    if (parsed.version === 2 || parsed.overview) {
      return String(parsed.overview ?? '')
    }
    return String(parsed.content ?? '')
  }

  return ''
}

function handlePageChange() {
  loadUsers()
}

function exportData() {
  ElMessage.info('导出功能开发中')
}

function normalizeDetailUser(payload: any) {
  const data = payload ?? {}
  if (data.user) {
    const user = data.user || {}
    const extra = { ...data }
    delete extra.user
    const merged: any = { ...user, ...extra }
    merged.testList = data.testList ?? data.tests ?? user.testList ?? user.tests ?? []
    merged.mbtiType = merged.mbtiType ?? user.mbtiType
    merged.pdpType = merged.pdpType ?? user.pdpType
    merged.discType = merged.discType ?? user.discType
    merged.sbtiType = merged.sbtiType ?? user.sbtiType
    merged.faceType = merged.faceType ?? user.faceType
    return merged
  }
  return data
}

async function loadDetailUser(userId: number): Promise<boolean> {
  try {
    detailLoading.value = true
    const res: any = await request.get(`/admin/app-users/${userId}`)
    const raw = res?.data ?? res
    if (!raw || typeof raw !== 'object') {
      ElMessage.warning('用户数据为空，请刷新后重试')
      return false
    }
    detailUser.value = normalizeDetailUser(raw)
    return true
  } catch (e: any) {
    ElMessage.warning(e?.message || '获取用户详情失败，请稍后重试')
    return false
  } finally {
    detailLoading.value = false
  }
}

async function handleView(row: any) {
  if (!row?.id) return
  detailUser.value = null
  showDetailDialog.value = true
  const ok = await loadDetailUser(row.id)
  if (!ok) {
    showDetailDialog.value = false
  }
}

async function handleViewTest(row: any) {
  const testId = row?.id != null ? Number(row.id) : NaN
  if (!Number.isFinite(testId) || testId <= 0) {
    ElMessage.warning('测试记录缺少有效 ID')
    return
  }
  currentTest.value = null
  showTestDetailDialog.value = true
  testDetailLoading.value = true
  try {
    const res: any = await request.get(`/admin/test-records/${testId}`)
    const payload = res?.data != null ? res.data : res
    if (payload && typeof payload === 'object' && (payload.testType != null || payload.result != null)) {
      currentTest.value = payload
    } else {
      ElMessage.warning('未获取到测试详情')
      showTestDetailDialog.value = false
    }
  } catch {
    showTestDetailDialog.value = false
  } finally {
    testDetailLoading.value = false
  }
}

async function handleClickTestTag(row: any, testType: string) {
  // 仅加载用户测试记录，弹出单次测试详情对话框并显示加载动画
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
  // 并行加载：分销商 ID 集合 + 用户列表
  void loadDistributorIds()
  void loadUsers()
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

.profile-summary {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 16px;

  &.profile-summary--five {
    grid-template-columns: repeat(5, minmax(0, 1fr));
    @media (max-width: 1100px) {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  &.profile-summary--six {
    grid-template-columns: repeat(6, minmax(0, 1fr));
    @media (max-width: 1280px) {
      grid-template-columns: repeat(3, minmax(0, 1fr));
    }
    @media (max-width: 900px) {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  @media (max-width: 900px) {
    grid-template-columns: repeat(2, 1fr);
  }

  .summary-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    background: #fff;
    border: 1px solid #eef2f7;
    border-radius: 10px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    transition: all 0.2s;

    &:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(99, 102, 241, 0.08);
    }
  }

  .summary-ic {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
    flex-shrink: 0;

    &.ic-blue   { background: #eff6ff; color: #2563eb; }
    &.ic-teal   { background: #f0fdfa; color: #0d9488; }
    &.ic-indigo { background: #eef2ff; color: #4f46e5; }
    &.ic-violet { background: #f5f3ff; color: #7c3aed; }
    &.ic-amber  { background: #fffbeb; color: #d97706; }
    &.ic-coop   { background: #f0fdf4; color: #16a34a; }

    svg { flex-shrink: 0; }
  }

  .summary-body {
    min-width: 0;
  }

  .summary-label {
    font-size: 12px;
    color: #6b7280;
    margin-bottom: 4px;
  }

  .summary-value {
    font-size: 22px;
    font-weight: 700;
    color: #111827;
    line-height: 1;
    font-variant-numeric: tabular-nums;

    .summary-sub {
      font-size: 12px;
      font-weight: 500;
      color: #9ca3af;
      margin-left: 6px;
    }
  }

  .summary-foot {
    margin-top: 8px;
    font-size: 12px;
    color: #6b7280;
    line-height: 1.4;
    font-weight: 400;
  }
}

.search-section {
  margin-bottom: 20px;
  display: flex;
  gap: 12px;
  align-items: center;
  flex-wrap: wrap;

  .coldface-select {
    width: 180px;
  }

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
  width: 100%;

  :deep(.el-table__row) {
    cursor: pointer;
    transition: background 0.15s;
    &:hover td { background: #f5f7ff !important; }
  }
}

  :deep(.el-table__body) {
    td {
      padding: 12px 16px;
    }
  }

  .coop-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
    line-height: 1.35;
  }
  .coop-title {
    font-size: 13px;
    font-weight: 500;
    color: #111827;
  }
  .coop-code {
    font-size: 12px;
    color: #6b7280;
  }
  .coop-time {
    font-size: 12px;
    color: #9ca3af;
  }
  .coop-empty {
    color: #d1d5db;
  }

  .user-info-cell {
    display: flex;
    align-items: center;
    gap: 12px;

    .user-avatar {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      flex-shrink: 0;
    }

    .user-avatar-img {
      object-fit: cover;
      border: 1px solid #e5e7eb;
      background: #f3f4f6;
    }

    .user-avatar-letter {
      background-color: #7c3aed;
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
      font-weight: 600;
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
      cursor: pointer;
  }

    :deep(.tag-sbti.el-tag) {
      --el-tag-bg-color: #f5f3ff;
      --el-tag-border-color: #ddd6fe;
      --el-tag-text-color: #5b21b6;
    }

    .no-test {
    font-size: 13px;
      color: #9ca3af;
    }
  }

  .contact-cell {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 0;

    .phone { color: #0f172a; font-size: 13px; font-weight: 500; }
    .openid-line { color: #94a3b8; font-size: 11px; font-family: 'SF Mono', Menlo, monospace; }
  }

  .activity-cell {
    font-size: 13px;
    color: #334155;

    strong {
      color: #4f46e5;
      font-weight: 600;
      font-variant-numeric: tabular-nums;
    }

    .activity-row.sub { color: #94a3b8; font-size: 12px; margin-top: 2px; }
  }

  .time-cell {
    font-size: 13px;
    color: #374151;
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

.test-pagination {
  margin-top: 12px;
  display: flex;
  justify-content: center;
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

/* ── 用户列新增字段 ── */
.user-name-row {
  display: flex;
  align-items: center;
  gap: 5px;
}

.user-id {
  font-size: 11px;
  color: #9ca3af;
  margin-top: 1px;
}

.dist-badge {
  display: inline-flex;
  align-items: center;
  padding: 1px 6px;
  border-radius: 4px;
  font-size: 10px;
  font-weight: 700;
  background: #fef3c7;
  color: #92400e;
  border: 1px solid #fde68a;
  flex-shrink: 0;
}

.distributor-dot {
  position: absolute;
  bottom: 0;
  right: 0;
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: #f59e0b;
  border: 2px solid #fff;
}

.col-dot {
  display: inline-flex;
  align-items: center;
  justify-content: center;

  &--yes {
    width: 22px;
    height: 22px;
    border-radius: 50%;
    background: #ecfdf5;
    color: #16a34a;
  }

  &--no {
    color: #d1d5db;
    font-size: 14px;
  }
}

.test-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}

.chip {
  display: inline-flex;
  align-items: center;
  padding: 2px 7px;
  border-radius: 5px;
  font-size: 10.5px;
  font-weight: 600;
  white-space: nowrap;

  &--mbti { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
  &--sbti { background: #f5f3ff; color: #6d28d9; border: 1px solid #ddd6fe; }
  &--disc { background: #fdf4ff; color: #7e22ce; border: 1px solid #e9d5ff; }
  &--pdp  { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
}

/* ── 用户信息列胶囊标签（旧，保留兼容） ── */
.user-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
  margin-top: 4px;
}

.upill {
  display: inline-flex;
  align-items: center;
  padding: 1px 7px;
  border-radius: 999px;
  font-size: 11px;
  font-weight: 500;
  line-height: 1.6;
  white-space: nowrap;

  &--phone {
    background: #eff6ff;
    color: #2563eb;
    border: 1px solid #bfdbfe;
  }
  &--resume {
    background: #f0fdf4;
    color: #16a34a;
    border: 1px solid #bbf7d0;
  }
  &--face {
    background: #fdf4ff;
    color: #7e22ce;
    border: 1px solid #e9d5ff;
  }
  &--empty {
    background: #f9fafb;
    color: #9ca3af;
    border: 1px solid #e5e7eb;
  }
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

.test-detail-disc .test-detail-main-card {
  flex-wrap: wrap;
  align-items: flex-start;
}

.test-detail-main-left {
  flex: 1;
}

.test-detail-main-right {
  width: min(100%, 300px);
  min-width: 0;
  flex-shrink: 0;
}

.test-detail-main-card--sbti {
  background-color: #f2f7f3;
  border: 1px solid #e3ebe6;
  flex-wrap: wrap;
  align-items: flex-start;
}

.sbti-type-line {
  display: flex;
  flex-wrap: wrap;
  align-items: baseline;
  gap: 6px;
  margin-bottom: 8px;
}

.sbti-code-badge {
  display: inline-flex;
  padding: 4px 10px;
  border-radius: 8px;
  background: #e8efe9;
  color: #5a7268;
  font-weight: 700;
  font-size: 14px;
}

.sbti-cn {
  font-size: 15px;
  font-weight: 600;
  color: #374c40;
}

.sbti-intro {
  font-size: 13px;
  color: #5a7268;
  margin: 0 0 8px;
}

.sbti-long-desc {
  font-size: 13px;
  color: #4b5563;
  line-height: 1.6;
  margin: 0;
  white-space: pre-wrap;
}

.test-detail-sbti-right {
  width: 160px;
  flex-shrink: 0;
}

.sbti-result-img {
  width: 160px;
  height: 160px;
}

.sbti-img-fallback {
  font-size: 12px;
  color: #5a7268;
  padding: 8px;
  line-height: 1.4;
}

.sbti-level-tags {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
}

.sbti-level-tag {
  --el-tag-bg-color: #f2f7f3;
  --el-tag-border-color: #dbe8e0;
  --el-tag-text-color: #5a7268;
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
  border: 1px solid #e0e7ff;
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
  gap: 8px;
}

.disc-bar-row {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 22px;
}

.disc-bar-label {
  display: flex;
  flex-direction: row;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
  min-width: 72px;
}

.disc-bar-letter {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 22px;
  height: 22px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 800;
  line-height: 1;
  color: #fff;
}

.disc-bar-letter-d {
  background: linear-gradient(135deg, #f87171 0%, #ef4444 100%);
}

.disc-bar-letter-i {
  background: linear-gradient(135deg, #fbbf24 0%, #eab308 100%);
}

.disc-bar-letter-s {
  background: linear-gradient(135deg, #4ade80 0%, #22c55e 100%);
}

.disc-bar-letter-c {
  background: linear-gradient(135deg, #60a5fa 0%, #3b82f6 100%);
}

.disc-bar-cn {
  font-size: 12px;
  font-weight: 500;
  color: #4b5563;
  white-space: nowrap;
  letter-spacing: 0.02em;
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
  width: min(100%, 340px);
  flex-shrink: 0;
  position: sticky;
  top: 0;
  align-self: flex-start;
  height: fit-content;
}

.face-photos {
  display: grid;
  gap: 8px;
  width: 100%;

  :deep(.face-photo-thumb.el-image) {
    display: block;
    width: 100%;
    aspect-ratio: 3 / 4;
    max-height: 220px;
    border-radius: 8px;
    overflow: hidden;
    background: #f1f5f9;
    cursor: zoom-in;
  }

  :deep(.face-photo-thumb .el-image__inner) {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover;
  }

  :deep(.face-photo-thumb .el-image__error) {
    min-height: 120px;
    font-size: 12px;
  }
}

.face-photos--1 {
  grid-template-columns: 1fr;
  max-width: 280px;
}

.face-photos--2 {
  grid-template-columns: repeat(2, 1fr);
}

.face-photos--3 {
  grid-template-columns: repeat(3, 1fr);
}

.face-right {
  flex: 1;
}

.test-detail-row {
  display: flex;
  gap: 12px;
  margin-bottom: 12px;
}

.compact-row .test-detail-block {
  margin-bottom: 0;
  padding: 12px;
  background-color: #f9fafb;
  border-radius: 8px;
}

.flex-1 {
  flex: 1;
}

.text-primary {
  color: #4f46e5;
  font-weight: 600;
}

.text-secondary {
  color: #6b7280;
  font-weight: normal;
}

.face-gallup-list {
  margin: 6px 0 0 18px;
  padding: 0;
  font-size: 13px;
  color: #374151;
  line-height: 1.6;
}

.face-feature-list {
  margin: 6px 0 0;
  padding-left: 18px;
  font-size: 13px;
  color: #4b5563;
  line-height: 1.65;

  li {
    margin-bottom: 6px;
  }
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
</style>
