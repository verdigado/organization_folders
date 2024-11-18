<script setup>
import Delete from "vue-material-design-icons/Delete.vue"
import NcButton from "@nextcloud/vue/dist/Components/NcButton.js"
import NcAvatar from "@nextcloud/vue/dist/Components/NcAvatar.js"
import ChevronRight from "vue-material-design-icons/ChevronRight.vue"

import { computed } from "vue"

const props = defineProps({
  member: {
    type: Object,
    required: true,
  },
});

const friendlyNameParts = computed(() => props.member.principal.split(" / "));

const emit = defineEmits(["update", "delete"]);

const typeOptions = [
  { label: "Mitglied", value: 1 },
  { label: "Manager", value: 2 },
];

const onTypeSelected = (e) => {
  emit("update", props.member.id, {
    type: e.target.value,
  })
};

const onDeleteClicked = (e) => {
  emit("delete", props.member.id)
};
</script>

<template>
	<tr>
		<td>
			<NcAvatar :user="props.member.type === 1 ? props.member.principal : undefined"
				:disabled-menu="true"
				:disabled-tooltip="true"
				:icon-class="props.member.type === 2 ? 'icon-group' : undefined" />
		</td>
		<td>
			<div class="friendlyNameParts">
				<div v-for="(friendlyNamePart, index) of friendlyNameParts" :key="'breadcrumb-' + friendlyNamePart" class="friendlyNamePartDiv">
					<p v-tooltip="friendlyNamePart" class="friendlyNamePartP">
						{{ friendlyNamePart }}
					</p>
					<ChevronRight v-if="index !== friendlyNameParts.length - 1" :size="20" />
				</div>
			</div>
		</td>
		<td>
			<select :value="props.member.permissionLevel" @input="onTypeSelected">
				<option v-for="{ label, value} in typeOptions" :key="value" :value="value">
					{{ label }}
				</option>
			</select>
		</td>
		<td>
			<NcButton type="tertiary-no-background" @click="onDeleteClicked">
				<template #icon>
					<Delete :size="20" />
				</template>
			</NcButton>
		</td>
	</tr>
</template>

<style scoped>
	td {
		padding: 8px;
	}
	.friendlyNameParts {
		display: inline-flex;
		max-width: 100%;
		overflow-x: clip;
	}
	.friendlyNamePartP {
		white-space: nowrap;
		overflow: hidden;

	}
	.friendlyNamePartP:not(:last-child)  {
		text-overflow: ellipsis;
	}
	.friendlyNamePartDiv {
		display: inline-flex;
		min-width: 20px;
	}
	.friendlyNamePartDiv:last-child  {
		flex-shrink: 0;
	}
</style>
