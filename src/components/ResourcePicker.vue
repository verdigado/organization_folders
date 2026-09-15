<script setup>
import { ref, computed } from "vue";

import NcBreadcrumbs from "@nextcloud/vue/components/NcBreadcrumbs";
import NcBreadcrumb from "@nextcloud/vue/components/NcBreadcrumb";
import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";

import Folder from "vue-material-design-icons/Folder.vue";

import ResourceList from "./ResourceList.vue";

import api from "../api";

const props = defineProps({
	organizationFolder: {
		type: Object,
		required: true,
	},
	initialResourceId: {
		type: Number,
	},
	resourceFilter: {
		type: Function,
		default: () => (() => true),
	},
});

const emit = defineEmits(["picked"]);

const resourcePath = ref([]);

const currentSubresources = ref([]);

const subresourcesLoading = ref(true);

if(props.initialResourceId) {
	api.getResource(props.initialResourceId, "fullPath+subresources+userApiPermissions")
		.then(({ fullPath, subResources }) => {
			resourcePath.value = fullPath;
			currentSubresources.value = subResources;
			picked();

			subresourcesLoading.value = false;
		});
} else {
	api.getOrganizationFolderResources(props.organizationFolder.id)
		.then((value) => {
			currentSubresources.value = value;
			picked();

			subresourcesLoading.value = false;
		});
}


const currentParentResourceId = computed(() => {
	return resourcePath.value.at(-1)?.id ?? null;
});

const reloadSubresources = async () => {
	subresourcesLoading.value = true;

	if(currentParentResourceId.value) {
		currentSubresources.value = await api.getResourceSubresources(currentParentResourceId.value, "model+userApiPermissions");
	} else {
		currentSubresources.value = await api.getOrganizationFolderResources(props.organizationFolder.id, "model+userApiPermissions");
	}
	
	subresourcesLoading.value = false;
};

const filteredSubresources = computed(() => {
	return currentSubresources.value.filter(props.resourceFilter);
});

const breadcrumbClicked = (index) => {
	resourcePath.value = resourcePath.value.slice(0, index);
	picked();
	reloadSubresources();
};

const resourceClicked = (resource) => {
	resourcePath.value.push(resource);
	picked();
	reloadSubresources();
};

const picked = () => {
	emit("picked", resourcePath.value.at(-1));
}

</script>

<template>
	<div style="display: flex; flex-direction: column; height: 100%;">
		<NcBreadcrumbs v-if="organizationFolder">
			<template #default>
				<NcBreadcrumb :name="organizationFolder.name"
					:title="organizationFolder.name"
					:disableDrop="true"
					:forceIconText="true"
					@click="breadcrumbClicked(0)">
					<template #icon>
						<Folder :size="20" />
					</template>
				</NcBreadcrumb>
				<NcBreadcrumb v-for="(part, index) in resourcePath" :key="part.id"
					:name="part.name"
					:title="part.name"
					:disableDrop="true"
					@click="breadcrumbClicked(index + 1)" />
			</template>
		</NcBreadcrumbs>
		<div style="display: flex; flex-direction: column; overflow-y: scroll;">
			<NcLoadingIcon v-if="subresourcesLoading" :size="50" />
			<ResourceList v-else :resources="filteredSubresources" @click:resource="resourceClicked" />
		</div>
	</div>
</template>