var should             = require('should')
  , JsonExpectedStream = require('../index.js')
;


describe('create()', function() {
    describe('basic arguments', function() {
        it('exists', function() {
			var jsonExpectedStream = new JsonExpectedStream('first,second');
            should.exist(jsonExpectedStream);
        });
    });
});
