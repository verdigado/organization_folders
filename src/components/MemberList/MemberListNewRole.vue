<template>
	<div class="input-row">
		<div v-for="(level, levelIndex) in levels"
			:key="'level-' + levelIndex"
			style="display: contents;"
			@input="event => onSelection(levelIndex, event.target.value)">
			<select>
				<option value="" selected />
				<option v-for="(item, itemIndex) in level" :key="'option-' + itemIndex + '-' + item.prefix" :value="item.type + '_' + item.id" :disabled="item.disabled === true">
					{{ item.friendlyName }}
				</option>
			</select>
			<ChevronRight v-if="levelIndex !== levels.length - 1" :size="20" />
		</div>
		<NcButton :disabled="!validSelection"
			@click="onSave">
			<template #icon>
				<Plus />
			</template>
			Hinzufügen
		</NcButton>
	</div>
</template>

<script>
import NcButton from "@nextcloud/vue/dist/Components/NcButton.js"
import Plus from "vue-material-design-icons/Plus.vue"
import ChevronRight from "vue-material-design-icons/ChevronRight.vue"
import api from "../../api.js"

export default {
  components: {
	NcButton,
	Plus,
	ChevronRight,
  },
  props: {
	organizationProvider: {
		type: String,
		required: true,
	},
  },
  data() {
	return {
	  selections: [],
	  levels: [],
	  selectedPrincipalType: null,
	  selectedPrincipalId: null,
	  options: [],
	}
  },
  computed: {
	validSelection() {
	  // valid, if not null, undefined or empty
	  return !!this.selectedPrincipalId
	},
  },
  async mounted() {
	// load first selection level
	this.options = await this.loadSubOptions();
	await this.recalculateLevels();
  },
  methods: {
	async loadSubOptions(parentId) {
		const self = this.loadSubOptions;

		let results = [];

		let subOrganizations = await api.getSubOrganizations(this.organizationProvider, parentId);
		
		let roles = [];
		if(parentId) {
			roles = await api.getRoles(this.organizationProvider, parentId);

			results.push({
				type: "organization_member",
				id: parentId,
				friendlyName: "Mitglieder",
			});
		}

		if(subOrganizations.length > 0) {
			results.push(
				{
					type: "seperator",
					friendlyName: "──────────",
					disabled: true,
				},
				...subOrganizations.map((subOrganization) => {
					return {
						type: "organization",
						id: subOrganization.id,
						friendlyName: subOrganization.friendlyName,
						subOptions: () => new Promise((resolve, reject) => {
							self(subOrganization.id).then((result) => {
								resolve(result);
							}).catch((err) => {
								reject(err);
							});
						}),
					};
				})
			);
		}

		if(roles.length > 0) {
			results.push(
				{
					type: "seperator",
					friendlyName: "──────────",
					disabled: true,
				},
				...roles.map((role) => {
					return {
						type: "organization_role",
						id: role.id,
						friendlyName: role.friendlyName,
					};
				}
			));
		}

		return results;
	},
	async recalculateLevels() {
		const levels = [this.options];
		let selectedPrincipalType = null;
		let selectedPrincipalId = null;

		let parent = this.options;
		for (let index = 0; index < this.selections.length; index++) {
			const selection = this.selections[index];

			const option = parent.find(option => option.type + '_' + option.id === selection);

			if (option.type === "organization") {
				const subOptions = await option.subOptions();
				levels[index + 1] = subOptions;
				parent = subOptions;
			} else if(option.type === "organization_member") {
				// reached member leaf
				selectedPrincipalType = api.PrincipalTypes.ORGANIZATION_MEMBER;
				selectedPrincipalId = option.id;
				break;
			} else if(option.type === "organization_role") {
				// reached role leaf
				selectedPrincipalType = api.PrincipalTypes.ORGANIZATION_ROLE;
				selectedPrincipalId = option.id;
				break;
			}
		}

		this.selectedPrincipalType = selectedPrincipalType;
		this.selectedPrincipalId = selectedPrincipalId;
		this.levels = levels;
	},
	async onSelection(level, value) {
		// truncate to levels before new selection
		const newSelections = this.selections.filter((_, index) => index < level);

		newSelections[level] = value;
		this.selections = newSelections;

		this.recalculateLevels();
	},
	onSave() {
		this.$emit("add-member", this.selectedPrincipalType, this.organizationProvider + ":" + this.selectedPrincipalId);		
	},
  },
}
</script>

<style scoped>
.input-row {
	display: flex;
	justify-content: flex-start;
	flex-wrap: wrap;
}
</style>
