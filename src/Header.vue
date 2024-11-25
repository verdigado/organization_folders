<script setup>
import { ref, inject, watch, computed, nextTick } from "vue";
import NcButton from "@nextcloud/vue/dist/Components/NcButton.js";
import NcLoadingIcon from "@nextcloud/vue/dist/Components/NcLoadingIcon.js";
import FolderCog from "vue-material-design-icons/FolderCog.vue";

import router from "./router.js";
import { useCurrentDirStore } from "./stores/current-dir.js";
import Modal from "./Modal.vue";

console.log("router loggg", router, router.currentRoute);

const currentDir = useCurrentDirStore();

const modalOpen = ref(false);

function openModal() {
    if(currentDir.organizationFolderResourceId && currentDir.organizationFolderResourceUpdatePermissions) {
        router.push({
            path: '/resource/' + currentDir.organizationFolderResourceId
        });
        modalOpen.value = true;
    } else if(currentDir.organizationFolderId && currentDir.organizationFolderUpdatePermissions) {
        router.push({
            path: '/organizationFolder/' + currentDir.organizationFolderId
        });
        modalOpen.value = true;
    }
}

</script>

<template>
    <div v-if="currentDir.organizationFolderUpdatePermissions || currentDir.organizationFolderResourceUpdatePermissions" class="toolbar">
        <NcButton :disabled="currentDir.loading"
            type="primary"
            @click="openModal">
            <template #icon>
                <NcLoadingIcon v-if="currentDir.loading" />
                <FolderCog v-else :size="20" />
            </template>
            Ordner und Berechtigungen Verwalten
		</NcButton>
        <Modal :open.sync="modalOpen" />
    </div>
</template>

<style scoped>
	.spacer {
		flex-grow: 1;
	}
	.toolbar {
		margin: 15px;
		display: flex;
		justify-content: space-between;
	}
</style>
