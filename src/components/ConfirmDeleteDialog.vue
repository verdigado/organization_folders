<script setup>
import { ref, computed } from "vue";
import { translate as t, translatePlural as n } from "@nextcloud/l10n";

import NcTextField from "@nextcloud/vue/components/NcTextField";
import NcModal from "@nextcloud/vue/components/NcModal";


const props = defineProps({
  title: {
	type: String,
	default: "Löschen",
  },
  loading: {
	type: Boolean,
	default: false,
  },
  matchText: {
	type: String,
	default: "löschen",
  },
});

const open = ref(false);
const confirmText = ref("");

const openDialog = () => {
  open.value = true;
}

const closeDialog = () => {
  open.value = false;
}

const confirmExplanation = computed(() => {
	return t(
		"organization_folders",
		'Type "{markupStart}{text}{markupEnd}" to confirm.',
		{
			markupStart: {
				value: '<span style="user-select: all;">',
				escape: false,
			},
			text: props.matchText,
			markupEnd: {
				value: '</span>',
				escape: false,
			},
		}
	);
});

</script>

<template>
	<div>
		<slot name="activator" :open="openDialog">
			<button type="button" @click="openDialog">
				Löschen
			</button>
		</slot>
		<NcModal v-if="open"
			class="modal"
			:out-transition="true"
			:has-next="false"
			:has-previous="false"
			@close="closeDialog">
			<div class="modal__content">
				<div class="modal__title">
					<h1>
						{{ props.title }}
					</h1>
				</div>
				<div>
					<slot name="content" />
					<p v-html="confirmExplanation" />
					<NcTextField class="confirmText"
						:value.sync="confirmText"
						style=" --color-border-maxcontrast: #949494;" />
					<slot name="delete-button" :close="closeDialog" :disabled="confirmText !== props.matchText">
						<button type="button">{{ t("organization_folders", "Delete") }}</button>
					</slot>
				</div>
			</div>
		</NcModal>
	</div>
</template>

<style scoped>
.confirmText {
	margin: 1rem 0 1rem 0;
}

.modal__title {
	margin-bottom: 16px;
	height: 50px;
}

.modal__title h1 {
	text-align: center;
	font-size: 1.6rem;
	font-weight: bold;
}

.modal__content {
	margin: 50px;
	min-height: 500px;
}
</style>
