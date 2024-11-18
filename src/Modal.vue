<script setup>
import { defineProps, ref } from "vue";
import { useRouter, useRoute } from 'vue2-helpers/vue-router';

import NcModal from "@nextcloud/vue/dist/Components/NcModal.js";

const props = defineProps({
  open: {
    type: Boolean,
    required: true,
  },
});

const emit = defineEmits(["update:open"]);

const closeDialog = () => {
  emit("update:open", false)
};

const router = useRouter();
const route = useRoute();

const currentView = ref(null);

</script>
<template>
    <NcModal v-if="props.open"
        size="large"
        class="organizationfolders-dialog"
        label-id="Organization Folder Management"
        :out-transition="true"
        :has-next="false"
        :has-previous="false"
        @close="closeDialog">
          <router-view />
    </NcModal>
</template>

<style>
.organizationfolders-dialog .modal-container {
	width: unset !important;
	height: 90%;
}

.material_you .list-item, .material_you.app-navigation-entry > .app-navigation-entry-button {
	margin-bottom: 6px !important;
	border-radius: 24px !important;
    background-color: var(--color-background-dark)
}

.app-navigation-entry.material_you  > .app-navigation-entry-button {
	line-height: 60px;
}

.app-navigation-entry.material_you  > .app-navigation-entry-button > .app-navigation-entry-icon {
	width: 60px;
  	height: 60px;
	flex-basis: 60px;
	background-size: 44px 44px;
	background-position: center center;
}

.app-navigation-entry.material_you  > .app-navigation-entry-button > .app-navigation-new-item__name {
	padding-left: 7px;
}

.material_you .list-item {
	--default-clickable-area: 60px;
}

.material_you .list-item:hover, .material_you .list-item:focus, .material_you.app-navigation-entry:hover > .app-navigation-entry-button, .material_you.app-navigation-entry:focus > .app-navigation-entry-button {
    background-color: var(--color-primary-light-hover) !important;
}

.material_you .list-item {
    -webkit-box-sizing: border-box;
    -moz-box-sizing: border-box;
    box-sizing: border-box;
}

.material_you.list-item__wrapper.listItemSelectable:not(.selected) .list-item {
	border: 3px solid transparent;
}
.material_you.list-item__wrapper.listItemSelectable.selected .list-item {
	/*background-color: var(--color-primary-light) !important;*/
	border: 3px solid var(--color-primary);
}

.material_you .list-item:hover .list-item-content__main .list-item-content__name {
	color: var(--color-primary-light-text);
}

.material_you .list-item:hover .list-item__anchor > .material-design-icon svg {
	fill: var(--color-primary-light-text);
}

.material_you .app-navigation-entry-div {
	padding: 8px !important;
}

.material_you.app-navigation-entry:hover {
	background-color: transparent !important;
}

.material_you .app-navigation-new-item__title {
	font-weight: bold;
    font-size: var(--default-font-size);
}

/* For divs required for vue, but irrelevant in the layout */
.ignoreForLayout {
	display: contents;
}
</style>