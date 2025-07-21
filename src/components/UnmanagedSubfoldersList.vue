<script setup>
import { ref, computed } from "vue";

import NcListItem from "@nextcloud/vue/components/NcListItem";
import NcDialog from "@nextcloud/vue/components/NcDialog";
import NcButton from "@nextcloud/vue/components/NcButton";
import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";

import Folder from "vue-material-design-icons/Folder.vue";
import Cancel from "vue-material-design-icons/Cancel.vue";
import Check from "vue-material-design-icons/Check.vue";

import { translate as t, translatePlural as n } from "@nextcloud/l10n";

const emit = defineEmits(["promote-subfolder"]);

const props = defineProps({
	resource: {
		type: Object,
		required: true,
	},
	unmanagedSubfolders: {
		type: Array,
		required: true,
	},
});

const loading = ref(false);
const selectedSubfolder = ref(null);

const dialogCancel = () => {
	selectedSubfolder.value = null;
}

const dialogConfirm = () => {
	loading.value = true;
	emit("promote-subfolder", selectedSubfolder.value, (success, error) => {
		if(success) {
			selectedSubfolder.value = null;
		}

		loading.value = false;
	});
};

const dialogMessage = computed(() => {
	return t(
		"organization_folders",
		'The folder "{folderName}" is currently unmanaged, meaning users have the same permissions in it as they do in its parent folder "{parentFolderName}".<br>If you need to configure different permissions, you can convert it into a subresource by clicking Confirm. The initial settings will match the current effective permissions.<br><br><b>Only proceed if this folder needs its own permission configuration. This change cannot be undone.</b>',
		{
			folderName: selectedSubfolder.value ?? "",
			parentFolderName: props.resource?.name ?? "",
		}
	);
});

</script>
<template>
	<div class="ignoreForLayout">
		<ul>
			<NcListItem v-for="unmanagedSubfolder in unmanagedSubfolders"
				:key="unmanagedSubfolder"
				class="material_you unmanaged-subfolder-item"
				:name="unmanagedSubfolder"
				:linkAriaLabel="unmanagedSubfolder"
				:force-display-actions="true"
				@click="() => selectedSubfolder = unmanagedSubfolder">
				<template #icon>
					<Folder :size="44" />
				</template>
			</NcListItem>
		</ul>
		<NcDialog :open="!!selectedSubfolder"
			:name="t('organization_folders', 'Convert folder into resource')"
			@update:open="selectedSubfolder = null"
			size="normal">
			<p style="margin: 20px;" v-html="dialogMessage"></p>
			<template #actions>
				<NcButton @click="dialogCancel">
					<template #icon>
						<Cancel :size="20" />
					</template>
					{{ t("organization_folders", "Cancel") }}
				</NcButton>
				<NcButton @click="dialogConfirm">
					<template #icon>
						<NcLoadingIcon v-if="loading" />
						<Check v-else :size="20" />
					</template>
					{{ t("organization_folders", "Confirm") }}
				</NcButton>
			</template>
		</NcDialog>
	</div>
</template>
<style scoped>
.list-item__wrapper.unmanaged-subfolder-item {
	padding-left: 0px;
    padding-right: 0px;
}
</style>