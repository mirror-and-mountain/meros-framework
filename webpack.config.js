const path = require('path');
const defaultConfig = require('@wordpress/scripts/config/webpack.config');

module.exports = {
  ...defaultConfig,
  entry: {
    'src': './src/assets/src/meros-livewire.js',
  },
  output: {
    ...defaultConfig.output,
    path: path.resolve(__dirname, 'src/assets/build'),
    filename: 'meros-livewire.js',
  },
};