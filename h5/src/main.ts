import { createApp } from 'vue'
import { createPinia } from 'pinia'
import {
  Button, Form, Field, CellGroup, Cell, Image as VanImage,
  Tab, Tabs, Toast, Loading, Skeleton, Empty, PullRefresh,
  List, Popup, Picker, DatePicker, NavBar, Icon, Badge,
  Swipe, SwipeItem, Tag, Progress, Uploader, ImagePreview,
  ActionSheet, Dialog, Notify, Overlay, Rate
} from 'vant'
import 'vant/lib/index.css'
import './styles/global.css'
import App from './App.vue'
import router from './router'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Vant 组件
;[
  Button, Form, Field, CellGroup, Cell, VanImage,
  Tab, Tabs, Toast, Loading, Skeleton, Empty, PullRefresh,
  List, Popup, Picker, DatePicker, NavBar, Icon, Badge,
  Swipe, SwipeItem, Tag, Progress, Uploader, ImagePreview,
  ActionSheet, Dialog, Notify, Overlay, Rate
].forEach(c => app.use(c))

app.mount('#app')
