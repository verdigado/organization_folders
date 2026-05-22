<script setup>
import { ref } from "vue";

import { showSuccess } from "@nextcloud/dialogs";
import { translate as t } from "@nextcloud/l10n";

import NcButton from "@nextcloud/vue/components/NcButton";
import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";
import NcAvatar from "@nextcloud/vue/components/NcAvatar";

import Delete from "vue-material-design-icons/Delete.vue";
import ContentCopy from "vue-material-design-icons/ContentCopy.vue";

const props = defineProps({
	linkShare: {
		type: Object,
		required: true,
	},
});

const emit = defineEmits(["delete"]);

const deletionLoading = ref(false);

const onDeleteClicked = (e) => {
	deletionLoading.value = true;
	emit("delete", props.linkShare.id, () => {
		deletionLoading.value = false;
	});
};

const onCopyClicked = () => {
    navigator.clipboard.writeText(props.linkShare.linkUrl);
    showSuccess(t("organization_folders", "Link copied"));
};
</script>

<template>
	<tr>
		<td style="display: contents;">
            <NcAvatar :disable-menu="true"
                :disable-tooltip="true"
                :size="34"
                icon-class="icon-link" />
		</td>
		<td>
			{{ props.linkShare.name }}
		</td>
        <td>
			<NcButton type="tertiary-no-background" :aria-label="t('organization_folders', 'Copy link')" @click="onCopyClicked">
				<template #icon>
					<ContentCopy :size="20" />
				</template>
			</NcButton>
		</td>
		<td>
			<NcButton type="tertiary-no-background" :aria-label="t('organization_folders', 'Delete link share')" @click="onDeleteClicked">
				<template #icon>
					<NcLoadingIcon :size="20" v-if="deletionLoading" />
					<Delete :size="20" v-else />
				</template>
			</NcButton>
		</td>
	</tr>
</template>
<style lang="scss" scoped>
td {
	padding: 8px;
}
</style>
