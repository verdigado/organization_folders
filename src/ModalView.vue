<script setup>
import NcButton from "@nextcloud/vue/components/NcButton";
import NcLoadingIcon from "@nextcloud/vue/components/NcLoadingIcon";

import { translate as t } from "@nextcloud/l10n";

import KeyboardBackspace from "vue-material-design-icons/KeyboardBackspace.vue";

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  hasBackButton: {
    type: Boolean,
    default: false,
  },
  hasNextStepButton: {
    type: Boolean,
    default: false,
  },
  hasPreviousStepButton: {
    type: Boolean,
    default: false,
  },
  nextStepButtonEnabled: {
    type: Boolean,
    default: false,
  },
  previousStepButtonEnabled: {
    type: Boolean,
    default: false,
  },
  previousStepButtonText: {
    type: String,
    default: t("organization_folders", "Back"),
  },
  nextStepButtonText: {
    type: String,
    default: t("organization_folders", "Next"),
  },
});

const emit = defineEmits(["back-button-pressed", "next-step-button-pressed", "previous-step-button-pressed"]);

const backButtonPressed = () => {
    emit("back-button-pressed");
};

const nextStepButtonPressed = () => {
    emit("next-step-button-pressed");
};

const previousStepButtonPressed = () => {
    emit("previous-step-button-pressed");
};

</script>

<template>
    <div class="modal__content">
        <div class="modal__title">
            <NcButton
                type="secondary"
                class="btn-back"
                aria-label="Zurück"
                v-if="hasBackButton"
                @click="backButtonPressed">
                <template #icon>
                    <KeyboardBackspace />
                </template>
            </NcButton>
            <h1>{{ title }}</h1>
        </div>
        <div v-if="loading" class="modal__loading">
            <NcLoadingIcon :size="64" />
        </div>
        <div v-if="!loading" class="modal__main ignoreForLayout">
            <slot></slot>
        </div>
        <div v-if="!loading && (hasPreviousStepButton || hasNextStepButton)" class="modal__footer">
            <NcButton
                :style="{visibility: hasPreviousStepButton ? 'visible' : 'hidden'}"
                type="secondary"
                :disabled="!previousStepButtonEnabled"
                :aria-label="previousStepButtonText"
                @click="previousStepButtonPressed">
                {{ previousStepButtonText }}
            </NcButton>
            <NcButton
                v-if="hasNextStepButton"
                type="primary"
                :disabled="!nextStepButtonEnabled"
                :aria-label="nextStepButtonText"
                @click="nextStepButtonPressed">
                {{ nextStepButtonText }}
            </NcButton>
        </div>
    </div>
</template>

<style scoped>
.modal__title {
	margin-bottom: 16px;
	flex-grow: 0;
}

.modal__title h1 {
	text-align: center;
	font-size: 1.6rem;
	font-weight: bold;
}

.modal__footer{
	margin-top: 16px;
	height: 50px;
	flex-grow: 0;
	display: flex;
	flex-direction: row;
	justify-content: space-between;
}

.modal__content {
	padding: 50px;
	min-width: 75vw;
	height: calc(100% - 100px);
	overflow: scroll;
	display: flex;
	flex-direction: column;
}
.modal__loading {
    padding: 50px;
	min-width: 75vw;
	height: calc(100% - 100px);
	overflow: hidden;
	display: flex;
    justify-content: center;
}

.btn-back {
	position: absolute;
	z-index: 10002;
}
</style>

<style>
.organizationfolders-dialog .modal-container {
	width: unset !important;
	height: 90%;
}
</style>