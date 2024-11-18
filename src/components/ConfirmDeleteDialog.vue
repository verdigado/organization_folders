<script setup>
import { ref } from "vue";
import NcTextField from "@nextcloud/vue/dist/Components/NcTextField.js";
import NcModal from "@nextcloud/vue/dist/Components/NcModal.js";

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
					<p>
						Gib hier als Bestätigung "<span style="user-select: all;">{{ props.matchText }}</span>" ein.
					</p>
					<NcTextField class="confirmText"
						:value.sync="confirmText"
						style=" --color-border-maxcontrast: #949494;" />
					<slot name="delete-button" :close="closeDialog" :disabled="confirmText !== props.matchText">
						<button type="button">
							Löschen
						</button>
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
