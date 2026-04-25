const gaokaoApi = require('../../utils/gaokao')

/** 科类/选科备选项：首项为占位，不可作为有效保存值 */
const SUBJECT_PLACEHOLDER = '请选择科类/选科'
const SUBJECT_CHOICES = [
  SUBJECT_PLACEHOLDER,
  '文科',
  '理科',
  '物化生',
  '物化地',
  '物化政',
  '物生地',
  '物生政',
  '物政地',
  '化生地',
  '化政地',
  '生政地',
  '史政地',
  '史化政',
  '史化生',
  '物化技',
  '物生技',
  '史地技',
  '艺术类（物理向）',
  '艺术类（历史向）',
  '体育类（物理向）',
  '体育类（历史向）',
  '中职/对口/单招'
]

/** 在意向专业中展示/保存的选项：首项表示不填 */
const MAJOR_PLACEHOLDER = '（可选）不填'
const MAJOR_CHOICES = [
  MAJOR_PLACEHOLDER,
  '哲学',
  '经济学 / 金融',
  '法学',
  '教育学 / 师范',
  '文学',
  '外语 / 新传',
  '理学',
  '工学 / 工程',
  '计算机 / 软件 / 人工智能',
  '电子 / 通信 / 信息',
  '医学 / 临床 / 公卫 / 中医',
  '农学 / 林学 / 生科',
  '历史学',
  '管理学 / 商学',
  '艺术学',
  '交叉学科 / 暂未确定'
]

/** 将微信 region 结果格式化为只到「市」的文案（不含区） */
function regionToCityText(v) {
  if (!v || !v.length) return ''
  const p = (v[0] || '').trim()
  const c = (v[1] || '').trim()
  if (p && c) return p + ' ' + c
  return p || c
}

/** 展示用：只取省+市，直辖市 p===c 时只显示一个 */
function formatRegionLine(v) {
  if (!v || !v.length) return ''
  const p = (v[0] || '').trim()
  const c = (v[1] || '').trim()
  if (!p && !c) return ''
  if (c && c !== p) return p + ' · ' + c
  return p || c
}

/** 从 bindchange 取 value（部分环境 detail 结构异常时兜底） */
function regionValueFromEvent(e) {
  const d = (e && e.detail) || {}
  let v = d.value
  if (Array.isArray(v) && v.length) return v
  return []
}

Page({
  data: {
    form: {
      name: '',
      province: '',
      /** 高考生源地所在市（与省同一套 region 选择结果，用于展示/扩展） */
      city: '',
      streamOrSubjects: '',
      /** 与微信 region picker 联动；level=city 时为 [省,市]；老数据或省级可能为 3 项 */
      region: [],
      /**
       * 意向地区：仅省为 [p]；老数据 可能 为 [p,c]；与 preferredRegions 对应
       */
      preferredRegion: [],
      estimatedScore: '',
      scoreText: '',
      wishListText: '',
      preferredRegions: '',
      preferredFields: ''
    },
    /** 高考生源：与 level=city 一致，为 [省,市] */
    regionPickerValue: [],
    /** 意向：只省 */
    intendedProvPicker: [],
    /** 意向：省+市 */
    intendedCityPicker: [],
    /** 0=只到省份 1=到省+市 */
    intendedModeOptions: ['只到省份', '到省+市'],
    intendedModeIndex: 1,
    intendedModeLine: '到省+市',
    /** 地区展示文案（不依赖 wxml 里对 length 的比较，避免真机/模拟器不渲染） */
    regionLine: '',
    intendedRegionLine: '',
    subjectOptions: SUBJECT_CHOICES,
    streamOrSubjectsIndex: 0,
    majorOptions: MAJOR_CHOICES,
    preferredFieldsIndex: 0,
    saving: false
  },

  /** 每次页面展示拉取（含从上级页返回），避免栈内页面不触发 onLoad 时看不到已保存内容 */
  onShow() {
    this.loadFormFromServer()
  },

  loadFormFromServer() {
    gaokaoApi
      .getForm()
      .then((res) => {
        const form = res.form || {}
        const pr = (() => {
          const a = form.preferredRegion
          if (!Array.isArray(a) || a.length < 1) return []
          return a
        })()
        const baseForm = {
          ...this.data.form,
          ...form,
          city: (form.city != null && form.city !== '') ? String(form.city) : (this.data.form.city || ''),
          region: (() => {
            const a = form.region
            if (!Array.isArray(a) || a.length < 2) return []
            return a
          })(),
          preferredRegion: pr,
          preferredRegions: (() => {
            if (pr && pr.length >= 2) {
              return regionToCityText([pr[0], pr[1]])
            }
            if (pr && pr.length === 1) {
              return (pr[0] || '').trim()
            }
            return form.preferredRegions != null ? String(form.preferredRegions) : ''
          })(),
          estimatedScore: form.estimatedScore != null ? String(form.estimatedScore) : ''
        }
        const rpv = baseForm.region
        if (rpv && rpv.length >= 2) {
          baseForm.province = rpv[0] || baseForm.province
          baseForm.city = rpv[1] || baseForm.city
        }
        const rForPicker = (() => {
          if (Array.isArray(baseForm.region) && baseForm.region.length >= 2) {
            const a0 = (baseForm.region[0] || '').trim()
            const a1 = (baseForm.region[1] || a0).trim()
            return [a0, a1]
          }
          if (baseForm.province) {
            const a0 = String(baseForm.province).trim()
            const a1 = (baseForm.city && String(baseForm.city).trim()) || a0
            return [a0, a1]
          }
          return []
        })()
        const regionLine = rForPicker.length
          ? formatRegionLine(rForPicker)
          : ''
        const intendedModeOptions = this.data.intendedModeOptions
        const modeIdx = (() => {
          if (pr && pr.length >= 2) return 1
          if (pr && pr.length === 1) return 0
          return 1
        })()
        const intendedModeLine = intendedModeOptions[modeIdx] || '到省+市'
        const intendedProvPicker = (() => {
          if (pr && pr.length >= 1) return [(String(pr[0] || '')).trim()]
          return []
        })()
        const intendedCityPicker = (() => {
          if (pr && pr.length >= 2) {
            return [
              (String(pr[0] || '')).trim(),
              (String(pr[1] || '')).trim()
            ]
          }
          if (pr && pr.length === 1 && modeIdx === 1) {
            const p0 = (String(pr[0] || '')).trim()
            return p0 ? [p0, p0] : []
          }
          return []
        })()
        let intendedRegionLine = (() => {
          if (pr && pr.length >= 2) {
            return formatRegionLine([(pr[0] || '').trim(), (pr[1] || '').trim()])
          }
          if (pr && pr.length === 1) {
            return (pr[0] || '').trim()
          }
          if (form.preferredRegions) return String(form.preferredRegions)
          return ''
        })()
        const stream = baseForm.streamOrSubjects || ''
        const opts = (() => {
          if (stream && SUBJECT_CHOICES.indexOf(stream) < 0) {
            return [SUBJECT_CHOICES[0], stream, ...SUBJECT_CHOICES.slice(1)]
          }
          return SUBJECT_CHOICES
        })()
        let sIdx = opts.indexOf(stream)
        if (sIdx < 0) sIdx = 0
        const pField = baseForm.preferredFields || ''
        const mOpts = (() => {
          if (pField && MAJOR_CHOICES.indexOf(pField) < 0) {
            return [MAJOR_CHOICES[0], pField, ...MAJOR_CHOICES.slice(1)]
          }
          return MAJOR_CHOICES
        })()
        let mIdx = mOpts.indexOf(pField)
        if (mIdx < 0) mIdx = 0
        this.setData({
          form: baseForm,
          regionPickerValue: rForPicker,
          regionLine,
          intendedModeIndex: modeIdx,
          intendedModeLine,
          intendedProvPicker,
          intendedCityPicker,
          intendedRegionLine,
          subjectOptions: opts,
          streamOrSubjectsIndex: sIdx,
          majorOptions: mOpts,
          preferredFieldsIndex: mIdx
        })
      })
      .catch(() => {
        wx.showToast({ title: '加载表单失败', icon: 'none' })
      })
  },

  onInput(e) {
    const key = e.currentTarget.dataset.key
    this.setData({ [`form.${key}`]: e.detail.value })
  },

  onRegionChange(e) {
    const v = regionValueFromEvent(e)
    if (!v || !v.length) return
    const p = (v[0] || '').trim()
    if (!p) return
    const c2 = v[1] != null && v[1] !== '' ? String(v[1]).trim() : ''
    const pair = [p, c2 || p]
    this.setData({
      regionPickerValue: pair,
      regionLine: formatRegionLine(pair),
      'form.province': p,
      'form.city': c2 || p,
      'form.region': pair
    })
  },

  onSubjectChange(e) {
    const idx = parseInt(e.detail.value, 10) || 0
    const opts = this.data.subjectOptions
    const raw = opts[idx] || ''
    const val =
      raw && raw !== SUBJECT_PLACEHOLDER
        ? raw
        : ''
    this.setData({
      streamOrSubjectsIndex: idx,
      'form.streamOrSubjects': val
    })
  },

  onIntendedModeChange(e) {
    const idx = parseInt(e.detail.value, 10) || 0
    const opts = this.data.intendedModeOptions
    const pr = this.data.form.preferredRegion
    const arr = Array.isArray(pr) ? pr : []
    if (idx === 0) {
      const p = arr[0] ? String(arr[0]).trim() : ''
      const next = p ? [p] : []
      this.setData({
        intendedModeIndex: idx,
        intendedModeLine: opts[idx],
        intendedProvPicker: next,
        intendedRegionLine: p,
        'form.preferredRegion': next,
        'form.preferredRegions': p
      })
      return
    }
    let cityPick = []
    if (arr.length >= 2) {
      cityPick = [String(arr[0] || '').trim(), String(arr[1] || '').trim()]
    } else if (arr.length === 1) {
      const p0 = String(arr[0] || '').trim()
      cityPick = p0 ? [p0, p0] : []
    }
    const hasPair = arr.length >= 2
    this.setData({
      intendedModeIndex: idx,
      intendedModeLine: opts[idx],
      intendedCityPicker: cityPick
    })
    if (hasPair) {
      const line = formatRegionLine(cityPick)
      this.setData({
        intendedRegionLine: line,
        'form.preferredRegion': [cityPick[0], cityPick[1]],
        'form.preferredRegions': regionToCityText(cityPick)
      })
    } else {
      this.setData({
        intendedRegionLine: arr[0] ? String(arr[0]).trim() : '',
        'form.preferredRegion': arr
      })
    }
  },

  onIntendedRegionProv(e) {
    const v = regionValueFromEvent(e)
    if (!v || !v.length) return
    const p = (v[0] || '').trim()
    if (!p) return
    this.setData({
      intendedProvPicker: [p],
      intendedRegionLine: p,
      'form.preferredRegion': [p],
      'form.preferredRegions': p
    })
  },

  onIntendedRegionCity(e) {
    const v = regionValueFromEvent(e)
    if (!v || !v.length) return
    const p = (v[0] || '').trim()
    if (!p) return
    const c2 = v[1] != null && v[1] !== '' ? String(v[1]).trim() : ''
    const pair = [p, c2 || p]
    this.setData({
      intendedCityPicker: pair,
      intendedRegionLine: formatRegionLine(pair),
      'form.preferredRegion': pair,
      'form.preferredRegions': regionToCityText(pair)
    })
  },

  onPreferredFieldChange(e) {
    const idx = parseInt(e.detail.value, 10) || 0
    const opts = this.data.majorOptions
    const raw = opts[idx] || ''
    const val = raw && raw !== MAJOR_PLACEHOLDER ? raw : ''
    this.setData({
      preferredFieldsIndex: idx,
      'form.preferredFields': val
    })
  },

  onSave() {
    const f = this.data.form
    if (!f.name || !f.province || !f.streamOrSubjects) {
      wx.showToast({ title: '请选择姓名、所在地区与科类/选科', icon: 'none' })
      return
    }
    this.setData({ saving: true })
    gaokaoApi.saveForm({
      ...f,
      estimatedScore: f.estimatedScore ? Number(f.estimatedScore) : null
    }).then(() => {
      wx.showToast({ title: '保存成功', icon: 'success' })
      setTimeout(() => wx.navigateBack(), 400)
    }).catch((e) => {
      wx.showToast({ title: e.message || '保存失败', icon: 'none' })
    }).finally(() => this.setData({ saving: false }))
  }
})

