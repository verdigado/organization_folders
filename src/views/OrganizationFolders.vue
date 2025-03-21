<script setup>
import { ref, computed, watch } from "vue";
import { useRouter } from 'vue2-helpers/vue-router';

import NcListItem from "@nextcloud/vue/components/NcListItem";
import NcEmptyContent from "@nextcloud/vue/components/NcEmptyContent";

import Folder from "vue-material-design-icons/Folder.vue";
import FolderOff from "vue-material-design-icons/FolderOff.vue";

import ModalView from '../ModalView.vue';

import api from "../api.js";
import { validOrganizationFolderName } from "../helpers/validation.js";

const loading = ref(true);

const organizationFolders = ref([]);

const router = useRouter();

api.getOrganizationFolders().then((result) => {
    organizationFolders.value = result;
    loading.value = false;
});

const organizationFolderClicked = (organizationFolder) => {
	router.push({
		path: '/OrganizationFolder/' + organizationFolder.id,
	});
};

</script>

<template>
	<ModalView
		:has-back-button="false"
		:has-next-step-button="false"
		:has-last-step-button="false"
		:title="'Organization Folder Admin Settings'"
		:loading="loading"
		v-slot="">
        <NcEmptyContent v-if="organizationFolders.length === 0" name="Keine Organization Folder vorhanden">
            <template #icon>
                <FolderOff />
            </template>
        </NcEmptyContent>
		<ul v-else>
			<NcListItem v-for="organizationFolder in organizationFolders"
				:key="organizationFolder.id"
				class="material_you"
				:name="organizationFolder.name"
				:linkAriaLabel="organizationFolder.name"
				@click="() => organizationFolderClicked(organizationFolder)">
				<template #icon>
					<Folder :size="44" />
				</template>
			</NcListItem>
		</ul>
    </ModalView>
</template>