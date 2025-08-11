<template>
	<NcSelect v-model="selectedUser"
		:options="options"
		:aria-label-combobox="'User Select'"
		:user-select="true"
		label="displayName"
		style="width: 100%;"
		@update:modelValue="onSelection"
		@search="onSearch" />
</template>


<script setup>
import { ref } from 'vue';

import NcSelect from "@nextcloud/vue/components/NcSelect";

import api from "../api.js";

const props = defineProps({
	findUserMemberOptions: {
		type: Function,
		required: false,
		default: async () => [],
	},
});

const emit = defineEmits(["selected"]);

const selectedUser = ref(null);

const options = ref([]);

const onSearch = (search) => {
	props.findUserMemberOptions(search).then((newOptions) => {
		options.value = newOptions;
	});
};

const onSelection = (newSelectedUser) => {
	if(newSelectedUser) {
		emit("selected", api.PrincipalTypes.USER, newSelectedUser.id);
	} else {
		emit("selected", null, null);
	}  
};
</script>
