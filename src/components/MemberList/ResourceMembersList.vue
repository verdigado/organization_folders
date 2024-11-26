<script setup>
import NcEmptyContent from "@nextcloud/vue/dist/Components/NcEmptyContent.js";
import NcLoadingIcon from "@nextcloud/vue/dist/Components/NcLoadingIcon.js";
import NcActions from "@nextcloud/vue/dist/Components/NcActions.js";
import NcActionButton from "@nextcloud/vue/dist/Components/NcActionButton.js";
import NcButton from "@nextcloud/vue/dist/Components/NcButton.js";
import { showError } from "@nextcloud/dialogs";
import MemberListNewRole from "./MemberListNewRole.vue";
import MemberListItem from "./MemberListItem.vue";
import Plus from "vue-material-design-icons/Plus.vue";
import Close from "vue-material-design-icons/Close.vue";
import HelpCircle from "vue-material-design-icons/HelpCircle.vue";
import AccountOff from "vue-material-design-icons/AccountOff.vue";
import api from "../../api.js";
import { ref } from 'vue';

const props = defineProps({
	resourceId: {
		type: Number,
		required: true,
	},
	members: {
		type: Array,
		required: true,
	},
	organizationProviders: {
		type: Array,
		required: false,
		default: [],
	},
});

const emit = defineEmits(["add-member", "update-member", "delete-member"]);

const loading = ref(false);

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
		<div class="header-button-group">
			<h3>Mitglieder</h3>
			<NcActions :disabled="!!newMemberType" type="secondary">
				<template #icon>
					<Plus :size="20" />
				</template>
				<NcActionButton icon="icon-user" close-after-click @click="setNewMemberType(api.PrincipalTypes.USER)">
					Benutzer hinzufügen
				</NcActionButton>
				<NcActionButton icon="icon-group" close-after-click @click="setNewMemberType(api.PrincipalTypes.GROUP)">
					Gruppe hinzufügen
				</NcActionButton>
				<NcActionButton v-for="organizationProvider of organizationProviders"
					:key="organizationProvider"
					icon="icon-group"
					close-after-click
					@click="setNewMemberType(api.PrincipalTypes.ROLE, { organizationProvider })">
					{{ organizationProvider }} Organisation Rolle hinzufügen
				</NcActionButton>
			</NcActions>
		</div>
		<div v-if="newMemberType" class="new-item">
			<NcButton type="tertiary" @click="setNewMemberType(null)">
				<template #icon>
					<Close />
				</template>
			</NcButton>
			<!--<MemberListNewUser v-if="newMemberType === api.PrincipalTypes.USER" :resource-id="props.resourceId" @add-member="(principalId) => addMember(api.PrincipalTypes.USER, principalId)" />-->
			<!--<MemberListNewGroup v-if="newMemberType === api.PrincipalTypes.GROUP" :resource-id="props.resourceId" @add-member="(principalId) => addMember(api.PrincipalTypes.GROUP, principalId)" />-->
			<MemberListNewRole v-if="newMemberType === api.PrincipalTypes.ROLE" :resource-id="props.resourceId" :organization-provider="newMemberAdditionalParameters?.organizationProvider" @add-member="(principalId) => addMember(api.PrincipalTypes.ROLE, principalId)" />
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
				<tr v-if="loading">
					<td colspan="4" style="grid-column-start: 1; grid-column-end: 5">
						<NcLoadingIcon :size="50" />
					</td>
				</tr>
				<tr v-if="!loading && !members.length">
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
