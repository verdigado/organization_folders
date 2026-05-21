<script setup>
import { computed, ref } from "vue";

import NcButton from "@nextcloud/vue/components/NcButton";
import NcLoadingIcon from "@nextcloud/vue/dist/Components/NcLoadingIcon.js";

import Check from "vue-material-design-icons/Check.vue";
import Cancel from "vue-material-design-icons/Cancel.vue";
import HelpCircle from "vue-material-design-icons/HelpCircle.vue";

const props = defineProps({
	locked: {
		type: Boolean,
		default: false,
	},
	label: {
		type: String,
		default: "",
	},
	explanation: {
		type: String,
	},
	value: {
		type: Object,
	},
});

const emit = defineEmits(["change"]);

const tooltipAllow = t("organization_folders", "Allowed");
const tooltipDenied = t("organization_folders", "Denied");
const labelAllowed = t("organization_folders", "Access allowed");
const labelDenied = t("organization_folders", "Access denied");

const loading = ref(false);

const onClick = (permissionKey) => {
	if(!props.locked) {
		loading.value = permissionKey;
		emit("change", { [permissionKey]: !props.value[permissionKey] }, () => {
			loading.value = false;
		});
	}
};
</script>

<template>
	<tr>
		<td>
			<div style="display: flex; align-items: center; justify-items: center;">
				<span>{{ props.label }}</span>
				<HelpCircle v-if="props.explanation"
					v-tooltip="props.explanation"
					style="margin-left: 5px;"
					:size="15" />
			</div>
		</td>
		<th />
		<td v-for="(permissionValue, permissionKey) in value" :key="permissionKey" class="buttonTd">
			<NcButton v-tooltip="permissionValue ? tooltipAllow : tooltipDenied"
				:aria-label="permissionValue ? labelAllowed : labelDenied"
				@click="() => onClick(permissionKey)">
				<template #icon>
					<NcLoadingIcon v-if="loading === permissionKey" />
					<component v-else :is="permissionValue ? Check : Cancel" :size="16" />
				</template>
			</NcButton>
		</td>
	</tr>
</template>

<style scoped>
	.inherited {
		opacity: 0.5;
	}
	:deep(.button-vue) {
		margin: 3px;
	}
</style>
