<script setup>
import { ref, inject, watch, computed, nextTick } from "vue";
import { getCurrentUser } from "@nextcloud/auth";
import { loadState } from "@nextcloud/initial-state";

import NcButton from "@nextcloud/vue/components/NcButton";
import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";

import FolderCog from "vue-material-design-icons/FolderCog.vue";

import router from "./router.js";
import { useCurrentDirStore } from "./stores/current-dir.js";
import Modal from "./Modal.vue";

const currentDir = useCurrentDirStore();

const modalOpen = ref(false);

const userIsAdmin = ref(getCurrentUser().isAdmin);
const subresourcesEnabled = loadState('organization_folders', 'subresources_enabled', false);

const folderLevel = computed(() => {
	return currentDir.path.split("/").filter(Boolean).length;
});

const showHeader = computed(() => {
	return ((currentDir.organizationFolderUpdatePermissions || currentDir.organizationFolderReadLimitedPermissions ) && folderLevel.value === 1)
		|| (currentDir.organizationFolderResourceUpdatePermissions && folderLevel.value <= 2)
		|| (userIsAdmin.value && folderLevel.value === 0)
		|| (currentDir.organizationFolderResourceUpdatePermissions && folderLevel.value > 2 && subresourcesEnabled)
});

const buttonText = computed(() => {
    if(currentDir.organizationFolderId) {
        return "Ordner und Berechtigungen verwalten";
    } else if (userIsAdmin) {
        return "Organization Folders verwalten";
    } else {
        return "";
    }
});

function openModal() {
    if(currentDir.organizationFolderResourceId && currentDir.organizationFolderResourceUpdatePermissions) {
        router.push({
            path: '/resource/' + currentDir.organizationFolderResourceId,
        });
        modalOpen.value = true;
    } else if(currentDir.organizationFolderId && (currentDir.organizationFolderUpdatePermissions || currentDir.organizationFolderReadLimitedPermissions)) {
        router.push({
            path: '/organizationFolder/' + currentDir.organizationFolderId,
        });
        modalOpen.value = true;
    } else if (userIsAdmin) {
        router.push({
            path: '/organizationFolders',
        });
        modalOpen.value = true;
    }
}

</script>

<template>
    <div v-if="showHeader" class="toolbar">
        <NcButton :disabled="currentDir.loading"
            type="primary"
            @click="openModal">
            <template #icon>
                <NcLoadingIcon v-if="currentDir.loading" />
                <FolderCog v-else :size="20" />
            </template>
            {{ buttonText }}
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
