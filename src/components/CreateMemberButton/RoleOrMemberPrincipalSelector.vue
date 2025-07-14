<template>
	<div style="display: flex; flex-direction: column;">
		<div v-for="(level, levelIndex) in levels"
			:key="'level-' + levelIndex"
			style="display: flex; flex-direction: row;">
			<SubdirectoryArrowRight v-if="levelIndex !== 0" :size="30" />
			<NcSelect style="flex-grow: 100;"
				label="friendlyName"
				:modelValue="selections[levelIndex]"
				:options="level"
				:getOptionKey="(item) => item.type + '_' + item.id"
				:reduce="(item) => item.type + '_' + item.id"
				:selectable="(item) => item.disabled !== true"
				:filter="filter"
				@search="(newValue) => search = newValue"
				@input="newValue => onSelection(levelIndex, newValue)">
					<template #option="option">
						<span v-if="option.disabled" :class="{optionIndented: option.indented}">{{ option.friendlyName }}</span>
						<NcHighlight v-else :class="{optionIndented: option.indented}" :text="option.friendlyName"
							:search="search" />
					</template>
			</NcSelect>
		</div>
	</div>
</template>

<script>
import { translate as t, translatePlural as n } from "@nextcloud/l10n";

import NcButton from "@nextcloud/vue/components/NcButton";
import NcSelect from "@nextcloud/vue/components/NcSelect";
import NcHighlight from "@nextcloud/vue/components/NcHighlight";

import Plus from "vue-material-design-icons/Plus.vue"
import SubdirectoryArrowRight from "vue-material-design-icons/SubdirectoryArrowRight.vue"

import api from "../../api.js"

export default {
  components: {
	NcButton,
	NcSelect,
	NcHighlight,
	Plus,
	SubdirectoryArrowRight,
  },
  props: {
	organizationProvider: {
		type: String,
		required: true,
	},
	enableMembers: {
		type: Boolean,
		default: true,
	},
	enableRoles: {
		type: Boolean,
		default: true,
	}
  },
  data() {
	return {
		selections: [],
		levels: [],
		selectedPrincipalType: null,
		selectedPrincipalId: null,
		options: [],
		search: "",
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
			if(this.enableRoles) {
				roles = await api.getRoles(this.organizationProvider, parentId);
			}

			if(this.enableMembers) {
				results.push({
					type: "organization_member",
					id: parentId,
					friendlyName: t("organization_folders", "Members"),
				});
			}
		}

		if(subOrganizations.length > 0) {
			if(results.length > 0) {
				results.push(
					{
						type: "seperator",
						id: 1,
						friendlyName: "──────────",
						disabled: true,
					},
				);
			}
			results.push(
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
			if(results.length > 0) {
				results.push(
					{
						type: "seperator",
						id: 2,
						friendlyName: "──────────",
						disabled: true,
					},
				);
			}

			const rolesByCategory = roles.reduce((acc, role) => {
				const key = role?.category?.friendlyName ?? t("organization_folders", "Uncategorized roles");

				if (!acc[key]) {
					acc[key] = [];
				}
				
				acc[key].push(role);

				return acc;
			}, {});

			Object.keys(rolesByCategory).forEach((categoryName, index) => {
				results.push(
					{
						type: "category",
						id: index,
						friendlyName: categoryName + ":",
						disabled: true,
					},
				);

				for(const role of rolesByCategory[categoryName]) {
					results.push(
						{
							type: "organization_role",
							id: role.id,
							friendlyName: role.friendlyName,
							indented: true,
						}
					)
				}
			});
		}

		return results;
	},
	filter(options, search) {
		let results = [];

		let firstSection = true;
		let sectionSeperator = null;
		let sectionItems = [];

		let categoryOption = null;
		let categoryItems = [];

		for(const option of options) {
			if(option.type === "seperator") {
				// next section starting
				if(sectionItems.length > 0) {
					if(sectionSeperator && !firstSection) {
						results.push(sectionSeperator);
					}
					results.push(...sectionItems);
					sectionItems = [];
					firstSection = false;
					categoryOption = null
				}

				sectionSeperator = option;
			} else if (option.type === "category") {
				// next category starting
				if(categoryItems.length > 0) {
					sectionItems.push(categoryOption);
					sectionItems.push(...categoryItems);
					categoryItems = [];
				}

				categoryOption = option;
			} else {
				if((option["friendlyName"].toLocaleLowerCase().indexOf(search.toLocaleLowerCase()) > -1)) {
					if(categoryOption) {
						categoryItems.push(option);
					} else {
						sectionItems.push(option);
					}
				}
			}
		}

		if(categoryItems.length > 0) {
			sectionItems.push(categoryOption);
			sectionItems.push(...categoryItems);
		}

		if(sectionItems.length > 0) {
			if(sectionSeperator && !firstSection) {
				results.push(sectionSeperator);
			}
			results.push(...sectionItems);
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

			if (option?.type === "organization") {
				const subOptions = await option.subOptions();
				levels[index + 1] = subOptions;
				parent = subOptions;
			} else if(option?.type === "organization_member") {
				// reached member leaf
				selectedPrincipalType = api.PrincipalTypes.ORGANIZATION_MEMBER;
				selectedPrincipalId = option.id;
				break;
			} else if(option?.type === "organization_role") {
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
		newSelections[level + 1] = "";
		this.selections = newSelections;

		await this.recalculateLevels();

		console.log("onSelection", this.selectedPrincipalType, this.organizationProvider, this.selectedPrincipalId);

		if(this.selectedPrincipalId) {
			this.$emit("selected", this.selectedPrincipalType, this.organizationProvider + ":" + this.selectedPrincipalId);
		} else {
			this.$emit("selected", null, null);
		}
	},
  },
}
</script>

<style scoped>
.optionIndented {
	margin-inline-start: 10px;
}
</style>