<script setup>
import { ref, computed } from "vue";

import NcDialog from "@nextcloud/vue/components/NcDialog";
import NcButton from "@nextcloud/vue/components/NcButton";
import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";

import Cancel from "vue-material-design-icons/Cancel.vue";
import Check from "vue-material-design-icons/Check.vue";

import { translate as t, translatePlural as n } from "@nextcloud/l10n";

const emit = defineEmits(["cancel", "continue"]);

const props = defineProps({
	resource: {
		type: Object,
		required: true,
	},
	open: {
		type: Boolean,
		required: true,
	},
});

const loading = ref(false);

const dialogMessage = computed(() => {
	if(props.resource?.subResources?.length > 0) {
		return t(
			"organization_folders",
			'This action would revoke your own management permissions in "{resourceName}" (and potentially also in some subresources).<br><br><b>Do you want to proceed ?</b>',
			{
				resourceName: props.resource?.name ?? "",
			}
		);
	} else {
		return t(
			"organization_folders",
			'This action would revoke your own management permissions in "{resourceName}".<br><br><b>Do you want to proceed ?</b>',
			{
				resourceName: props.resource?.name ?? "",
			}
		);
	}
	
});

const dialogCancel = () => {
	emit("cancel");
}

const dialogConfirm = () => {
	loading.value = true;	
	emit("continue", () => {
		loading.value = false;
	});
};

</script>

<template>
	<NcDialog :open="open"
		:name="t('organization_folders', 'Warning')"
		@update:open="dialogCancel"
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
				{{ t("organization_folders", "Continue anyway") }}
			</NcButton>
		</template>
	</NcDialog>
</template>