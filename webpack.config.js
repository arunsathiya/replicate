const defaultConfig = require("@wordpress/scripts/config/webpack.config");

module.exports = {
    ...defaultConfig,
    devServer: {
        hot: true, 
        liveReload: true,
        allowedHosts: 'all',
        headers: {
            "Access-Control-Allow-Origin": "*"
        }
    },
    watchOptions: {
        aggregateTimeout: 300,
        poll: 1000,
        ignored: /node_modules/
    }
};
