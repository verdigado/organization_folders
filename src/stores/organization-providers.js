import { defineStore } from "pinia";
import api from "../api.js";

export const useOrganizationProvidersStore = defineStore("organizationProviders", {
    state: () => ({
        initialized: false,
        providers: [],
    }),
    actions: {
        async initialize() {
            if(!this.initialized) {
                this.providers = await api.getOrganizationProviders();
            }
        },

        async refresh() {
            this.providers = await api.getOrganizationProviders();
        }
    },
});
