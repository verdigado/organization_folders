<template>
	<NcSelect v-model="selectedUser"
		@update:modelValue="onSelection"
		:options="options"
		:aria-label-combobox="'User Select'"
		:user-select="true"
		label="displayName"
		@search="onSearch" />
</template>


<script setup>
import NcSelect from '@nextcloud/vue/dist/Components/NcSelect.js';

import api from "../../api.js";

import { ref } from 'vue';

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
