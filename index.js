var fs       = require('fs'),
    conflate = require('conflate'),
    php      = require('phpexe') // angleman/phpexe


// json string in and out
function DeviceAtlas(config) {
	var defaults = {}
	config = (config) ? conflate(defaults, config) : defaults
	
	if (!config.dataPath) throw new Error('missing dataPath config')

	this.device = function(ua, callback) {
		if (typeof ua === 'string') {
			ua = [ ua ]
		}
		if (ua[0] == 'DeviceAtlas/test') {
			callback(null, { ok: 1} )
		}
		var args = [ config.dataPath ]
		for (var i = 0; i < ua.length; i++) {
			args.push(ua[i])
		}
		php(__dirname + '/index.php', args, function(error, data) {
			if (!error) {
				try {
					data = JSON.parse(data)
				} catch (e) {
					error = e
				}
			}
			callback(error, data)
		})
	}
}

module.exports = DeviceAtlas