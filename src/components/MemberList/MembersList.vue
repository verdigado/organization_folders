<script setup>
import { translate as t, translatePlural as n } from "@nextcloud/l10n";

import HelpCircle from "vue-material-design-icons/HelpCircle.vue";
import AccountOff from "vue-material-design-icons/AccountOff.vue";

import NcEmptyContent from "@nextcloud/vue/components/NcEmptyContent";

import MemberListItem from "./MemberListItem.vue";

const props = defineProps({
	members: {
		type: Array,
		required: true,
	},
	permissionLevelOptions: {
		type: Array,
		required: true,
	},
	permissionLevelExplanation: {
		type: String,
		default: "",
	},
});

const emit = defineEmits(["update-member", "delete-member"]);

const updateMember = (memberId, updateResourceMemberDto) => {
	emit("update-member", memberId, updateResourceMemberDto);
};

const deleteMember = (memberId) => {
	emit("delete-member", memberId);
};

</script>

<template>
	<div>
		<table>
			<thead style="display: contents;">
				<tr>
					<th />
					<th>{{ t("organization_folders", "Name") }}</th>
					<th>
						<div style="display: flex; align-items: center;">
							<span>{{ t("organization_folders", "Permission level") }}</span>
							<HelpCircle v-if="props.permissionLevelExplanation"
								v-tooltip="props.permissionLevelExplanation"
								style="margin-left: 5px;"
								:size="15" />
						</div>
					</th>
					<th />
				</tr>
			</thead>
			<tbody style="display: contents">
				<tr v-if="!members.length">
					<td colspan="4" style="grid-column-start: 1; grid-column-end: 5">
						<NcEmptyContent :name="t('organization_folders', 'No members yet')">
							<template #icon>
								<AccountOff />
							</template>
						</NcEmptyContent>
					</td>
				</tr>
				<MemberListItem v-for="member in members"
					:key="member.id"
					:member="member"
					:permission-level-options="props.permissionLevelOptions"
					@update="updateMember"
					@delete="deleteMember" />
			</tbody>
		</table>
	</div>
</template>

<style scoped>
table {
	width: 100%;
	margin-bottom: 14px;
	display: grid;
	grid-template-columns: max-content minmax(30px, auto) max-content max-content;
}
table tr {
	display: contents;
}
table td, table th {
	padding: 8px;
}
</style>
