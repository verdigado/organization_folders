<script setup>
import { ref } from "vue";

import NcDialog from "@nextcloud/vue/components/NcDialog";
import NcButton from "@nextcloud/vue/components/NcButton";
import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";

import Cancel from "vue-material-design-icons/Cancel.vue";
import FolderMove from "vue-material-design-icons/FolderMove.vue";

import ResourcePicker from "./ResourcePicker.vue";

import api from "../api";

const emit = defineEmits(["update:open", "move"]);

const props = defineProps({
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

const organizationFolder = ref(null);

api.getOrganizationFolder(props.resource.organizationFolderId, "model")
	.then((result) => {
		console.log(result);
		organizationFolder.value = result;
	});

const currentPickedResource = ref(null);

const resourcePicked = (resource) => {
	console.log(resource);
	currentPickedResource.value = resource;
};

const updateOpen = (newValue) => {
	emit('update:open', newValue);
};

const dialogCancel = () => {
	emit("update:open", false);
};

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
		:name="t('organization_folders', 'Move Resource')"
		size="large"
		@update:open="updateOpen">
		<ResourcePicker v-if="organizationFolder"
			:organization-folder="organizationFolder"
			:initial-resource-id="props.resource.parentResource"
			:require-full-permissions="true"
			:resource-blacklist="[props.resource.id]"
			@picked="resourcePicked" />
		<template #actions>
			<NcButton @click="dialogCancel">
				<template #icon>
					<Cancel :size="20" />
				</template>
				{{ t("organization_folders", "Cancel") }}
			</NcButton>
			<NcButton v-if="organizationFolder" :disabled="(currentPickedResource?.id ?? null) === props.resource.parentResource" @click="dialogConfirm">
				<template #icon>
					<NcLoadingIcon v-if="loading" />
					<FolderMove v-else :size="20" />
				</template>
				{{ t("organization_folders", "Move to {target}", { target: currentPickedResource?.name ?? organizationFolder?.name }) }}
			</NcButton>
		</template>
	</NcDialog>
</template>