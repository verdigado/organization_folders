<script setup>
import { ref, computed } from "vue";

import NcDialog from "@nextcloud/vue/components/NcDialog";
import NcButton from "@nextcloud/vue/components/NcButton";
import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";
import { translate as t } from "@nextcloud/l10n";

import Cancel from "vue-material-design-icons/Cancel.vue";
import FolderMove from "vue-material-design-icons/FolderMove.vue";

import ResourcePicker from "./ResourcePicker.vue";

import api from "../api";

const emit = defineEmits(["update:open", "move"]);

const props = defineProps({
	organizationFolder: {
		type: Object,
		required: true,
	},
	resource: {
		type: Object,
		required: true,
	},
	open: {
		type: Boolean,
		required: true,
	}
});

const loading = ref(false);

const currentPickedResource = ref(null);

const resourceFilter = (resource) => {
	return api.SubresourceSupportByType[resource.type] && resource.id !== props.resource.id;
};

const moveResourceText = computed(() => {
	if(props.resource.value?.type === api.ResourceTypes.FOLDER) {
		return t("organization_folders", "Move folder");
	} else if(props.resource.value?.type === api.ResourceTypes.CALENDAR) {
		return t("organization_folders", "Move calendar");
	} else {
		return "";
	}
});

const resourcePicked = (resource) => {
	currentPickedResource.value = resource;
};

const updateOpen = (newValue) => {
	emit('update:open', newValue);
};

const dialogCancel = () => {
	emit("update:open", false);
};

const currentPickedResourceDifferentToCurrentParent = computed(() => {
	if(currentPickedResource.value) {
		return currentPickedResource.value.id !== props.resource.parentResourceId;
	} else {
		return props.resource.parentResourceId !== null;
	}
	
});

const currentPickedResourceUserHasRequiredPermission = computed(() => {
	if(currentPickedResource.value) {
		return currentPickedResource.value?.userApiPermissions?.CREATE_SUBRESOURCE?.granted ?? false;
	} else {
		return props.organizationFolder?.userApiPermissions?.CREATE_TOP_LEVEL_RESOURCE?.granted ?? false;
	}
	
});

const currentPickedResourceValidTarget = computed(() => {
	return currentPickedResourceDifferentToCurrentParent.value && currentPickedResourceUserHasRequiredPermission.value;
});

const currentPickedResourceInvalidTooltip = computed(() => {
	if(!currentPickedResourceDifferentToCurrentParent.value) {
		return t("organization_folders", "Resource cannot be moved to its current location");
	}
	if(!currentPickedResourceUserHasRequiredPermission.value) {
		return t("organization_folders", "You do not have the required permissions to move the resource here");
	}
	return null;
});

const dialogConfirm = () => {
	loading.value = true;
	emit("move", currentPickedResource.value?.id ?? null, () => {
		loading.value = false;
		emit("update:open", false);
	});
};

</script>

<template>
	<NcDialog :open="open"
		:name="moveResourceText"
		size="large"
		@update:open="updateOpen">
		<ResourcePicker :organization-folder="organizationFolder"
			:initial-resource-id="props.resource.parentResourceId"
			:resource-filter="resourceFilter"
			@picked="resourcePicked" />
		<template #actions>
			<NcButton @click="dialogCancel">
				<template #icon>
					<Cancel :size="20" />
				</template>
				{{ t("organization_folders", "Cancel") }}
			</NcButton>
			<NcButton :disabled="!currentPickedResourceValidTarget" v-tooltip="currentPickedResourceInvalidTooltip" @click="dialogConfirm">
				<template #icon>
					<NcLoadingIcon v-if="loading" />
					<FolderMove v-else :size="20" />
				</template>
				{{ t("organization_folders", "Move to {target}", { target: currentPickedResource?.name ?? organizationFolder?.name }) }}
			</NcButton>
		</template>
	</NcDialog>
</template>