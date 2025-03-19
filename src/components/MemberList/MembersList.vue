<script setup>
import { NcEmptyContent } from '@nextcloud/vue';

import MemberListItem from "./MemberListItem.vue";

import HelpCircle from "vue-material-design-icons/HelpCircle.vue";
import AccountOff from "vue-material-design-icons/AccountOff.vue";

const props = defineProps({
	members: {
		type: Array,
		required: true,
	},
	permissionLevelOptions: {
		type: Array,
		required: true,
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
					<th>Name</th>
					<th>
						<div style="display: flex; align-items: center;">
							<span>Typ</span>
							<HelpCircle v-tooltip="'Für Manager gelten die oben ausgewählten Ordnermanager Berechtigungen, für Mitglieder die Ordnermitglieder Berechtigungen. Manager haben auf diese Einstellungen Zugriff.'" style="margin-left: 5px;" :size="15" />
						</div>
					</th>
					<th>Aktion</th>
				</tr>
			</thead>
			<tbody style="display: contents">
				<tr v-if="!members.length">
					<td colspan="4" style="grid-column-start: 1; grid-column-end: 5">
						<NcEmptyContent name="Keine Mitglieder">
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
