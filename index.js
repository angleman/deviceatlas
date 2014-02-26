var fs = require('fs'),
    php = require('phpexe') // angleman/phpexe
; 



php('sample.php', function(error, data) {
	if (error) {
		console.log(error) // PHP error or stderr
	} else {
		console.log(data); // PHP stdout  'Hello'
	}
}
 
// json string in and out
function DeviceAtlas(config) {
	var defaults = {
	}

	config = (config) ? conflate(defaults, config) : defaults;

}
 
module.exports = DeviceAtlas;