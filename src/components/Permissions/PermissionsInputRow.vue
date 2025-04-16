<script setup>
import { computed } from "vue";

import HelpCircle from "vue-material-design-icons/HelpCircle.vue";

import { calcBits, toggleBit } from "../../helpers/permission-helpers.js";

const props = defineProps({
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

const calcBitButtonProps = (bitName, bitState) => {
  const states = {
	INHERIT_DENY: {
	  tooltipText: t("organization_folders", "Denied (Inherited permission)"),
	  className: "icon-deny inherited",
	},
	INHERIT_ALLOW: {
	  tooltipText: t("organization_folders", "Allowed (Inherited permission)"),
	  className: "icon-checkmark inherited",
	},
	SELF_DENY: {
	  tooltipText: t("organization_folders", "Denied"),
	  className: "icon-deny",
	},
	SELF_ALLOW: {
	  tooltipText: t("organization_folders", "Allowed"),
	  className: "icon-checkmark",
	},
  }
  return {
	...states[bitState],
	bitName,
  }
};

const bitButtonProps = computed(() => Object.entries(calcBits(props.value, props.mask)).map(([bitName, { state }]) => calcBitButtonProps(bitName, state)));

const onClick = (bitName) => emit("change", toggleBit(props.value, bitName));

</script>

<template>
	<tr>
		<td>
			<div style="display: flex; align-items: center;">
				<span>{{ props.label }}</span>
				<HelpCircle v-if="props.explanation"
					v-tooltip="props.explanation"
					style="margin-left: 5px;"
					:size="15" />
			</div>
		</td>

		<td v-for="({ bitName, className, tooltipText }) in bitButtonProps" :key="bitName">
			<button v-tooltip="tooltipText"
				:class="className"
				@click="() => onClick(bitName)" />
		</td>
	</tr>
</template>

<style scoped>
	button {
		height: 24px;
		border-color: transparent;
	}
	button:hover {
		height: 24px;
		border-color: var(--color-primary, #0082c9);
	}
	.icon-deny {
		background-image: url('../../../img/deny.svg');
	}
	.inherited {
		opacity: 0.5;
	}
</style>
