<template>
    <div class="section-container">
		<div class="section-header">
			<slot name="header" />
		</div>
		<slot />
    </div>
</template>

<style lang="scss" scoped>
.section-container {
	margin-top: 12px;
	margin-bottom: 24px;

	.section-header {
		margin-bottom: 12px;
	}
}
</style>