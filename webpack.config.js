const path = require('path')

const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry["init"] = {
	import: path.join(__dirname, 'src', 'init.js'),
}

webpackConfig.entry["adminSettings"] = {
	import: path.join(__dirname, 'src', 'adminSettings.js'),
}

module.exports = webpackConfig
