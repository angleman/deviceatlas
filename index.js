var fs = require('fs'),
    php = require('phpexe') // angleman/phpexe
; 


 
// json string in and out
function DeviceAtlas(config) {
	var defaults = {
	}

	config = (config) ? conflate(defaults, config) : defaults;

	function device(ua, callback) {
		php('index.php', function(error, data) {
			if (error) {
				data = undefined;
			} else {
				error = undefined;
			}
			callback(error, data);
		}
	}
}
 
module.exports = DeviceAtlas;