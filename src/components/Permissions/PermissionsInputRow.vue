<script setup>
import { computed, ref } from "vue";

import NcButton from "@nextcloud/vue/components/NcButton";
import NcLoadingIcon from "@nextcloud/vue/dist/Components/NcLoadingIcon.js";

import Check from "vue-material-design-icons/Check.vue";
import Cancel from "vue-material-design-icons/Cancel.vue";
import HelpCircle from "vue-material-design-icons/HelpCircle.vue";

import { calcBits, toggleBit } from "../../helpers/permission-helpers.js";

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
	mask: {
		type: Number,
		default: 31,
	},
	value: {
		type: Number,
		default: 0,
	},
});

const emit = defineEmits(["change"]);

const buttonStates = {
	INHERIT_DENY: {
		tooltipText: t("organization_folders", "Denied (Inherited permission)"),
		ariaLabel: t('organization_folders', "Access denied"),
		classes: "inherited",
		icon: Cancel,
	},
	INHERIT_ALLOW: {
		tooltipText: t("organization_folders", "Allowed (Inherited permission)"),
		ariaLabel: t("organization_folders", "Access allowed"),
		classes: "inherited",
		icon: Check,
	},
	SELF_DENY: {
		tooltipText: t("organization_folders", "Denied"),
		ariaLabel: t('organization_folders', "Access denied"),
		classes: "",
		icon: Cancel,
	},
	SELF_ALLOW: {
		tooltipText: t("organization_folders", "Allowed"),
		ariaLabel: t("organization_folders", "Access allowed"),
		classes: "",
		icon: Check,
	},
}

const loading = ref(false);

const calcBitButtonProps = (bitName, bitState) => {
  return {
	...buttonStates[bitState],
	bitName,
  }
};

const bitButtonProps = computed(() => Object.entries(calcBits(props.value, props.mask)).map(([bitName, { state }]) => calcBitButtonProps(bitName, state)));

const onClick = (bitName) => {
	if(!props.locked) {
		loading.value = bitName;
		emit("change", toggleBit(props.value, bitName), () => {
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
		<td v-for="({ bitName, tooltipText, classes, ariaLabel, icon }) in bitButtonProps" :key="bitName" class="buttonTd">
			<NcButton v-tooltip="tooltipText"
				:class="classes"
				:aria-label="ariaLabel"
				@click="() => onClick(bitName)">
				<template #icon>
					<NcLoadingIcon v-if="loading === bitName" />
					<component v-else :is="icon" :size="16" />
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
