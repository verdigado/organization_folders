import Vue from "vue";
import App from "./AdminSettings.vue";

Vue.mixin({ methods: { t, n } })

export default new Vue({
    el: '#organization_folders_admin_settings',
    render: h => h(App),
})
