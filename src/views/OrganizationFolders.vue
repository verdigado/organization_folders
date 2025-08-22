<script setup>
import { ref, computed, watch } from "vue";
import { useRouter } from 'vue2-helpers/vue-router';

import NcListItem from "@nextcloud/vue/components/NcListItem";
import NcEmptyContent from "@nextcloud/vue/components/NcEmptyContent";
import NcTextField from "@nextcloud/vue/components/NcTextField";

import Magnify from "vue-material-design-icons/Magnify.vue";
import Folder from "vue-material-design-icons/Folder.vue";
import FolderOff from "vue-material-design-icons/FolderOff.vue";

import ModalView from '../ModalView.vue';
import PageSelector from "../components/PageSelector.vue";
import CreateOrganizationFolderButton from "../components/CreateOrganizationFolderButton.vue";

import api from "../api.js";
import { validOrganizationFolderName } from "../helpers/validation.js";

const loading = ref(true);

const organizationFolders = ref([]);

const router = useRouter();

const page = ref(1);

const search = ref("");

const filteredOrganizationFolders = computed(() => {
	return organizationFolders.value.filter((folder) => folder.name.toLowerCase().includes(search.value.toLowerCase()));
});

const pageLimit = 50;

const maxPage = computed(() => {
	return Math.ceil(Math.max(filteredOrganizationFolders.value.length, 1) / pageLimit) || 1;
});

const paginateArray = (array, page, pageLimit) => {
  const startIndex = (page - 1) * pageLimit;
  const endIndex = startIndex + pageLimit;
  return array.slice(startIndex, endIndex);
};

const paginatedFilteredOrganizationFolders = computed(() => {
	return paginateArray(filteredOrganizationFolders.value, page.value, pageLimit);
});

const ensurePageIsNotExceedingMax = () => {
	if (page.value > maxPage.value) {
		page.value = maxPage.value;
	}
};

watch(maxPage, () => {
	ensurePageIsNotExceedingMax();
});

api.getOrganizationFolders().then((result) => {
	organizationFolders.value = result;
	loading.value = false;
});

const organizationFolderClicked = (organizationFolder) => {
	router.push({
		path: '/organizationFolder/' + organizationFolder.id,
	});
};

const emptyContentMessage = computed(() => {
	if(search.value === "") {
		return t("organization_folders", "No organization folders yet");
	} else {
		return t("organization_folders", "No search results");
	}
});

const createOrganizationFolder = async (name, quota) => {
	organizationFolderClicked(await api.createOrganizationFolder({ name, quota }));
};

</script>

<template>
	<ModalView
		:has-back-button="false"
		:has-next-step-button="false"
		:has-previous-step-button="false"
		:title="t('organization_folders', 'Organization Folders Admin Settings')"
		:loading="loading"
		v-slot="">
		<div style="display: flex; flex-direction: row; column-gap: 5px; margin-bottom: 10px;">
			<NcTextField :value.sync="search"
				:label="t('organization_folders', 'Search')"
				trailing-button-icon="close"
				:show-trailing-button="search !== ''"
				@trailing-button-click="search = ''">
				<Magnify :size="16" />
			</NcTextField>
			<PageSelector :page.sync="page" :max-page="maxPage" />
			<CreateOrganizationFolderButton @add-organization-folder="createOrganizationFolder" />
		</div>
		<NcEmptyContent v-if="filteredOrganizationFolders.length === 0" :name="emptyContentMessage">
			<template #icon>
				<FolderOff />
			</template>
		</NcEmptyContent>
		<ul v-else>
			<NcListItem v-for="organizationFolder in paginatedFilteredOrganizationFolders"
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