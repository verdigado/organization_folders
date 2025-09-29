<template>
	<div style="display: flex; flex-direction: column;">
		<div v-for="(level, levelIndex) in levels"
			:key="'level-' + levelIndex"
			style="display: flex; flex-direction: row;">
			<SubdirectoryArrowRight v-if="levelIndex !== 0" :size="30" />
			<NcSelect style="flex-grow: 100;"
				label="friendlyName"
				:tabindex="levels.length - levelIndex"
				:modelValue="selections[levelIndex]"
				:options="level"
				:loading="loading[levelIndex] ?? false"
				:clearable="false"
				:getOptionKey="(item) => item.type + '_' + item.id"
				:reduce="(item) => item.type + '_' + item.id"
				:selectable="(item) => item.disabled !== true"
				:filter="filter"
				:aria-label-combobox="levelIndex === 0 ? t('organization_folders', 'Select organization') : t('organization_folders', 'Select sub-organization or role')"
				@search="(newValue) => search = newValue"
				@input="newValue => onSelection(levelIndex, newValue)">
					<template #option="option">
						<span v-if="option.disabled" :class="{ optionIndented: option.indented }">{{ option.friendlyName }}</span>
						<NcHighlight v-else :class="{ optionIndented: option.indented }" :text="option.friendlyName"
							:search="search" />
					</template>
			</NcSelect>
		</div>
	</div>
</template>

<script>
import { translate as t, translatePlural as n } from "@nextcloud/l10n";
import PLazy from 'p-lazy';

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
	},
	initialRoleOrganizationPath: {
		type: Array,
		default: () => [],
	},
  },
  data() {
	return {
		selections: [],
		levels: [],
		loading: [],
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
  beforeMount() {
	// prepare initial selections and add temporary levels array to supply friendlyNames until recalculateLevels runs and actually fills the levels
	for(let organization of this.initialRoleOrganizationPath) {
		this.loading.push(true);
		this.levels.push([{
			type: "organization",
			id: organization.id,
			friendlyName: organization.friendlyName,
		}]);
		this.selections.push("organization_" + organization.id);
	}

	// as all precreated selections are organizations, one more level to select role or members is guaranteed
	this.loading.push(true);
	this.levels.push([]);
	this.selections.push("");
  },
  async mounted() {
	// load first selection level
	this.options = await this.loadSubOptions();
	this.loading[0] = false;
	await this.recalculateLevels(true);
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
						subOptions: new PLazy((resolve, reject) => {
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
				if(
					(option["friendlyName"].toLocaleLowerCase().indexOf(search.toLocaleLowerCase()) > -1)
					|| (option["id"].toString().indexOf(search) > -1)
				) {
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
	async recalculateLevels(initialLoad) {
		this.levels.splice(this.selections.length);
		this.$set(this.levels, 0, this.options);
		let selectedPrincipalType = null;
		let selectedPrincipalId = null;

		let numberOfLevels = 0;

		let parent = this.options;
		for (let index = 0; index < this.selections.length; index++) {
			const selection = this.selections[index];

			const option = parent.find(option => option.type + '_' + option.id === selection);

			if (option?.type === "organization") {
				if(!initialLoad) {
					this.$set(this.levels, index + 1, []);
				}
				this.loading[index + 1] = true;
				const subOptions = await option.subOptions;
				this.loading[index + 1] = false;
				this.$set(this.levels, index + 1, subOptions);
				numberOfLevels = index + 1;
				parent = subOptions;
			} else if(option?.type === "organization_member") {
				// reached member leaf
				selectedPrincipalType = api.PrincipalTypes.ORGANIZATION_MEMBER;
				selectedPrincipalId = option.id;
				numberOfLevels = index;
				break;
			} else if(option?.type === "organization_role") {
				// reached role leaf
				selectedPrincipalType = api.PrincipalTypes.ORGANIZATION_ROLE;
				selectedPrincipalId = option.id;
				numberOfLevels = index;
				break;
			}
		}

		this.levels.splice(numberOfLevels + 1);

		this.selectedPrincipalType = selectedPrincipalType;
		this.selectedPrincipalId = selectedPrincipalId;
	},
	async onSelection(level, value) {
		// truncate to levels before new selection
		// TODO: migrate to splice
		const newSelections = this.selections.filter((_, index) => index < level);

		newSelections[level] = value;
		newSelections[level + 1] = "";
		this.selections = newSelections;

		await this.recalculateLevels();

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