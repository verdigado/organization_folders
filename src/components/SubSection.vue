<template>
    <div class="subsection-container">
		<div class="subsection-header">
			<slot name="header" />
		</div>
		<slot />
    </div>
</template>

<style lang="scss" scoped>
.subsection-container {
	margin-bottom: 12px;

	.subsection-header {
		margin-bottom: 3px;
	}
}
</style>