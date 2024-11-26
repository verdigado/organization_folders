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

const emit = defineEmits(["update", "delete"]);

const permissionLevelOptions = [
  { label: "Mitglied", value: 1 },
  { label: "Manager", value: 2 },
];

const onPermissionLevelSelected = (e) => {
  emit("update", props.member.id, {
    permissionLevel: parseInt(e.target.value, 10),
  });
};

const onDeleteClicked = (e) => {
  emit("delete", props.member.id);
};
</script>

<template>
	<tr>
		<td>
			<NcAvatar :user="props.member.principal.type === 1 ? props.member.principal.id : undefined"
				:disabled-menu="true"
				:disabled-tooltip="true"
				:icon-class="props.member.principal.type > 1 ? 'icon-group' : undefined" />
		</td>
		<td>
			<div class="fullHierarchyNameParts">
				<div v-for="(fullHierarchyNamePart, index) of props.member.principal.fullHierarchyNames" :key="fullHierarchyNamePart">
					<p v-tooltip="fullHierarchyNamePart">
						{{ fullHierarchyNamePart }}
					</p>
					<ChevronRight v-if="index !== props.member.principal.fullHierarchyNames.length - 1" :size="20" />
				</div>
			</div>
		</td>
		<td>
			<select :value="props.member.permissionLevel" @input="onPermissionLevelSelected">
				<option v-for="{ label, value} in permissionLevelOptions" :key="value" :value="value">
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

<style lang="scss" scoped>
	td {
		padding: 8px;
	}

	.fullHierarchyNameParts {
		display: inline-flex;
		max-width: 100%;
		overflow-x: clip;

		> div {
			display: inline-flex;
			min-width: 20px;

			&:last-child  {
				flex-shrink: 0;
			}

			> p {
				white-space: nowrap;
				overflow: hidden;

				&:not(:last-child)  {
					text-overflow: ellipsis;
				}
			}
		}
	}
</style>
