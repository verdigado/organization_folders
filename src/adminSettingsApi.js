import axios from "@nextcloud/axios"
import { generateUrl } from "@nextcloud/router"

axios.defaults.baseURL = generateUrl("/apps/organization_folders")

export const adminSettingsApi = {
    getAllSettings() {
        return axios.get("/adminSettings").then((res) => res.data)
    },
    getSetting(key) {
        return axios.get("/adminSettings/" + key).then((res) => res.data)
    },
    setSetting(key, value) {
        return axios.patch("/adminSettings/" + key, { value }).then((res) => res.data)
    },
}
