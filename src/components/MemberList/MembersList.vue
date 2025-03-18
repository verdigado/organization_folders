<script setup>
import NcEmptyContent from "@nextcloud/vue/dist/Components/NcEmptyContent.js";
import NcActions from "@nextcloud/vue/dist/Components/NcActions.js";
import NcActionButton from "@nextcloud/vue/dist/Components/NcActionButton.js";
import NcButton from "@nextcloud/vue/dist/Components/NcButton.js";

import MemberListNewRole from "./MemberListNewRole.vue";
import MemberListItem from "./MemberListItem.vue";
import HeaderButtonGroup from "./../HeaderButtonGroup.vue";

import Plus from "vue-material-design-icons/Plus.vue";
import Close from "vue-material-design-icons/Close.vue";
import HelpCircle from "vue-material-design-icons/HelpCircle.vue";
import AccountOff from "vue-material-design-icons/AccountOff.vue";

import api from "../../api.js";
import { ref } from 'vue';

const props = defineProps({
	members: {
		type: Array,
		required: true,
	},
	enableUserType: {
		type: Boolean,
		default: true,
	},
	enableGroupType: {
		type: Boolean,
		default: true,
	},
	enableRoleType: {
		type: Boolean,
		default: true,
	},
	organizationProviders: {
		type: Array,
		required: false,
		default: [],
	},
	permissionLevelOptions: {
		type: Array,
		required: true,
	},
});

const emit = defineEmits(["add-member", "update-member", "delete-member"]);

const newMemberType = ref(null);
const newMemberAdditionalParameters = ref({});
const addMenuOpen = ref(false);

const setNewMemberType = (name, additionalParameters = {}) => {
	newMemberType.value = name;
	newMemberAdditionalParameters.value = additionalParameters;
	addMenuOpen.value = false;
};

const addMember = (principalType, principalId) => {
	emit("add-member", principalType, principalId);
	newMemberType.value = null;
};

const updateMember = (memberId, updateResourceMemberDto) => {
	emit("update-member", memberId, updateResourceMemberDto);
};

const deleteMember = (memberId) => {
	emit("delete-member", memberId);
};

</script>

<template>
	<div>
		<HeaderButtonGroup>
			<h3>Mitglieder</h3>
			<NcActions :disabled="!!newMemberType" type="secondary">
				<template #icon>
					<Plus :size="20" />
				</template>
				<NcActionButton icon="icon-user" close-after-click v-if="props.enableUserType" @click="setNewMemberType('USER')">
					Benutzer hinzufügen
				</NcActionButton>
				<NcActionButton icon="icon-group" close-after-click v-if="props.enableGroupType" @click="setNewMemberType('GROUP')">
					Gruppe hinzufügen
				</NcActionButton>
				<NcActionButton v-for="organizationProvider of (props.enableGroupType ? organizationProviders : [])"
					:key="organizationProvider"
					icon="icon-group"
					close-after-click
					@click="setNewMemberType('ORGANIZATION_MEMBER_OR_ROLE', { organizationProvider })">
					{{ organizationProvider }} Organisation Mitglied oder Rolleninhaber*innen hinzufügen
				</NcActionButton>
			</NcActions>
		</HeaderButtonGroup>
		<div v-if="newMemberType" class="new-item">
			<NcButton type="tertiary" @click="setNewMemberType(null)">
				<template #icon>
					<Close />
				</template>
			</NcButton>
			<!--<MemberListNewUser v-if="newMemberType === 'USER'" @add-member="(principalId) => addMember(api.PrincipalTypes.USER, principalId)" />-->
			<!--<MemberListNewGroup v-if="newMemberType === 'GROUP'" @add-member="(principalId) => addMember(api.PrincipalTypes.GROUP, principalId)" />-->
			<MemberListNewRole v-if="newMemberType === 'ORGANIZATION_MEMBER_OR_ROLE'" :organization-provider="newMemberAdditionalParameters?.organizationProvider" @add-member="(principalType, principalId) => addMember(principalType, principalId)" />
		</div>
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
	.new-item {
		display: flex;
	}

	.header-button-group {
		display: flex;
		justify-content: flex-start;
		align-items: center;
		column-gap: 10px;
		margin-top: 24px;
		margin-bottom: 12px;

		h1, h2, h3 {
			margin: 0px;
		}
	}
</style>
