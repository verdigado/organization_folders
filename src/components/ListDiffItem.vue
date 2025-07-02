<!--
  - @copyright Copyright (c) 2023 Jonathan Treffler <jonathan.treffler@verdigado.com>
  -
  - @author Marco Ambrosini <marcoambrosini@icloud.com>
  - @author Jonathan Treffler <jonathan.treffler@verdigado.com>
  -
  - @license GNU AGPL version 3 or any later version
  -
  - This program is free software: you can redistribute it and/or modify
  - it under the terms of the GNU Affero General Public License as
  - published by the Free Software Foundation, either version 3 of the
  - License, or (at your option) any later version.
  -
  - This program is distributed in the hope that it will be useful,
  - but WITHOUT ANY WARRANTY; without even the implied warranty of
  - MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
  - GNU Affero General Public License for more details.
  -
  - You should have received a copy of the GNU Affero General Public License
  - along with this program. If not, see <http://www.gnu.org/licenses/>.
-->

<template>
    <li class="list-diff-item__wrapper"
        :class="{ 'list-diff-item__wrapper--active' : active }">
        <a :id="anchorId"
            ref="list-diff-item"
            class="list-diff-item"
            :aria-label="linkAriaLabel"
            @mouseover="handleMouseover"
            @mouseleave="handleMouseleave"
            @focus="handleFocus"
            @blur="handleBlur">
            <div class="list-diff-item-content__wrapper">
				<div class="list-diff-item-content__row1">
					<NcCheckboxRadioSwitch :checked="selected" @update:checked="(newValue) => $emit('update:selected', newValue)"></NcCheckboxRadioSwitch>
					<div class="list-diff-item-content__row1-text" @click.stop="handleClick">
						<span>{{ textDescription }}</span>
					</div>
					<NcButton
						aria-label="Expand item"
						type="tertiary-no-background"
						class="list-diff-item-content__row1-expand-button"
						:class="{ expanded }"
						@click="expanded = !expanded">
						<template #icon>
							<ChevronLeft :size="20" />
						</template>
					</NcButton>
				</div>
				<div class="list-diff-item-content__row2" :class="{ expanded }">
					<div style="display: grid; grid-template-columns: 50% 50%;">
						<div class="grid_item">
							<p style="text-align: center;">{{ t("organization_folders", "In the backup") }}</p>
						</div>
						<div class="grid_item">
							<p style="text-align: center;">{{ t("organization_folders", "Currently") }}</p>
						</div>

						<!-- Line 1 -->
						<div class="grid_item">
							<File :size="64" v-if="diff?.before?.fileExists"/>
							<FileRemove :size="64" v-else/>
						</div>
						<div class="grid_item">
							<img v-if="diff?.current?.fileExists"
								alt=""
								loading="lazy"
								class="thumbnail"
								:src="currentFilePreviewUrl">
						</div>
						<!-- Line 2 -->
						<div class="grid_item">
							<span v-if="diff?.before?.fileExists">
								{{ diff?.before?.path }}
							</span>
							<span v-else>{{ t("organization_folders", "File does not exist") }}</span>
						</div>
						<div class="grid_item">
							<span style="text-align: end;" v-if="diff?.current?.fileExists">
								{{ diff?.current?.path }}
							</span>
							<span v-else>{{ t("organization_folders", "File does not exist") }}</span>
						</div>
						<!-- Line 3 -->
						<div class="grid_item">
							<span v-if="diff?.before?.fileExists">
								{{ bytesToSize(diff?.before?.size) }}
							</span>
						</div>
						<div class="grid_item">
							<span v-if="diff?.current?.fileExists">
								{{ bytesToSize(diff?.current?.size) }}
							</span>
						</div>
					</div>
				</div>
            </div>
        </a>
    </li>
</template>

<script>
import NcCheckboxRadioSwitch from "@nextcloud/vue/components/NcCheckboxRadioSwitch";
import NcButton from "@nextcloud/vue/components/NcButton";

import { generateUrl } from '@nextcloud/router'

import File from "vue-material-design-icons/File.vue"
import FileRemove from "vue-material-design-icons/FileRemove.vue"
import ChevronLeft from 'vue-material-design-icons/ChevronLeft.vue'

import { bytesToSize } from "../helpers/file-size-helpers.js"

export default {
    name: 'ListDiffItem',

    components: {
        NcCheckboxRadioSwitch,
		NcButton,
        File,
		FileRemove,
		ChevronLeft,
    },

    props: {
        diff: {
            type: Object,
            default: {},
        },

        /**
         * Id for the `<a>` element
         */
        anchorId: {
            type: String,
            default: '',
        },

        /**
         * Toggle the active state of the component
         */
        active: {
            type: Boolean,
            default: false,
        },

        /**
         * Aria label for the wrapper element
         */
        linkAriaLabel: {
            type: String,
            default: '',
        },

		selected: {
			type: Boolean,
			default: false,
		},

		parentDirectory: {
			type: String,
			default: "",
		}
    },

    emits: [
        'click',
        'update:menuOpen',
    ],

    data() {
        return {
            hovered: false,
            focused: false,
			expanded: false,
        }
    },

    computed: {
		currentFilePreviewUrl() {
			try {
				const previewUrl = generateUrl('/core/preview?fileId={fileId}', {
						fileId: this.diff?.current?.fileId,
					})
				const url = new URL(window.location.origin + previewUrl)

				// Request tiny previews
				url.searchParams.set('x', '32')
				url.searchParams.set('y', '32')
				url.searchParams.set('mimeFallback', 'true')

				// Handle cropping
				url.searchParams.set('a', '0')
				return url.href
			} catch (e) {
				return null
			}
		},
		textDescription() {
			switch(this.diff?.type) {
				case "CREATION":
					return t("organization_folders", "{filePath} has been created", { filePath: this.diff?.current?.path });
                case "EDIT":
					return t("organization_folders", "{filePath} has been changed", { filePath: this.diff?.current?.path });
                case "RENAME":
					return t("organization_folders", "{oldFilePath} has been renamed to {newFilePath}", { oldFilePath: this.diff?.before?.path, newFilePath: this.diff?.current?.path });
                case "DELETION":
					return t("organization_folders", "{filePath} has been deleted", { filePath: this.diff?.before?.path });
				default:
					return "";
			}
		}

    },

    watch: {
        menuOpen(newValue) {
            // A click outside both the menu and the root element hides the actions again
            if (!newValue && !this.hovered) {
                this.displayActionsOnHoverFocus = false
            }
        },
    },

    mounted() {
    },

    updated() {
    },

    methods: {
        /**
         * Handle click
         *
         * @param {PointerEvent} event - Native click event
         */
        handleClick(event) {
            this.$emit("update:selected", !this.selected);
        },

        handleMouseover() {
            this.hovered = true
        },

        /**
         * Show actions upon focus
         */
        handleFocus() {
            this.focused = true
        },

        handleBlur() {
            this.focused = false
        },

        /**
         * Hide the actions on mouseleave unless the menu is open
         */
        handleMouseleave() {
            if (!this.menuOpen) {
                this.displayActionsOnHoverFocus = false
            }
            this.hovered = false
        },

        handleActionsUpdateOpen(e) {
            this.menuOpen = e
            this.$emit('update:menuOpen', e)
        },
		bytesToSize,
    },
}
</script>

<style lang="scss" scoped>

.list-diff-item__wrapper {
    position: relative;
    width: 100%;

    &--active,
    &:active,
    &.active {
        .list-diff-item {
            background-color: var(--color-primary-element-light);
        }
    }
}

.list-diff-item {
    display: block;
    position: relative;
    flex: 0 0 auto;
    justify-content: flex-start;
    padding: 8px;
    // Fix for border-radius being too large for 3-line entries like in Mail
    // 44px avatar size / 2 + 8px padding, and 2px for better visual quality
    border-radius: 32px;
    margin: 2px 0;
    width: 100%;
    cursor: pointer;
    transition: background-color var(--animation-quick) ease-in-out;
    list-style: none;
    &:hover,
    &:focus {
        background-color: var(--color-background-hover);
    }

    &-content__row1 {
        display: flex;
        align-items: center;
		flex-direction: row;
		-webkit-user-select: none; /* Safari */
		user-select: none;
        height: 51px;

		&-text {
			display: flex;
			height: 100%;
			width: 100%;
			padding-left: 8px;
			justify-content: flex-start;
			align-items: center;
		}

		&-expand-button {
			transform: rotate(0deg);

			-webkit-transition: -webkit-transform 0.3s ease-out;
			transition:         transform 0.3s ease-out;

			&.expanded {
				transform: rotate(-90deg);
			}
		}
    }

	&-content__row2 {
        overflow: hidden;
		transition: max-height 0.3s ease-out;
		height: auto;
		max-height: 0;

		&.expanded {
			max-height: 160px;
		}

		.thumbnail {
			width: 64px;
			height: 64px;
		}
    }

    &__extra {
        margin-top: 4px;
    }

    .grid_item {
        display: flex; 
        justify-content: center
    }
}

// Add more contrast for active entry
[data-themes*='highcontrast'] {
    .list-diff-item__wrapper {
        &--active,
        &:active,
        &.active {
            .list-diff-item {
                background-color: var(--color-primary-element-light-hover);
            }
        }
    }
}

.line-one {
    display: flex;
    align-items: center;
    justify-content: space-between;
    white-space: nowrap;
    margin: 0 auto 0 0;
    overflow: hidden;

    &__name {
        overflow: hidden;
        flex-grow: 1;
        cursor: pointer;
        text-overflow: ellipsis;
        color: var(--color-main-text);
        font-weight: bold;
    }
}

.line-two {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    white-space: nowrap;
    &--bold {
        font-weight: bold;
    }

    &__subname {
        overflow: hidden;
        flex-grow: 1;
        cursor: pointer;
        white-space: nowrap;
        text-overflow: ellipsis;
        color: var(--color-text-maxcontrast);
    }

    &__additional_elements {
        margin: 2px 4px 0 4px;
        display: flex;
        align-items: center;
    }

    &__indicator {
        margin: 0 5px;
    }
}

</style>

<style lang="scss">
.list-diff-item .checkbox-radio-switch__label {
    //&:hover, &:focus, &:focus-within {
        background-color: transparent !important;
    //}
}
</style>