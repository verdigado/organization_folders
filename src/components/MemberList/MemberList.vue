<script setup>
import NcEmptyContent from "@nextcloud/vue/dist/Components/NcEmptyContent.js"
import NcLoadingIcon from "@nextcloud/vue/dist/Components/NcLoadingIcon.js"
import NcActions from "@nextcloud/vue/dist/Components/NcActions.js"
import NcActionButton from "@nextcloud/vue/dist/Components/NcActionButton.js"
import NcButton from "@nextcloud/vue/dist/Components/NcButton.js"
import { showError } from "@nextcloud/dialogs"
//import MemberListNewItem from "./MemberListNewItem.vue"
import MemberListItem from "./MemberListItem.vue"
import Plus from "vue-material-design-icons/Plus.vue"
import Close from "vue-material-design-icons/Close.vue"
import HelpCircle from "vue-material-design-icons/HelpCircle.vue"
import api from "../../api.js"
import { ref } from 'vue';

const props = defineProps({
	members: {
		type: Array,
		required: true,
	},
});

const loading = ref(false);
const error = ref(undefined);
const newItemComponent = ref(null);
const addMenuOpen = ref(false);

const setNewItemComponent = (name) => {
	this.newItemComponent.value = name
	this.addMenuOpen.value = false
};

const deleteMember = async (memberId) => {
	this.loading.value = true
	try {
		api.deleteGroupMember(this.groupId, memberId)
		//this.members.value = this.members.filter((m) => m.id !== memberId)
	} catch (err) {
		showError(err.message)
	} finally {
		this.loading.value = false
	}
};

const updateMember = async (memberId, changes) => {
	this.loading.value = true
	try {
		const member = await api.updateGroupMember(this.groupId, memberId, changes)
		this.members = this.members.map((m) => m.id === member.id ? member : m)
	} catch (err) {
		showError(err.message)
	} finally {
		this.loading.value = false
	}
};

const addMember = async ({ mappingId, mappingType }) => {
	this.loading.value = true
	try {
		const _member = await api.addGroupMember(this.groupId, {
			mappingType,
			mappingId,
			type: "member",
		})
		this.members.push(_member)
		this.setNewItemComponent(null)
	} catch (err) {
		showError(err.message)
	} finally {
		this.loading = false
	}
};


</script>

<template>
	<div>
		<div class="title">
			<h3>Mitglieder</h3>
			<!--<NcActions :disabled="!!newItemComponent" type="secondary">
				<template #icon>
					<Plus :size="20" />
				</template>
				<NcActionButton icon="icon-group" close-after-click @click="setNewItemComponent('new_item')">
					Benutzer/Gruppe hinzufügen
				</NcActionButton>
				<NcActionButton icon="icon-group" close-after-click @click="setNewItemComponent('new_role_item')">
					Organisation Rolle hinzufügen
				</NcActionButton>
			</NcActions>-->
		</div>
		<!--<div v-if="newItemComponent" class="new-item">
			<NcButton type="tertiary" @click="setNewItemComponent(null)">
				<template #icon>
					<Close />
				</template>
			</NcButton>
			<MemberListNewItem v-if="newItemComponent === 'new_item'" :group-id="groupId" @selected="addMember" />
		</div>-->
		<table>
			<thead style="display: contents;">
				<tr>
					<th />
					<th>Name</th>
					<th>
						<div style="display: flex; align-items: center;">
							<span>Typ</span>
							<HelpCircle v-tooltip="'Für Admins gelten die oben ausgewählten Ordneradministrator*innen Berechtigungen, für Mitglieder die Ordnermitglieder Berechtigungen. Admins haben auf diese Einstellungen Zugriff.'" style="margin-left: 5px;" :size="15" />
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
						<NcEmptyContent title="Keine Gruppenmitglieder" />
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
	.title {
		display: flex;
		justify-content: flex-start;
		align-items: center;
		margin-top: 24px;
	}
	h3 {
		font-weight: bold;
		margin-right: 24px;
	}
	.new-item {
		display: flex;
	}
</style>
